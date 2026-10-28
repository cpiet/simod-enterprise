<?php

namespace App\Console\Commands;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Services\RssFeedService;
use Illuminate\Console\Command;

class SyncMediaFeedsCommand extends Command
{
    protected $signature = 'simod:sync-feeds {--polda=SULBAR} {--force}';
    protected $description = 'Sinkronisasi siaran berita RSS dari seluruh sumber media online terdaftar';

    public function handle(RssFeedService $rssService): int
    {
        $code = $this->option('polda') ?: 'SULBAR';
        $polda = Polda::where('code', $code)->first();

        if (!$polda) {
            $this->error("Polda dengan kode [{$code}] tidak ditemukan.");
            return 1;
        }

        $feeds = MediaFeed::where('polda_id', $polda->id)
            ->where('is_active', true)
            ->get();

        $this->info("Memulai sinkronisasi {$feeds->count()} sumber RSS untuk {$polda->name}...");

        $liveArticles = $rssService->fetchAggregatedFeeds($feeds->toArray(), true);
        $this->info("Berhasil mengambil " . count($liveArticles) . " artikel dari jaringan RSS.");

        $saved = 0;
        $withImages = 0;

        foreach ($liveArticles as $item) {
            $article = MediaArticle::updateOrCreate(
                ['url' => $item['link']],
                [
                    'polda_id'    => $polda->id,
                    'feed_id'     => $item['source_id'] ?: null,
                    'title'       => $item['title'],
                    'source_name' => strtoupper($item['source_name']),
                    'category'    => $item['category'],
                    'image_url'   => $item['image_url'] ?: null,
                    'summary'     => $item['summary'],
                    'pub_date'    => !empty($item['pub_date']) ? date('Y-m-d H:i:s', strtotime($item['pub_date'])) : now(),
                    'sentiment'   => $item['sentiment'] ?? 'netral',
                ]
            );

            $saved++;
            if (!empty($article->image_url)) {
                $withImages++;
            }
        }

        $this->info("Selesai! {$saved} artikel diperbarui ke database simod_enterprise ({$withImages} memiliki foto cover).");
        return 0;
    }
}

