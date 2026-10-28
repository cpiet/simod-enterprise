<?php

namespace Database\Seeders;

use App\Models\MediaFeed;
use App\Models\Polda;
use Illuminate\Database\Seeder;

class SulbarRssFeedsSeeder extends Seeder
{
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (! $sulbar) {
            return;
        }

        $sources = [
            // Portal Lokal & Regional Sulbar (Identik dengan SIMOD-HUMAS)
            ['name' => 'Detik Sulsel', 'url' => 'https://www.detik.com/sulsel/rss', 'category' => 'Regional Sulselbar'],
            ['name' => 'Mamuju Pos', 'url' => 'https://mamujupos.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Katinting', 'url' => 'https://katinting.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Kabar Sulbar', 'url' => 'https://kabarsulbar.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Suwarta', 'url' => 'https://suwarta.id/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Sulbar Terkini', 'url' => 'https://sulbarterkini.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Malaqbi', 'url' => 'https://malaqbi.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Rakyat Sulbar', 'url' => 'https://www.rakyatsulbar.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'iNews Mamuju (Google News RSS)', 'url' => 'https://news.google.com/rss/search?q=inews+mamuju&hl=id&gl=ID&ceid=ID:id', 'category' => 'Regional Sulselbar'],
            ['name' => 'Bugis Pos', 'url' => 'https://bugispos.com/feed/', 'category' => 'Regional Sulselbar'],
            ['name' => 'Google News Polda Sulbar', 'url' => 'https://news.google.com/rss/search?q=polda+sulbar&hl=id&gl=ID&ceid=ID:id', 'category' => 'Harkamtibmas'],

            // Portal Nasional Utama
            ['name' => 'Antara News Terkini', 'url' => 'https://www.antaranews.com/rss/terkini.xml', 'category' => 'Nasional'],
            ['name' => 'CNN Indonesia Nasional', 'url' => 'https://www.cnnindonesia.com/nasional/rss', 'category' => 'Nasional'],
            ['name' => 'Detikcom Hukum & Kriminal', 'url' => 'https://news.detik.com/berita/rss', 'category' => 'Hukum & Kriminal'],
        ];

        foreach ($sources as $src) {
            MediaFeed::updateOrCreate(
                ['polda_id' => $sulbar->id, 'url' => $src['url']],
                [
                    'name'      => $src['name'],
                    'category'  => $src['category'],
                    'is_active' => true,
                ]
            );
        }
    }
}
