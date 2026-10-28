<?php

namespace Database\Seeders;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use Illuminate\Database\Seeder;

class SulbarRssFeedsSeeder extends Seeder
{
    /**
     * Seed HANYA 29 Sumber Feed RSS Resmi Wilayah Sulbar & Nasional (Sesuai Permintaan Klien SIMOD HUMAS)
     */
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (! $sulbar) {
            return;
        }

        $sources = [
            // 1-4. Portal Utama Nasional & Regional
            [
                'name'     => 'Detik Sulsel',
                'url'      => 'https://www.detik.com/sulsel/rss',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Antara News Terkini',
                'url'      => 'https://www.antaranews.com/rss/terkini.xml',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'CNN Indonesia Nasional',
                'url'      => 'https://www.cnnindonesia.com/nasional/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'Detikcom Hukum & Kriminal',
                'url'      => 'https://news.detik.com/berita/rss',
                'category' => 'Hukum & Kriminal',
            ],

            // 5-12. Jaringan ANTARA Regional & Tribun Sulbar/Nasional
            [
                'name'     => 'Tribun Sulbar',
                'url'      => 'https://sulbar.tribunnews.com/rss',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'ANTARA Makassar (Terkini)',
                'url'      => 'https://makassar.antaranews.com/rss/terkini.xml',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'ANTARA Makassar (Hukum)',
                'url'      => 'https://makassar.antaranews.com/rss/hukum.xml',
                'category' => 'Kriminal / Hukum',
            ],
            [
                'name'     => 'ANTARA Makassar (Top News)',
                'url'      => 'https://makassar.antaranews.com/rss/top-news.xml',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'ANTARA Makassar (Daerah)',
                'url'      => 'https://makassar.antaranews.com/rss/daerah.xml',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'ANTARA Makassar (Lintas Daerah)',
                'url'      => 'https://makassar.antaranews.com/rss/lintas-daerah.xml',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'ANTARA Makassar (Politik)',
                'url'      => 'https://makassar.antaranews.com/rss/politik.xml',
                'category' => 'Umum',
            ],
            [
                'name'     => 'Tribunnews (Nasional)',
                'url'      => 'https://www.tribunnews.com/rss',
                'category' => 'Nasional',
            ],

            // 13-25. Media Daring Lokal & Daerah Sulawesi Barat
            [
                'name'     => 'Mesakada.com',
                'url'      => 'https://mesakada.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Mekora.id',
                'url'      => 'https://mekora.id/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Globalsulbar',
                'url'      => 'https://globalsulbar.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Radar Sulbar',
                'url'      => 'https://radarsulbar.fajar.co.id/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Sulbar Express',
                'url'      => 'https://sulbarexpress.fajar.co.id/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Mamuju Pos',
                'url'      => 'https://mamujupos.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Katinting',
                'url'      => 'https://katinting.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Kabar Sulbar',
                'url'      => 'https://kabarsulbar.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Suwarta',
                'url'      => 'https://suwarta.id/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Sulbar Terkini',
                'url'      => 'https://sulbarterkini.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Malaqbi',
                'url'      => 'https://malaqbi.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Rakyat Sulbar',
                'url'      => 'https://www.rakyatsulbar.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Bugis Pos',
                'url'      => 'https://bugispos.com/feed/',
                'category' => 'Regional Sulselbar',
            ],
            [
                'name'     => 'Polisiku.co.id',
                'url'      => 'https://polisiku.co.id/feed/',
                'category' => 'Hukum & Kriminal',
            ],
            [
                'name'     => 'Bacasaja.id',
                'url'      => 'https://bacasaja.id/feed/',
                'category' => 'Regional Sulselbar',
            ],

            // 26-27. Google News RSS Monitoring Khusus Media Mamuju
            [
                'name'     => 'iNews Mamuju (Google News RSS)',
                'url'      => 'https://news.google.com/rss/search?q=inews+mamuju&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News',
            ],
            [
                'name'     => 'Google News - iNews Mamuju',
                'url'      => 'https://news.google.com/rss/search?q=site:mamuju.inews.id&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News',
            ],
        ];

        // 1. Sync / Update atau Buat 29 Feed Resmi
        $validUrls = [];
        $validNames = [];
        foreach ($sources as $src) {
            $validUrls[] = $src['url'];
            $validNames[] = strtoupper($src['name']);

            MediaFeed::updateOrCreate(
                ['polda_id' => $sulbar->id, 'url' => $src['url']],
                [
                    'name'      => $src['name'],
                    'category'  => $src['category'],
                    'is_active' => true,
                ]
            );
        }

        // 2. Buang semua feed di luar 29 daftar resmi khusus Sulbar
        MediaFeed::where('polda_id', $sulbar->id)
            ->whereNotIn('url', $validUrls)
            ->delete();

        // 3. Buang semua artikel di luar 29 portal resmi khusus Sulbar
        MediaArticle::where('polda_id', $sulbar->id)
            ->whereNotIn('source_name', $validNames)
            ->delete();
    }
}
