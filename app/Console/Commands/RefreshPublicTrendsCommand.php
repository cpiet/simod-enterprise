<?php

namespace App\Console\Commands;

use App\Services\PublicTrendsService;
use Illuminate\Console\Command;

/**
 * Perbarui tren pencarian publik Indonesia (Google Trends). Dijadwalkan tiap jam lewat ScheduledTasks.
 */
class RefreshPublicTrendsCommand extends Command
{
    protected $signature = 'social:trends-refresh';

    protected $description = 'Tarik tren pencarian publik Indonesia dari Google Trends (gratis, tanpa kunci API)';

    public function handle(PublicTrendsService $trends): int
    {
        $result = $trends->refresh();
        $this->{$result['success'] ? 'info' : 'warn'}($result['message']);

        return $result['success'] ? self::SUCCESS : self::FAILURE;
    }
}
