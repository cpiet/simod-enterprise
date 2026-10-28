<?php

namespace App\Console\Commands;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class ScrapeRssCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simod:scrape-rss {--polda= : Filter by specific Polda code (e.g. SULBAR)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Ambil berita RSS secara berkala dari seluruh portal media Satwil';

    /**
     * Execute the console command.
     */
    public function handle(RssFeedService $rssService): int
    {
        $this->info('====================================================');
        $this->info(' [SIMOD] Memulai Pengambilan Berita RSS Satwil');
        $this->info('====================================================');

        $poldaCode = $this->option('polda');
        $poldas = Polda::where('is_active', true)
            ->when($poldaCode, fn($q) => $q->where('code', strtoupper($poldaCode)))
            ->get();

        $totalIngested = 0;

        foreach ($poldas as $polda) {
            $feeds = MediaFeed::where('polda_id', $polda->id)
                ->where('is_active', true)
                ->get();

            $this->info("Menarik {$feeds->count()} portal feed untuk {$polda->name}...");

            $items = $rssService->fetchAggregatedFeeds($feeds->toArray(), true);
            $count = count($items);
            $this->info(" -> Diperoleh {$count} artikel live.");

            foreach (array_slice($items, 0, 20) as $item) {
                MediaArticle::updateOrCreate(
                    ['url' => $item['link']],
                    [
                        'polda_id'        => $polda->id,
                        'feed_id'         => $item['source_id'] ?: null,
                        'title'           => $item['title'],
                        'source_name'     => $item['source_name'],
                        'category'        => $item['category'],
                        'image_url'       => $item['image_url'],
                        'summary'         => $item['summary'],
                        'pub_date'        => now(),
                        'sentiment'       => $item['sentiment'] ?? 'netral',
                    ]
                );
                $totalIngested++;
            }
        }

        $this->info('====================================================');
        $this->info(" Sukses memperbarui {$totalIngested} berita ke database!");
        $this->info('====================================================');

        return Command::SUCCESS;
    }
}
