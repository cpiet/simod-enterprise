<?php

namespace App\Services;

use App\Models\SocialSetting;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pemanggil Actor Apify bersama dengan FAILOVER antar-token.
 *
 * - Token diambil dari pengaturan Polda → pengaturan mana pun yang berisi token → APIFY_API_TOKEN (dipisah baris/koma).
 * - Token dicoba berurutan. Bila ditolak (401/403), saldo/batas habis (402, atau pesan batas pemakaian), atau dibatasi
 *   (429), token berikutnya dipakai otomatis. Token yang saldonya habis diistirahatkan TOKEN_REST_MINUTES.
 * - Rem biaya: maksimum `simod.apify_daily_runs` run per hari (seluruh aplikasi); 0 = tanpa batas.
 * - Setiap run memakai `maxItems` dan `maxTotalChargeUsd` sehingga biaya per run terkendali.
 */
class ApifyClient
{
    public const TOKEN_REST_MINUTES = 60;

    public function __construct(private ApifySyncService $tokens) {}

    /** @return array<int, string> */
    public function tokensFor(?int $poldaId): array
    {
        $raw = ($poldaId ? SocialSetting::where('polda_id', $poldaId)->value('apify_api_token') : null)
            ?: SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token')
            ?: config('services.apify.token', env('APIFY_API_TOKEN', ''));

        return $this->tokens->parseTokens($raw);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{success: bool, items?: array, message?: string, status?: string, token_index?: int}
     */
    public function run(string $actor, array $input, ?int $poldaId, int $maxItems, float $maxChargeUsd = 0.5, int $timeoutSeconds = 150): array
    {
        $tokens = $this->tokensFor($poldaId);
        if (! $tokens) {
            return ['success' => false, 'status' => 'no_token', 'message' => 'Token Apify belum diatur. Administrator mengisinya di Keamanan & Pemeliharaan → Mesin Intelijen AI.'];
        }

        $limit = (int) config('simod.apify_daily_runs', 60);
        $dayKey = 'simod.apify_runs.' . now()->format('Ymd');
        if ($limit > 0 && (int) Cache::get($dayKey, 0) >= $limit) {
            return ['success' => false, 'status' => 'daily_limit', 'message' => "Batas harian {$limit} pengambilan data Apify sudah tercapai (rem biaya). Coba lagi besok atau minta administrator menaikkan SIMOD_APIFY_DAILY_RUNS."];
        }

        $lastMessage = 'Apify tidak dapat menyelesaikan permintaan.';
        $tried = 0;
        foreach ($tokens as $i => $token) {
            $restKey = 'simod.apify_token_rest.' . sha1($token);
            if (Cache::has($restKey)) {
                continue; // saldo token ini habis baru-baru ini
            }
            $tried++;
            try {
                $response = Http::withToken($token)->timeout($timeoutSeconds + 30)->post(
                    "https://api.apify.com/v2/acts/{$actor}/run-sync-get-dataset-items?" . http_build_query([
                        'maxItems' => $maxItems, 'maxTotalChargeUsd' => $maxChargeUsd, 'timeout' => $timeoutSeconds,
                    ]),
                    $input
                );
            } catch (ConnectionException $e) {
                return ['success' => false, 'status' => 'timeout', 'message' => 'Apify tidak merespons tepat waktu. Coba lagi beberapa saat lagi.'];
            }

            if ($response->successful()) {
                Cache::add($dayKey, 0, now()->endOfDay());
                Cache::increment($dayKey);
                if ($i > 0) {
                    Log::info("Apify failover: {$actor} berhasil dengan token cadangan #" . ($i + 1));
                }
                $items = $response->json();

                return is_array($items) && array_is_list($items)
                    ? ['success' => true, 'items' => $items, 'token_index' => $i]
                    : ['success' => false, 'status' => 'bad_response', 'message' => 'Respons Apify tidak dikenali. Coba lagi nanti.'];
            }

            $status = $response->status();
            $error = (string) ($response->json('error.message') ?? '');
            $usageExceeded = $status === 402 || (stripos($error, 'usage') !== false && stripos($error, 'limit') !== false);
            Log::warning("Apify {$actor} token #" . ($i + 1) . " HTTP {$status}: " . mb_substr($error, 0, 200));

            if ($usageExceeded) {
                Cache::put($restKey, true, now()->addMinutes(self::TOKEN_REST_MINUTES));
                $lastMessage = 'Saldo atau batas pemakaian Apify habis pada semua token. Tambahkan token baru atau tunggu periode berikutnya.';
                continue;
            }
            if (in_array($status, [401, 403], true)) {
                $lastMessage = 'Token Apify ditolak (tidak valid atau kedaluwarsa). Minta administrator memperbaruinya.';
                continue;
            }
            if ($status === 429) {
                $lastMessage = 'Apify membatasi permintaan. Coba lagi beberapa menit lagi.';
                continue;
            }

            // Galat dari Actor/input (400/404/5xx): token lain tidak akan menolong.
            return ['success' => false, 'status' => 'actor_error', 'message' => 'Apify tidak dapat menyelesaikan pengambilan (kode ' . $status . '). Coba lagi nanti.'];
        }

        if ($tried === 0) {
            $lastMessage = 'Semua token Apify sedang diistirahatkan karena saldonya habis. Coba lagi nanti atau tambahkan token baru.';
        }

        return ['success' => false, 'status' => 'all_tokens_failed', 'message' => $lastMessage];
    }
}
