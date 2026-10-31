<?php

namespace App\Console\Commands;

use App\Models\Polda;
use App\Services\RssSyncService;
use Illuminate\Console\Command;

class RssSyncCommand extends Command
{
    protected $signature = 'rss:sync 
                            {--polda= : Kode Polda spesifik (e.g. SULBAR) atau kosong untuk seluruh Polda}
                            {--force : Abaikan mutex lock jika ada lock macet}
                            {--scheduled : Flag dari Task Scheduler untuk mengecek shouldRunNow()}';

    protected $description = 'Sinkronisasi agregasi berita online RSS dengan pengamanan mutex lock dan auto-capping multi-polda';

    public function handle(RssSyncService $syncService): int
    {
        $force = (bool) $this->option('force');
        $isScheduled = (bool) $this->option('scheduled');
        $poldaCode = $this->option('polda');

        if ($isScheduled && ! $force) {
            if (! $syncService->shouldRunNow()) {
                $this->info('Bukan waktu eksekusi jadwal sinkronisasi RSS. Dilewati.');
                return self::SUCCESS;
            }
        }

        $this->info('Memulai sinkronisasi berita online RSS...');

        if ($poldaCode) {
            $polda = Polda::where('code', strtoupper($poldaCode))->first();
            if (! $polda) {
                $this->error("Polda dengan kode [{$poldaCode}] tidak ditemukan.");
                return self::FAILURE;
            }

            $res = $syncService->syncPolda($polda, $isScheduled ? 'automatic' : 'artisan_cli', $force);
            if (! $res['success']) {
                $this->warn($res['message']);
                return self::FAILURE;
            }

            $this->info("Berhasil! {$res['articles_saved']} berita disimpan untuk {$polda->name} ({$res['duration_ms']}ms).");
            return self::SUCCESS;
        }

        // Sinkronisasi Multi-Polda
        $res = $syncService->syncAll($isScheduled ? 'automatic' : 'artisan_cli', $force);

        if (! $res['success']) {
            $this->warn($res['message']);
            return self::FAILURE;
        }

        $this->info("Berhasil Multi-Polda! Total {$res['articles_saved']} berita disimpan dari {$res['feeds_count']} feeds ({$res['duration_ms']}ms, Memori: {$res['memory_mb']}MB).");
        return self::SUCCESS;
    }
}
