<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Batas pemakaian AI per pengguna per hari + deteksi kuota Google habis.
 *
 * - 1 "permintaan AI" = 1 aksi pengguna (analisis atau pesan chat) yang berhasil dijawab Gemini.
 * - Aksi yang dilewati karena data tidak berubah (skipped) tidak dihitung.
 * - Saat kuota Google habis, panggilan baru ditahan sementara agar tidak menghabiskan sisa jatah per menit.
 */
class AiQuota
{
    public const DEFAULT_LIMIT = 40;
    private const BLOCK_MINUTES = 10;

    public function limitFor(?int $poldaId): int
    {
        $v = null;
        if ($poldaId) {
            $v = AiSetting::where('polda_id', $poldaId)->value('daily_user_limit');
        }
        if ($v === null) {
            $v = AiSetting::whereNull('polda_id')->value('daily_user_limit');
        }

        return $v === null ? self::DEFAULT_LIMIT : (int) $v;
    }

    public function usedToday(int $userId): int
    {
        return (int) DB::table('ai_usage_logs')
            ->where('user_id', $userId)->where('status', 'ok')
            ->where('created_at', '>=', now()->startOfDay())->count();
    }

    /** @return array{used:int, limit:int, remaining:?int, blocked:bool} */
    public function snapshot(User $user, ?int $poldaId): array
    {
        $limit = $this->limitFor($poldaId);
        $used = $this->usedToday($user->id);

        return [
            'used'      => $used,
            'limit'     => $limit,
            'remaining' => $limit > 0 ? max(0, $limit - $used) : null,
            'blocked'   => $this->isBlocked($poldaId),
        ];
    }

    /** Pesan galat bila aksi tidak boleh dijalankan sekarang; null bila boleh. */
    public function denial(User $user, ?int $poldaId): ?string
    {
        $s = $this->snapshot($user, $poldaId);

        if ($s['limit'] > 0 && $s['used'] >= $s['limit']) {
            return "Batas pemakaian AI harian Anda ({$s['limit']} permintaan) sudah tercapai. Coba lagi besok atau minta administrator menaikkan batas di Pengaturan Mesin AI.";
        }
        if ($s['blocked']) {
            return 'Jatah AI dari Google sedang habis. Permintaan ditahan sementara agar tidak memboroskan sisa jatah; coba lagi dalam beberapa menit. '
                . 'Jika berulang, jatah harian kemungkinan habis: tunggu pergantian hari (reset sekitar tengah malam waktu Pasifik) atau aktifkan penagihan API.';
        }

        return null;
    }

    public function isBlocked(?int $poldaId): bool
    {
        return Cache::has($this->blockKey($poldaId));
    }

    /** Catat hasil panggilan; mengembalikan status yang dicatat. */
    public function finish(?User $user, ?int $poldaId, string $feature, array $result): string
    {
        $status = 'ok';
        if (!($result['success'] ?? false)) {
            $quota = str_contains((string) ($result['message'] ?? ''), 'Kuota API Gemini');
            $status = $quota ? 'quota' : 'error';
            if ($quota) {
                Cache::put($this->blockKey($poldaId), true, now()->addMinutes(self::BLOCK_MINUTES));
            }
        }

        $this->log($user, $poldaId, $feature, $status, $result['model_used'] ?? $result['model'] ?? null);

        return $status;
    }

    public function log(?User $user, ?int $poldaId, string $feature, string $status, ?string $model = null): void
    {
        DB::table('ai_usage_logs')->insert([
            'user_id'    => $user?->id,
            'polda_id'   => $poldaId,
            'feature'    => $feature,
            'model'      => $model ? mb_substr($model, 0, 80) : null,
            'status'     => $status,
            'created_at' => now(),
        ]);
    }

    /** Ringkasan hari ini untuk halaman Pengaturan AI. */
    public function todaySummary(): array
    {
        $since = now()->startOfDay();
        $rows = DB::table('ai_usage_logs')->where('created_at', '>=', $since);

        $byStatus = (clone $rows)->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');
        $byUser = DB::table('ai_usage_logs as l')
            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')
            ->where('l.created_at', '>=', $since)->where('l.status', 'ok')
            ->select(DB::raw("coalesce(u.name, 'Sistem') as name"), DB::raw('count(*) as n'))
            ->groupBy('u.name')->orderByDesc('n')->limit(8)->get()
            ->map(fn ($r) => ['name' => $r->name, 'count' => (int) $r->n])->all();
        $byFeature = (clone $rows)->where('status', 'ok')->select('feature', DB::raw('count(*) as n'))->groupBy('feature')->pluck('n', 'feature');

        return [
            'ok'      => (int) ($byStatus['ok'] ?? 0),
            'quota'   => (int) ($byStatus['quota'] ?? 0),
            'error'   => (int) ($byStatus['error'] ?? 0),
            'skipped' => (int) ($byStatus['skipped'] ?? 0),
            'by_user' => $byUser,
            'by_feature' => $byFeature,
        ];
    }

    private function blockKey(?int $poldaId): string
    {
        return 'ai.quota_blocked.' . ($poldaId ?: 0);
    }
}
