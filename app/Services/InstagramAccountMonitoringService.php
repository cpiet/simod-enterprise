<?php

namespace App\Services;

use App\Models\MonitoredInstagramAccount;
use App\Models\SocialSetting;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Pantauan unggahan publik akun Instagram tertentu (mis. tokoh/KOL) lewat Actor Apify `apify/instagram-scraper`.
 * Manual (tanpa jadwal otomatis). Hanya baris milik akun yang diminta dan bertautan Instagram valid yang disimpan;
 * metrik yang tidak tersedia tetap null (tidak direka). Kegagalan mempertahankan snapshot sukses terakhir.
 */
class InstagramAccountMonitoringService
{
    public const POSTS_LIMIT = 12;
    public const MAX_CHARGE_USD = '0.05';

    private function fail(MonitoredInstagramAccount $account, string $status, string $message): array
    {
        $account->update(['sync_status' => $status, 'last_error' => $message]);

        return ['success' => false, 'message' => $message];
    }

    public function sync(MonitoredInstagramAccount $account, ApifySyncService $apify): array
    {
        $lock = Cache::lock('instagram-monitor-'.$account->id, 150);
        if (! $lock->get()) {
            return ['success' => false, 'message' => 'Sinkronisasi akun ini sedang berlangsung.'];
        }

        try {
            $rawTokens = SocialSetting::where('polda_id', $account->polda_id)->value('apify_api_token')
                ?: SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token')
                ?: env('APIFY_API_TOKEN', '');
            $token = $apify->parseTokens($rawTokens)[0] ?? null;
            if (! $token) {
                return $this->fail($account, 'error', 'Token Apify belum diatur. Administrator mengisinya di Keamanan & Pemeliharaan → Mesin Intelijen AI.');
            }

            $response = Http::withToken($token)->timeout(120)->post(
                'https://api.apify.com/v2/acts/apify~instagram-scraper/run-sync-get-dataset-items?timeout=100&maxTotalChargeUsd='.self::MAX_CHARGE_USD,
                ['directUrls' => ['https://www.instagram.com/'.$account->username.'/'], 'resultsType' => 'posts', 'resultsLimit' => self::POSTS_LIMIT, 'skipPinnedPosts' => true]
            );

            if (! $response->successful()) {
                return $this->fail($account, 'error', match (true) {
                    in_array($response->status(), [401, 403], true) => 'Token Apify ditolak (tidak valid atau kedaluwarsa). Minta administrator memperbaruinya.',
                    $response->status() === 402 => 'Saldo atau batas penggunaan Apify habis. Hubungi administrator.',
                    $response->status() === 429 => 'Apify membatasi permintaan. Coba lagi beberapa menit lagi.',
                    default => 'Apify tidak dapat menyelesaikan sinkronisasi (kode '.$response->status().'). Coba lagi nanti.',
                });
            }
            $items = $response->json();
            if (! is_array($items) || ! array_is_list($items)) {
                return $this->fail($account, 'error', 'Respons Apify tidak dikenali. Coba lagi nanti.');
            }

            $posts = [];
            $displayName = null;
            $notFound = false;
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (! empty($item['error']) && empty($item['url'])) {
                    $notFound = true;
                    continue;
                }
                if (strcasecmp((string) ($item['ownerUsername'] ?? ''), $account->username) !== 0) {
                    continue;
                }
                $url = $item['url'] ?? '';
                $host = strtolower((string) parse_url($url, PHP_URL_HOST));
                $path = (string) parse_url($url, PHP_URL_PATH);
                if (! in_array($host, ['instagram.com', 'www.instagram.com'], true) || ! preg_match('~^/(p|reel|reels)/[A-Za-z0-9_-]+/?$~', $path) || parse_url($url, PHP_URL_SCHEME) !== 'https') {
                    continue;
                }
                try {
                    $date = ! empty($item['timestamp']) ? Carbon::parse($item['timestamp'])->toIso8601String() : null;
                } catch (\Throwable $e) {
                    $date = null;
                }
                $number = static fn ($v) => is_numeric($v) && $v >= 0 ? (int) $v : null;
                $thumb = (string) ($item['displayUrl'] ?? '');
                $thumbHost = strtolower((string) parse_url($thumb, PHP_URL_HOST));
                $thumbOk = parse_url($thumb, PHP_URL_SCHEME) === 'https' && (str_ends_with($thumbHost, '.cdninstagram.com') || str_ends_with($thumbHost, '.fbcdn.net'));
                $type = strtolower((string) ($item['type'] ?? ''));

                $posts[$url] = [
                    'url' => $url,
                    'caption' => mb_substr((string) ($item['caption'] ?? ''), 0, 1200),
                    'posted_at' => $date,
                    'likes' => $number($item['likesCount'] ?? null),
                    'comments' => $number($item['commentsCount'] ?? null),
                    'views' => $number($item['videoViewCount'] ?? $item['videoPlayCount'] ?? null),
                    'type' => in_array($type, ['image', 'video', 'sidecar'], true) ? $type : (str_contains($path, '/reel') ? 'video' : null),
                    'hashtags' => array_values(array_slice(array_filter(array_map(fn ($h) => is_string($h) ? mb_substr($h, 0, 60) : null, (array) ($item['hashtags'] ?? []))), 0, 8)),
                    'thumb' => $thumbOk ? $thumb : null,
                ];
                $displayName = $item['ownerFullName'] ?? $displayName;
            }

            if (! $posts) {
                return $this->fail($account, 'empty', $notFound
                    ? 'Akun tidak ditemukan, privat, atau dibatasi Instagram. Periksa penulisan username.'
                    : 'Tidak ada unggahan akun yang dapat diverifikasi. Data sebelumnya tetap tersedia jika ada.');
            }
            $posts = array_values($posts);
            usort($posts, fn ($a, $b) => strcmp($b['posted_at'] ?? '', $a['posted_at'] ?? ''));
            $account->update(['display_name' => $displayName, 'posts' => $posts, 'last_synced_at' => now(), 'sync_status' => 'success', 'last_error' => null]);

            return ['success' => true, 'message' => count($posts).' unggahan @'.$account->username.' berhasil disinkronkan.'];
        } catch (ConnectionException $e) {
            return $this->fail($account, 'error', 'Apify tidak merespons tepat waktu. Coba lagi beberapa saat lagi.');
        } catch (\Throwable $e) {
            report($e);

            return $this->fail($account, 'error', 'Sinkronisasi gagal. Coba lagi; bila berulang, hubungi administrator.');
        } finally {
            $lock->release();
        }
    }
}
