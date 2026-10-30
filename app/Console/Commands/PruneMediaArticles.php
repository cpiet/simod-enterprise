<?php

namespace App\Console\Commands;

use App\Models\Polda;
use App\Services\NewsCleanupService;
use Illuminate\Console\Command;

class PruneMediaArticles extends Command
{
    protected $signature = 'media:prune {--days= : Umur minimum artikel (hari), default env MEDIA_RETENTION_DAYS} {--polda= : Kode Polda (kosong = semua)}';

    protected $description = 'Hapus artikel berita lama yang tidak di-bookmark';

    public function handle(NewsCleanupService $service): int
    {
        $days = (int) ($this->option('days') ?? config('simod.media_retention_days', 90));
        $poldaId = null;

        if ($code = $this->option('polda')) {
            $polda = Polda::where('code', $code)->first();
            if (! $polda) {
                $this->error("Polda {$code} tidak ditemukan.");
                return self::FAILURE;
            }
            $poldaId = $polda->id;
        }

        $deleted = $service->purge($poldaId, $days);
        $this->info("{$deleted} artikel (non-bookmark, > {$days} hari) dihapus.");

        return self::SUCCESS;
    }
}
