<?php

namespace Database\Seeders;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use Illuminate\Database\Seeder;

class MultiPoldaArticlesHistoricalSeeder extends Seeder
{
    public function run(): void
    {
        $poldas = Polda::where('is_active', true)->get();

        // 1. First, adjust existing SULBAR articles so they have realistic distributed publication dates
        $sulbar = $poldas->firstWhere('code', 'SULBAR');
        if ($sulbar) {
            $articles = MediaArticle::where('polda_id', $sulbar->id)->get();
            $total = $articles->count();

            if ($total > 0) {
                // Keep ~25% as recent (0-5 days)
                // ~25% as 10-25 days ago (> 7 days)
                // ~25% as 40-75 days ago (> 30 days)
                // ~25% as 100-240 days ago (> 90 days & > 180 days)
                foreach ($articles as $idx => $art) {
                    $ratio = $idx / $total;
                    if ($ratio < 0.25) {
                        $daysAgo = rand(0, 5);
                    } elseif ($ratio < 0.50) {
                        $daysAgo = rand(8, 25);
                    } elseif ($ratio < 0.75) {
                        $daysAgo = rand(35, 75);
                    } else {
                        $daysAgo = rand(95, 220);
                    }

                    $pubDate = now()->subDays($daysAgo)->subHours(rand(1, 23))->subMinutes(rand(1, 59));
                    $isBookmarked = ($idx % 12 === 0); // ~8% bookmarked

                    $art->update([
                        'pub_date'      => $pubDate,
                        'is_bookmarked' => $isBookmarked,
                    ]);
                }
            }
        }

        // 2. Generate realistic news articles for other Poldas (SULSEL, GORONTALO, SULTRA, KALTIM)
        $poldaNewsTemplates = [
            'SULSEL' => [
                'portal' => 'Tribun Timur',
                'category' => 'Regional Sulselbar',
                'headlines' => [
                    'Polda Sulsel Ungkap Kasus Penipuan Online Lintas Daerah di Makassar',
                    'Polrestabes Makassar Perketat Patroli Presisi di Titik Rawan Begal',
                    'Kapolda Sulsel Tinjau Posko Terpadu Operasi Mantap Praja di Maros',
                    'Polres Bone Amankan Ratusan Botol Miras Ilegal Jelang Akhir Pekan',
                    'Satlantas Polres Gowa Gelar Operasi Zebra Humanis Tertibkan Pengendara',
                    'Polda Sulsel Musnahkan Puluhan Kilogram Sabu Hasil Operasi Gabungan',
                    'Dirlantas Polda Sulsel Luncurkan ETLE Mobile Handheld di Wilayah Parepare',
                    'Polres Palopo Edukasi Pelajar Waspada Bahaya Narkoba dan Kenakalan Remaja',
                    'Bidhumas Polda Sulsel Imbau Masyarakat Cerdas Bermedsos Cegah Hoaks',
                    'Satgas Pangan Polda Sulsel Pantau Ketersediaan Beras dan Minyak Goreng',
                ],
            ],
            'GORONTALO' => [
                'portal' => 'Antara Gorontalo',
                'category' => 'Nasional & Umum',
                'headlines' => [
                    'Polda Gorontalo Salurkan Bantuan Sosial Bagi Korban Bencana Banjir',
                    'Polres Gorontalo Kota Tingkatkan Patroli Malam Cegah Balap Liar',
                    'Kapolda Gorontalo Resmikan Gedung Pelayanan Terpadu Satu Atap',
                    'Polda Gorontalo Gelar Simulasi Penanganan Unjuk Rasa Damai Pilkada',
                    'Satresnarkoba Polres Boalemo Tangkap Pengedar Obat Terlarang',
                    'Polres Bone Bolango Dampingi Kelompok Tani Dukung Ketahanan Pangan',
                ],
            ],
            'SULTRA' => [
                'portal' => 'Kendari Pos',
                'category' => 'Hukum & Kriminal',
                'headlines' => [
                    'Polda Sultra Amankan Puluhan Ton Solar Ilegal di Wilayah Kolaka',
                    'Polresta Kendari Gelar Jumat Curhat Dengarkan Keluhan Kamtibmas Warga',
                    'Polres Baubau Gagalkan Penyelundupan Rokok Ilegal via Jalur Laut',
                    'Ditpolairud Polda Sultra Patroli Kawasan Perairan Lindungi Nelayan',
                    'Kapolda Sultra Tekankan Netralitas Personel Dalam Pengamanan Pemilukada',
                    'Polres Konawe Bersama Warga Gotong Royong Perbaiki Jembatan Rusak',
                ],
            ],
            'KALTIM' => [
                'portal' => 'Kaltim Post',
                'category' => 'Nasional & Umum',
                'headlines' => [
                    'Polda Kaltim Perketat Pengamanan Jalur Logistik Pembangunan IKN Nusantara',
                    'Polresta Samarinda Tertibkan Tambang Ilegal di Wilayah Penyangga',
                    'Polres Balikpapan Gencarkan Patroli Siber Cegah Provokasi Medsos',
                    'Satlantas Polresta Balikpapan Terapkan Rekayasa Lalu Lintas di Titik Macet',
                    'Ditreskrimsus Polda Kaltim Tangkap Pelaku Ilegal Logging di Kutai Kartanegara',
                    'Polres Kutai Timur Kawal Penyaluran Pupuk Subsidi Tepat Sasaran',
                ],
            ],
        ];

        foreach ($poldas as $polda) {
            if ($polda->code === 'SULBAR') continue;

            $template = $poldaNewsTemplates[$polda->code] ?? null;
            if (!$template) continue;

            // Ensure feed exists
            $feed = MediaFeed::firstOrCreate(
                ['polda_id' => $polda->id, 'name' => $template['portal']],
                [
                    'category'  => $template['category'],
                    'url'       => 'https://' . strtolower(str_replace(' ', '', $template['portal'])) . '.com/rss',
                    'is_active' => true,
                ]
            );

            // Generate ~35-50 articles per Polda spread over time
            $count = rand(35, 55);
            for ($i = 0; $i < $count; $i++) {
                $headlineIndex = $i % count($template['headlines']);
                $headline = $template['headlines'][$headlineIndex] . ' (Edisi ' . ($i + 1) . ')';
                $slug = 'berita-' . strtolower($polda->code) . '-' . ($i + 1);

                // Date distribution:
                // 30% recent (1-6 days)
                // 30% medium (12-28 days) -> > 7 days
                // 20% older (45-80 days)  -> > 30 days
                // 20% past (95-200 days)  -> > 90 days & > 180 days
                if ($i < $count * 0.3) {
                    $daysAgo = rand(1, 6);
                } elseif ($i < $count * 0.6) {
                    $daysAgo = rand(8, 28);
                } elseif ($i < $count * 0.8) {
                    $daysAgo = rand(35, 80);
                } else {
                    $daysAgo = rand(95, 200);
                }

                $pubDate = now()->subDays($daysAgo)->subHours(rand(1, 23))->subMinutes(rand(1, 59));
                $isBookmarked = ($i === 2 || $i === 15); // Exactly 2 bookmarks per polda

                MediaArticle::updateOrCreate(
                    ['url' => "https://{$feed->name}.com/{$slug}"],
                    [
                        'polda_id'      => $polda->id,
                        'feed_id'       => $feed->id,
                        'title'         => $headline,
                        'source_name'   => strtoupper($template['portal']),
                        'category'      => $template['category'],
                        'image_url'     => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&auto=format&fit=crop&q=80',
                        'summary'       => "Informasi pemantauan intelijen media terkait dinamika kamtibmas wilayah hukum {$polda->name}.",
                        'pub_date'      => $pubDate,
                        'sentiment'     => ($i % 3 === 0) ? 'positif' : (($i % 4 === 0) ? 'netral' : 'positif'),
                        'is_bookmarked' => $isBookmarked,
                    ]
                );
            }
        }
    }
}

