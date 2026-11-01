<?php

namespace Database\Seeders;

use App\Models\MediaFeed;
use App\Models\Polda;
use Illuminate\Database\Seeder;

class DivhumasNationalRssFeedsSeeder extends Seeder
{
    /**
     * Seed 22 Feed RSS Media Online Nasional Terverifikasi untuk Divisi Humas Polri (Mabes).
     * Berdasarkan verifikasi live SIMOD_Enterprise_RSS_Media_Online_Nasional_2026-11-01.md
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
                'url'      => 'https://www.antaranews.com/rss/politik.xml',
                'category' => 'Politik',
            ],
            [
                'name'     => 'ANTARA Hukum & Peradilan',
                'url'      => 'https://www.antaranews.com/rss/hukum.xml',
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
                'url'      => 'https://www.antaranews.com/rss/ekonomi.xml',
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

