<?php

namespace App\Services;

use App\Models\BackupRecord;
use App\Models\RssSyncLog;
use App\Models\SecuritySetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kesehatan server & kesiapan hosting: seluruhnya hasil pengukuran/pemeriksaan nyata pada saat halaman dibuka.
 * Tidak ada klaim statis (mis. "WAF aktif", "enkripsi terpasang"); hal yang tidak dapat diperiksa tidak ditampilkan.
 */
class SystemHealth
{
    /**
     * Metrik server yang terukur.
     *
     * @return array<string, mixed>
     */
    public function snapshot(?string $timezone = null): array
    {
        $db = ['driver' => DB::connection()->getDriverName(), 'version' => null, 'ok' => false, 'latency_ms' => null, 'error' => null];
        try {
            $t = [];
            for ($i = 0; $i < 3; $i++) {
                $s = microtime(true);
                DB::select('select 1');
                $t[] = (microtime(true) - $s) * 1000;
            }
            $db['latency_ms'] = round(array_sum($t) / count($t), 1);
            $db['version'] = match ($db['driver']) {
                'sqlite' => DB::selectOne('select sqlite_version() as v')->v ?? null,
                default => DB::selectOne('select version() as v')->v ?? null,
            };
            $db['ok'] = true;
        } catch (\Throwable $e) {
            $db['error'] = 'Tidak dapat terhubung ke basis data.';
        }

        $free = @disk_free_space(base_path());
        $total = @disk_total_space(base_path());
        $tz = $timezone ?: config('app.timezone');

        return [
            'php_version'   => PHP_VERSION,
            'laravel_ver'   => app()->version(),
            'environment'   => app()->environment(),
            'database'      => $db,
            'disk_free_gb'  => $free !== false ? round($free / 1073741824, 1) : null,
            'disk_total_gb' => $total !== false ? round($total / 1073741824, 1) : null,
            'memory_mb'     => round(memory_get_peak_usage(true) / 1048576, 1),
            'memory_limit'  => ini_get('memory_limit'),
            'timezone'      => $tz,
            'server_time'   => now($tz)->format('d M Y, H:i:s'),
            'drivers'       => [
                'session' => config('session.driver'),
                'cache'   => config('cache.default'),
                'queue'   => config('queue.default'),
            ],
            'queue'         => $this->queueStats(),
        ];
    }

    /** @return array{pending: int|null, failed: int|null} */
    private function queueStats(): array
    {
        $count = fn (string $table) => Schema::hasTable($table) ? DB::table($table)->count() : null;

        return ['pending' => config('queue.default') === 'database' ? $count('jobs') : null, 'failed' => $count('failed_jobs')];
    }

    /**
     * Status modul penjadwal: aktif/tidak (dari detak nyata), tiap tugas otomatis (terakhir berjalan, terlambat, gagal),
     * dan saran pemasangan cron menurut sistem operasi.
     *
     * @return array<string, mixed>
     */
    public function scheduler(): array
    {
        $now = time();
        $ago = fn (?int $ts) => $ts === null ? null : \App\Support\FastTime::ago(gmdate('Y-m-d H:i:s', $ts));
        $tick = \App\Support\ScheduledTasks::lastTick();
        $active = $tick !== null && ($now - $tick) <= 180;

        $tasks = [];
        foreach (\App\Support\ScheduledTasks::definitions() as $def) {
            $enabled = ! isset($def['enabled']) || ($def['enabled'])();
            $last = \App\Support\ScheduledTasks::lastRun($def['key']);
            $failed = \App\Support\ScheduledTasks::lastFailure($def['key']);
            // Toleransi keterlambatan: 1,5x interval + cadangan (menit); tugas harian diberi 2 jam.
            $limitMin = $def['every'] * 1.5 + ($def['every'] >= 1440 ? 120 : 5);

            $status = match (true) {
                ! $enabled => 'off',
                $last === null => 'never',
                $failed !== null && $failed >= $last - 5 => 'failed',
                (($now - $last) / 60) > $limitMin => 'late',
                default => 'ok',
            };

            $tasks[] = [
                'key' => $def['key'], 'label' => $def['label'], 'command' => 'php artisan ' . $def['command'],
                'cadence' => $def['cadence'], 'why' => $def['why'],
                'status' => $status, 'last_run_ago' => $ago($last),
            ];
        }

        $base = base_path();
        $isWindows = PHP_OS_FAMILY === 'Windows';
        $phpExe = (PHP_SAPI === 'cli' && str_contains(strtolower(PHP_BINARY), 'php')) ? PHP_BINARY : null;

        return [
            'active'        => $active,
            'last_tick'     => $tick ? gmdate('c', $tick) : null,
            'last_tick_ago' => $ago($tick),
            'tasks'         => $tasks,
            'os'            => $isWindows ? 'windows' : 'linux',
            'suggestions'   => [
                [
                    'key' => 'linux', 'title' => 'Server Linux / hosting (cron)',
                    'command' => "* * * * * cd {$base} && php artisan schedule:run >> /dev/null 2>&1",
                    'note' => 'Di panel hosting (mis. hPanel → Lanjutan → Cron Jobs) pilih "setiap menit" dan isi perintahnya. Bila perlu, ganti php dengan jalur lengkap, mis. /usr/bin/php.',
                ],
                [
                    'key' => 'windows', 'title' => 'Komputer Windows / Laragon (Task Scheduler)',
                    'command' => 'schtasks /Create /SC MINUTE /MO 1 /TN "SIMOD Scheduler" /TR "\"' . ($phpExe ?: '<lokasi php.exe>') . '\" \"' . $base . DIRECTORY_SEPARATOR . 'artisan\" schedule:run"',
                    'note' => 'Jalankan di Command Prompt (Administrator). Tugas akan memanggil penjadwal tiap menit selama komputer menyala.',
                ],
            ],
        ];
    }

    /**
     * Daftar pemeriksaan kesiapan. Setiap butir: status ok|warn|fail, judul, penjelasan, dan saran bila perlu.
     *
     * @return array<int, array{key: string, status: string, title: string, detail: string, hint: string|null}>
     */
    public function checks(Request $request, SecuritySetting $setting): array
    {
        $checks = [];
        $add = function (string $key, string $status, string $title, string $detail, ?string $hint = null) use (&$checks) {
            $checks[] = compact('key', 'status', 'title', 'detail', 'hint');
        };

        $production = app()->environment('production');
        $secure = $request->isSecure() || strtolower((string) $request->header('X-Forwarded-Proto')) === 'https';

        // 1. HTTPS
        if ($secure) {
            $add('https', 'ok', 'Koneksi HTTPS', 'Halaman ini dibuka melalui HTTPS.');
        } else {
            $add('https', $production ? 'fail' : 'warn', 'Koneksi HTTPS', 'Halaman ini dibuka melalui HTTP (tidak terenkripsi).',
                $production ? 'Aktifkan SSL di hosting dan paksa HTTPS.' : 'Wajar di komputer lokal; wajib HTTPS di hosting.');
        }

        // 2. Mode debug
        if (config('app.debug')) {
            $add('debug', $production ? 'fail' : 'warn', 'Mode debug (APP_DEBUG)', 'Aktif: galat dapat menampilkan detail internal.',
                'Di hosting set APP_DEBUG=false pada berkas .env.');
        } else {
            $add('debug', 'ok', 'Mode debug (APP_DEBUG)', 'Nonaktif.');
        }

        // 3. Lingkungan
        $add('env', $production ? 'ok' : 'warn', 'Lingkungan aplikasi (APP_ENV)', 'Saat ini: ' . app()->environment() . '.',
            $production ? null : 'Di hosting set APP_ENV=production.');

        // 3b. Versi PHP & batas sumber daya (analisis AI dapat memakan hingga ~90 detik)
        $phpOk = version_compare(PHP_VERSION, '8.2.0', '>=');
        $add('php_version', $phpOk ? 'ok' : 'fail', 'Versi PHP', 'PHP ' . PHP_VERSION . ($phpOk ? '.' : ' (aplikasi membutuhkan 8.2 atau lebih baru).'),
            $phpOk ? null : 'Ubah versi PHP ke 8.2+ di panel hosting (hPanel → PHP Configuration).');

        $toMb = function (string $v): float {
            $v = trim($v);
            if ($v === '-1') {
                return INF;
            }
            $n = (float) $v;

            return match (strtoupper(substr($v, -1))) { 'G' => $n * 1024, 'K' => $n / 1024, 'M' => $n, default => $n / 1048576 };
        };
        $problems = [];
        $exec = (int) ini_get('max_execution_time');
        if ($exec !== 0 && $exec < 120) {
            $problems[] = "max_execution_time {$exec} dtk (disarankan 120 atau lebih untuk analisis AI)";
        }
        if ($toMb((string) ini_get('memory_limit')) < 256) {
            $problems[] = 'memory_limit ' . ini_get('memory_limit') . ' (disarankan 256M atau lebih)';
        }
        $add('limits', $problems === [] ? 'ok' : 'warn', 'Batas sumber daya PHP', $problems === [] ? 'Memadai (waktu eksekusi dan memori).' : implode('; ', $problems) . '.',
            $problems === [] ? null : 'Naikkan di hPanel → PHP Configuration (Options), lalu muat ulang halaman ini.');

        // 4. Cookie sesi
        $cookieOk = (bool) config('session.http_only');
        $cookieSecure = (bool) config('session.secure');
        if ($cookieOk && ($cookieSecure || ! $secure)) {
            $add('cookie', $secure && ! $cookieSecure ? 'warn' : 'ok', 'Cookie sesi', 'HttpOnly aktif' . ($cookieSecure ? ', Secure aktif.' : '; Secure belum diwajibkan.'));
        } else {
            $add('cookie', 'warn', 'Cookie sesi', 'Cookie sesi belum sepenuhnya aman.', 'Set SESSION_SECURE_COOKIE=true bila memakai HTTPS.');
        }

        // 5. APP_URL
        $configured = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($configured && strcasecmp($configured, $request->getHost()) !== 0) {
            $add('app_url', 'warn', 'APP_URL sesuai alamat situs', "APP_URL berisi {$configured}, tetapi situs dibuka dari {$request->getHost()}.", 'Samakan APP_URL di .env dengan alamat situs hosting.');
        } else {
            $add('app_url', 'ok', 'APP_URL sesuai alamat situs', 'Sesuai.');
        }

        // 6. Folder yang harus dapat ditulis
        $unwritable = array_values(array_filter([storage_path(), base_path('bootstrap/cache')], fn ($p) => ! is_writable($p)));
        $add('writable', $unwritable === [] ? 'ok' : 'fail', 'Folder storage & cache dapat ditulis',
            $unwritable === [] ? 'storage dan bootstrap/cache dapat ditulis.' : 'Tidak dapat ditulis: ' . implode(', ', array_map(fn ($p) => str_replace(base_path() . DIRECTORY_SEPARATOR, '', $p), $unwritable)) . '.',
            $unwritable === [] ? null : 'Atur izin folder tersebut di hosting (mis. 775).');

        // 7. Ekstensi PHP yang dibutuhkan
        $needed = array_filter(['mbstring', 'openssl', 'curl', 'fileinfo', 'json', 'zlib', 'tokenizer', 'xml', 'ctype', 'pdo']);
        $missing = array_values(array_filter($needed, fn ($e) => ! extension_loaded($e)));
        $add('extensions', $missing === [] ? 'ok' : 'fail', 'Ekstensi PHP', $missing === [] ? 'Seluruh ekstensi yang dibutuhkan tersedia.' : 'Belum aktif: ' . implode(', ', $missing) . '.',
            $missing === [] ? null : 'Aktifkan ekstensi tersebut di panel hosting (PHP Selector).');

        // 8. Penjadwal (detak ditulis tiap menit oleh schedule:run)
        $sched = $this->scheduler();
        if ($sched['active']) {
            $add('scheduler', 'ok', 'Penjadwal (cron) berjalan', 'Aktif: detak terakhir ' . $sched['last_tick_ago'] . '.');
        } else {
            $add('scheduler', 'warn', 'Penjadwal (cron) berjalan', $sched['last_tick'] ? 'Tidak aktif: detak terakhir ' . $sched['last_tick_ago'] . '.' : 'Tidak aktif: belum pernah ada detak.',
                'Pasang cron schedule:run tiap menit; panduan dan perintahnya ada di tab Penjadwal.');
        }

        // 9. Cadangan
        $lastBackup = BackupRecord::query()->latest('created_at')->first();
        if (! $lastBackup) {
            $add('backup', 'warn', 'Cadangan basis data', 'Belum ada cadangan.', 'Buat cadangan pertama di menu Backup Data.');
        } else {
            $hours = (int) now()->diffInHours($lastBackup->created_at, true);
            $add('backup', $hours <= 48 ? 'ok' : 'warn', 'Cadangan basis data', 'Cadangan terakhir ' . ($hours < 1 ? 'kurang dari 1 jam' : "{$hours} jam") . ' lalu.', $hours <= 48 ? null : 'Periksa jadwal cadangan otomatis.');
        }

        // 10. Parameter penguncian login
        $add('lockout', $setting->max_login_attempts <= 10 ? 'ok' : 'warn', 'Penguncian login',
            "Dikunci setelah {$setting->max_login_attempts} gagal selama {$setting->lockout_minutes} menit; sesi berakhir setelah {$setting->session_timeout_minutes} menit tidak aktif.");

        // 11. Antrean gagal
        $failed = $this->queueStats()['failed'];
        if ($failed !== null) {
            $add('queue', $failed === 0 ? 'ok' : 'warn', 'Antrean tugas', $failed === 0 ? 'Tidak ada tugas gagal.' : "{$failed} tugas gagal tercatat.", $failed === 0 ? null : 'Periksa tabel failed_jobs.');
        }

        // 12. Token ingest
        $hasToken = (string) config('simod.ingest_token') !== '';
        $add('ingest', $hasToken ? 'ok' : 'warn', 'Token API ingest worker', $hasToken ? 'Terisi.' : 'Belum diisi: worker Python tidak dapat mengirim berita (API menolak dengan 503).',
            $hasToken ? null : 'Isi INGEST_API_TOKEN di .env bila memakai worker scraper.');

        return $checks;
    }
}
