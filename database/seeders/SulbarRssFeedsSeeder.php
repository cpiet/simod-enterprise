<?php

namespace Database\Seeders;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use Illuminate\Database\Seeder;

class SulbarRssFeedsSeeder extends Seeder
{
    /**
     * Seed 29 Sumber Feed RSS Resmi Terorganisir (Sulselbar & Nasional)
     * Sesuai instruksi dan pengelompokan kategori SIMOD HUMAS Polda Sulbar.
     */
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (! $sulbar) {
            return;
        }

        $sources = [
            // =========================================================================
            // 1. REGIONAL SULSELBAR (SULAWESI SELATAN & BARAT) - 20 PORTAL
            // =========================================================================
            [
                'name'     => 'Detik Sulsel',
                'url'      => 'https://www.detik.com/sulsel/rss',
                'category' => 'Regional Sulselbar',
            ],
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
                'name'     => 'Bacasaja.id',
                'url'      => 'https://bacasaja.id/feed/',
                'category' => 'Regional Sulselbar',
            ],

            // =========================================================================
            // 2. NASIONAL & UMUM - 4 PORTAL
            // =========================================================================
            [
                'name'     => 'Antara News Terkini',
                'url'      => 'https://www.antaranews.com/rss/terkini.xml',
                'category' => 'Nasional & Umum',
            ],
            [
                'name'     => 'CNN Indonesia Nasional',
                'url'      => 'https://www.cnnindonesia.com/nasional/rss',
                'category' => 'Nasional & Umum',
            ],
            [
                'name'     => 'Tribunnews (Nasional)',
                'url'      => 'https://www.tribunnews.com/rss',
                'category' => 'Nasional & Umum',
            ],
            [
                'name'     => 'ANTARA Makassar (Politik)',
                'url'      => 'https://makassar.antaranews.com/rss/politik.xml',
                'category' => 'Nasional & Umum',
            ],

            // =========================================================================
            // 3. HUKUM & KRIMINAL - 3 PORTAL
            // =========================================================================
            [
                'name'     => 'Detikcom Hukum & Kriminal',
                'url'      => 'https://news.detik.com/berita/rss',
                'category' => 'Hukum & Kriminal',
            ],
            [
                'name'     => 'ANTARA Makassar (Hukum)',
                'url'      => 'https://makassar.antaranews.com/rss/hukum.xml',
                'category' => 'Hukum & Kriminal',
            ],
            [
                'name'     => 'Polisiku.co.id',
                'url'      => 'https://polisiku.co.id/feed/',
                'category' => 'Hukum & Kriminal',
            ],

            // =========================================================================
            // 4. GOOGLE NEWS RSS - 2 STREAM
            // =========================================================================
            [
                'name'     => 'iNews Mamuju (Keyword Search)',
                'url'      => 'https://news.google.com/rss/search?q=inews+mamuju&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - iNews Mamuju (Site Search)',
                'url'      => 'https://news.google.com/rss/search?q=site:mamuju.inews.id&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
        ];

        // 1. Sync / Update atau Buat 29 Feed Resmi
        $validUrls = [];
        $validNames = [];
        foreach ($sources as $src) {
            $validUrls[] = $src['url'];
            $validNames[] = strtoupper($src['name']);

            $feed = MediaFeed::updateOrCreate(
                ['polda_id' => $sulbar->id, 'url' => $src['url']],
                [
                    'name'      => $src['name'],
                    'category'  => $src['category'],
                    'is_active' => true,
                ]
            );

            // Update nama sumber dan kategori pada artikel yang terhubung
            MediaArticle::where('feed_id', $feed->id)
                ->update([
                    'source_name' => $src['name'],
                    'category'    => $src['category'],
                ]);
        }

        // 2. Buang semua feed di luar 29 daftar resmi khusus Sulbar
        MediaFeed::where('polda_id', $sulbar->id)
            ->whereNotIn('url', $validUrls)
            ->delete();

        // 3. Buang semua artikel di luar 29 feed resmi khusus Sulbar
        $validFeedIds = MediaFeed::where('polda_id', $sulbar->id)->pluck('id');
        MediaArticle::where('polda_id', $sulbar->id)
            ->whereNotIn('feed_id', $validFeedIds)
            ->delete();
    }
}
