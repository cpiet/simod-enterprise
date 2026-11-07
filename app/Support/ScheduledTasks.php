<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/**
 * Sumber tunggal tugas terjadwal. Dipakai `routes/console.php` untuk mendaftarkan jadwal DAN halaman
 * Audit Log → Penjadwal untuk menampilkan status nyata (aktif/tidak, terakhir berjalan, terlambat).
 *
 * Setiap kali penjadwal Laravel dipanggil (`schedule:run` oleh cron), tugas "detak" menulis waktu ke cache.
 * Bila detak berhenti, UI menandai penjadwal TIDAK AKTIF dan menyarankan cara memasang cron.
 */
class ScheduledTasks
{
    public const HEARTBEAT_KEY = 'simod.scheduler.tick';

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function definitions(): array
    {
        return [
            [
                'key' => 'rss_sync', 'label' => 'Sinkron berita RSS', 'command' => 'rss:sync --scheduled',
                'cadence' => 'Setiap menit', 'every' => 1, 'freq' => ['everyMinute'], 'overlap' => 10,
                'why' => 'Menarik berita terbaru dari seluruh portal.',
            ],
            [
                'key' => 'db_backup', 'label' => 'Cadangan basis data otomatis', 'command' => 'db:backup --type=scheduled',
                'cadence' => 'Diperiksa tiap 10 menit (jam cadangan diatur di menu Backup Data)', 'every' => 10, 'freq' => ['everyTenMinutes'], 'overlap' => 20,
                'why' => 'Membuat cadangan sesuai jadwal dan menghapus cadangan lama.',
            ],
            [
                'key' => 'social_free_sync', 'label' => 'Sinkron radar medsos (lapis gratis)', 'command' => 'social:sync-free',
                'cadence' => 'Setiap 30 menit', 'every' => 30, 'freq' => ['everyThirtyMinutes'], 'overlap' => 25,
                'enabled' => fn () => (bool) config('simod.social_auto_sync'),
                'why' => 'Menambah percakapan medsos dari sindikasi Google News tanpa biaya. Apify berbayar tetap manual.',
            ],
            [
                'key' => 'social_accounts_auto', 'label' => 'Pantau otomatis akun prioritas (Apify)', 'command' => 'social:accounts-refresh',
                'cadence' => 'Setiap jam (akun disinkron tiap 6 jam)', 'every' => 60, 'freq' => ['hourly'], 'overlap' => 50,
                'why' => 'Menyinkronkan akun yang diberi sakelar "Pantau otomatis" dan membuat peringatan. Dibatasi SIMOD_APIFY_AUTO_RUNS run per hari.',
            ],
            [
                'key' => 'public_trends', 'label' => 'Tren pencarian publik (Google Trends)', 'command' => 'social:trends-refresh',
                'cadence' => 'Setiap jam', 'every' => 60, 'freq' => ['hourly'], 'overlap' => 10,
                'why' => 'Memperbarui tren pencarian Indonesia di Radar Media Sosial → Tren (gratis, tanpa Apify).',
            ],
            [
                'key' => 'audit_prune', 'label' => 'Pemangkasan log audit', 'command' => 'audit:prune',
                'cadence' => 'Harian 02:30', 'every' => 1440, 'freq' => ['dailyAt', '02:30'],
                'why' => 'Menghapus log audit lebih lama dari retensi (diatur di Audit Log).',
            ],
            [
                'key' => 'media_prune', 'label' => 'Pembersihan berita lama', 'command' => 'media:prune',
                'cadence' => 'Harian 02:00', 'every' => 1440, 'freq' => ['dailyAt', '02:00'],
                'enabled' => fn () => (int) config('simod.media_retention_days') > 0,
                'why' => 'Menghapus berita non-bookmark yang melewati masa retensi.',
            ],
        ];
    }

    /** Daftarkan seluruh tugas pada penjadwal Laravel (dipanggil dari routes/console.php). */
    public static function register(): void
    {
        Schedule::call(fn () => Cache::put(self::HEARTBEAT_KEY, time(), now()->addDays(30)))
            ->everyMinute()
            ->name('simod-heartbeat');

        foreach (self::definitions() as $def) {
            if (isset($def['enabled']) && ! ($def['enabled'])()) {
                continue;
            }

            $event = Schedule::command($def['command']);
            $event->{$def['freq'][0]}(...array_slice($def['freq'], 1));
            if (! empty($def['overlap'])) {
                $event->withoutOverlapping($def['overlap']);
            }
            $key = $def['key'];
            $event->after(fn () => Cache::put("simod.sched.last.{$key}", time(), now()->addDays(60)))
                ->onFailure(fn () => Cache::put("simod.sched.failed.{$key}", time(), now()->addDays(60)));
        }
    }

    public static function lastTick(): ?int
    {
        $v = Cache::get(self::HEARTBEAT_KEY);

        return $v ? (int) $v : null;
    }

    public static function lastRun(string $key): ?int
    {
        $v = Cache::get("simod.sched.last.{$key}");

        return $v ? (int) $v : null;
    }

    public static function lastFailure(string $key): ?int
    {
        $v = Cache::get("simod.sched.failed.{$key}");

        return $v ? (int) $v : null;
    }
}
