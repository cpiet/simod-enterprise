<?php

namespace App\Console\Commands;

use App\Models\Polda;
use App\Services\SocialSyndicationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sinkron radar media sosial lapis GRATIS (sindikasi Google News) untuk setiap Polda aktif.
 * Tidak pernah memanggil Apify berbayar; Apify tetap manual lewat tombol "Sinkron & Analisis".
 * Dijadwalkan tiap 30 menit (ScheduledTasks, dapat dimatikan dengan SIMOD_SOCIAL_AUTO_SYNC=false).
 */
class SocialFreeSyncCommand extends Command
{
    protected $signature = 'social:sync-free {--polda= : Kode Polda tertentu (bawaan: semua Polda aktif)}';

    protected $description = 'Sinkron radar media sosial lapis gratis (tanpa Apify) untuk Polda aktif';

    public function handle(SocialSyndicationService $syndication): int
    {
        $query = Polda::where('is_active', true)->orderBy('id');
        if ($code = $this->option('polda')) {
            $query->where('code', strtoupper($code));
        }

        $total = 0;
        foreach ($query->get() as $polda) {
            try {
                $new = (int) ($syndication->syncRadarForPolda($polda->id)['new_posts'] ?? 0);
                $total += $new;
                $this->line("{$polda->code}: {$new} unggahan baru");
            } catch (\Throwable $e) {
                // Satu Polda gagal tidak menghentikan Polda lain.
                Log::warning("social:sync-free {$polda->code} gagal: " . $e->getMessage());
                $this->warn("{$polda->code}: gagal ({$e->getMessage()})");
            }
        }

        $this->info("Selesai: {$total} unggahan baru.");

        return self::SUCCESS;
    }
}
