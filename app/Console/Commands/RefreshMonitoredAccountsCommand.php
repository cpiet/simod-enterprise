<?php

namespace App\Console\Commands;

use App\Models\MonitoredSocialAccount;
use App\Services\CommentDigestService;
use App\Services\SocialAccountMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Pemantauan berkala akun prioritas (sakelar "Pantau otomatis"): sinkron akun yang terakhir disinkronkan lebih dari
 * SIMOD_SOCIAL_AUTO_INTERVAL_HOURS jam lalu, lalu ambil komentar untuk SATU unggahan baru yang menonjol per akun.
 * Dibatasi SIMOD_APIFY_AUTO_RUNS run otomatis per hari (terpisah dari rem harian total ApifyClient) agar selalu
 * tersisa jatah untuk pemakaian manual. Dijadwalkan tiap jam (ScheduledTasks).
 */
class RefreshMonitoredAccountsCommand extends Command
{
    protected $signature = 'social:accounts-refresh';

    protected $description = 'Sinkron berkala akun media sosial prioritas (Pantau otomatis) dengan batas biaya Apify harian';

    public function handle(SocialAccountMonitoringService $monitor, CommentDigestService $digests): int
    {
        $limit = (int) config('simod.apify_auto_runs', 20);
        $key = 'simod.apify_auto_runs.' . now()->format('Ymd');
        $hours = max(1, (int) config('simod.social_auto_interval_hours', 6));
        $use = function () use ($limit, $key): bool {
            if ($limit > 0 && (int) Cache::get($key, 0) >= $limit) {
                return false;
            }
            Cache::add($key, 0, now()->endOfDay());
            Cache::increment($key);

            return true;
        };

        $due = MonitoredSocialAccount::where('auto_monitor', true)
            ->where(fn ($q) => $q->whereNull('last_synced_at')->orWhere('last_synced_at', '<=', now()->subHours($hours)))
            ->orderByRaw('last_synced_at IS NULL DESC')->orderBy('last_synced_at')->get();

        $done = 0;
        foreach ($due as $account) {
            if (! $use()) {
                $this->warn("Batas {$limit} run otomatis hari ini tercapai; sisa akun menunggu besok.");
                break;
            }
            $before = array_column($account->posts ?? [], 'url');
            $result = $monitor->sync($account);
            $this->line("{$account->platform}/{$account->username}: {$result['message']}");
            $done++;
            if (! $result['success']) {
                continue;
            }

            // Komentar untuk satu unggahan BARU yang menonjol (paling banyak komentar) agar peringatan komentar negatif berjalan.
            $candidate = collect(\App\Support\InstagramHighlights::annotate($account->fresh()->posts ?? []))
                ->filter(fn ($p) => ! in_array($p['url'], $before, true) && ! empty($p['highlight']) && ($p['comments'] ?? 0) > 0)
                ->sortByDesc('comments')->first();
            if ($candidate && $use()) {
                $d = $digests->fetch($account->fresh(), $candidate['url']);
                $this->line('  komentar: ' . $d['message']);
            }
        }

        $this->info("Selesai: {$done} akun diproses dari " . $due->count() . ' yang jatuh tempo.');

        return self::SUCCESS;
    }
}
