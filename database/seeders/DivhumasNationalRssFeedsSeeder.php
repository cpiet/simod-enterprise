<?php

namespace Database\Seeders;

use App\Models\MediaFeed;
use App\Models\Polda;
use Illuminate\Database\Seeder;

class DivhumasNationalRssFeedsSeeder extends Seeder
{
    /**
     * Seed 33 Feed RSS Media Online Nasional Terverifikasi untuk Divisi Humas Polri (Mabes).
     * Termasuk media arus utama (Antara, Detik, CNN, CNBC, Tempo, Republika, Liputan6, SINDOnews, JPNN, Kumparan, BBC)
     * dan sindikasi multi-topik terbuka Google News Indonesia.
     */
    public function run(): void
    {
        $divhumas = Polda::firstOrCreate(
            ['code' => 'DIVHUMAS'],
            [
                'name'             => 'Divisi Humas Polri (Mabes)',
                'region'           => 'Nasional / Mabes Polri',
                'timezone'         => 'Asia/Jakarta',
                'kapolda_name'     => 'Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.',
                'kabid_humas_name' => 'Brigjen Pol. Trunoyudo Wisnu Andiko, S.I.K.',
                'is_active'        => true,
            ]
        );

        $feeds = [
            // 1. ANTARA News Network (Kantor Berita Negara - Terverifikasi 100% Gambar)
            [
                'name'     => 'ANTARA News Terkini',
                'url'      => 'https://www.antaranews.com/rss/terkini.xml',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'ANTARA Top News',
                'url'      => 'https://www.antaranews.com/rss/top-news.xml',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'ANTARA Politik & Pemerintahan',
                'url'      => 'https://news.google.com/rss/search?q=site:antaranews.com+politik&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Politik',
            ],
            [
                'name'     => 'ANTARA Hukum & Peradilan',
                'url'      => 'https://news.google.com/rss/search?q=site:antaranews.com+hukum&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Hukum & Kriminal',
            ],
            [
                'name'     => 'ANTARA Metro Kriminalitas',
                'url'      => 'https://www.antaranews.com/rss/metro-kriminalitas.xml',
                'category' => 'Hukum & Kriminal',
            ],
            [
                'name'     => 'ANTARA Metro Ibukota',
                'url'      => 'https://www.antaranews.com/rss/metro.xml',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'ANTARA Ekonomi & Bisnis',
                'url'      => 'https://news.google.com/rss/search?q=site:antaranews.com+ekonomi&hl=id&gl=ID&ceid=ID:id',
                'category' => 'Ekonomi',
            ],

            // 2. Detikcom Network (Terverifikasi 100% Gambar)
            [
                'name'     => 'Detikcom Berita Nasional',
                'url'      => 'https://news.detik.com/berita/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'Detikcom Finance',
                'url'      => 'https://finance.detik.com/rss',
                'category' => 'Ekonomi',
            ],
            [
                'name'     => 'Detikcom Inet & Siber',
                'url'      => 'https://inet.detik.com/rss',
                'category' => 'Teknologi',
            ],

            // 3. CNN Indonesia (Terverifikasi 100% Gambar)
            [
                'name'     => 'CNN Indonesia Nasional',
                'url'      => 'https://www.cnnindonesia.com/nasional/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'CNN Indonesia Ekonomi',
                'url'      => 'https://www.cnnindonesia.com/ekonomi/rss',
                'category' => 'Ekonomi',
            ],
            [
                'name'     => 'CNN Indonesia Teknologi & Siber',
                'url'      => 'https://www.cnnindonesia.com/teknologi/rss',
                'category' => 'Teknologi',
            ],

            // 4. CNBC Indonesia (Terverifikasi 100% Gambar)
            [
                'name'     => 'CNBC Indonesia News',
                'url'      => 'https://www.cnbcindonesia.com/news/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'CNBC Indonesia Market & Finansial',
                'url'      => 'https://www.cnbcindonesia.com/market/rss',
                'category' => 'Ekonomi',
            ],

            // 5. Media Nasional Terverifikasi Lainnya (100% Gambar)
            [
                'name'     => 'Republika Terkini',
                'url'      => 'https://www.republika.co.id/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'Liputan6 News & Peristiwa',
                'url'      => 'https://feed.liputan6.com/rss/news',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'SINDOnews Nasional',
                'url'      => 'https://nasional.sindonews.com/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'SINDOnews Daerah & Satwil',
                'url'      => 'https://daerah.sindonews.com/rss',
                'category' => 'Regional',
            ],
            [
                'name'     => 'JPNN Berita Terkini',
                'url'      => 'https://www.jpnn.com/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'Kumparan News',
                'url'      => 'https://lapi.kumparan.com/v2.0/rss/',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'BBC News Indonesia',
                'url'      => 'https://feeds.bbci.co.uk/indonesia/rss.xml',
                'category' => 'Nasional',
            ],

            // 6. Tempo.co Network (Investigatif & Kebijakan Publik)
            [
                'name'     => 'Tempo.co Utama',
                'url'      => 'https://rss.tempo.co/',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'Tempo.co Nasional',
                'url'      => 'https://rss.tempo.co/nasional',
                'category' => 'Nasional',
            ],

            // 7. Feed Utama Tambahan CNN & CNBC
            [
                'name'     => 'CNN Indonesia Utama',
                'url'      => 'https://www.cnnindonesia.com/rss',
                'category' => 'Nasional',
            ],
            [
                'name'     => 'CNBC Indonesia Terkini',
                'url'      => 'https://www.cnbcindonesia.com/rss',
                'category' => 'Ekonomi',
            ],

            // 8. Google News RSS Multi-Topik Indonesia
            [
                'name'     => 'Google News - Berita Utama',
                'url'      => 'https://news.google.com/rss?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - Bisnis & Ekonomi',
                'url'      => 'https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - Teknologi & Siber',
                'url'      => 'https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - Hiburan & Budaya',
                'url'      => 'https://news.google.com/rss/headlines/section/topic/ENTERTAINMENT?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - Olahraga',
                'url'      => 'https://news.google.com/rss/headlines/section/topic/SPORTS?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - Sains & Lingkungan',
                'url'      => 'https://news.google.com/rss/headlines/section/topic/SCIENCE?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
            [
                'name'     => 'Google News - Kesehatan',
                'url'      => 'https://news.google.com/rss/headlines/section/topic/HEALTH?hl=id&gl=ID&ceid=ID:id',
                'category' => 'Google News RSS',
            ],
        ];

        foreach ($feeds as $feed) {
            MediaFeed::updateOrCreate(
                [
                    'polda_id' => $divhumas->id,
                    'url'      => $feed['url'],
                ],
                [
                    'name'      => $feed['name'],
                    'category'  => $feed['category'],
                    'is_active' => true,
                ]
            );
        }
    }
}

