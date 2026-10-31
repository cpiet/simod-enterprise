<?php

namespace Database\Seeders;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use Illuminate\Database\Seeder;

class SocialIntelligenceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (!$sulbar) {
            return;
        }

        $polresMamuju = Polres::where('code', 'POLRESTA_MAMUJU')->first();
        $polresMajene = Polres::where('code', 'POLRES_MAJENE')->first();
        $polresPolman = Polres::where('code', 'POLRES_POLMAN')->first();
        $polresMamasa = Polres::where('code', 'POLRES_MAMASA')->first();
        $polresPasangkayu = Polres::where('code', 'POLRES_PASANGKAYU')->first();

        // 1. Social Settings default
        SocialSetting::updateOrCreate(
            ['polda_id' => $sulbar->id],
            [
                'apify_api_token'   => null,
                'keywords_tracked'  => 'Polda Sulbar, Polresta Mamuju, Polisi Sulbar, Polres Majene, Polres Polman, Lantas Sulbar, Dumas Presisi Mamuju',
                'sync_frequency'    => 'every_3_hours',
                'auto_sync_enabled' => true,
                'last_synced_at'    => now()->subMinutes(25),
            ]
        );

        // 2. Realistic Social Posts dataset
        $posts = [
            // 𝕏 (Twitter) - Tokoh Kritis / KOL
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMamuju?->id,
                'platform'        => 'x',
                'author_name'     => 'Dr. Arifin, S.H., M.H.',
                'author_handle'   => '@arifin_law_sulbar',
                'is_kol'          => true,
                'kol_category'    => 'Pengamat Hukum & Advokat',
                'post_id'         => 'x_1852001',
                'content'         => 'Langkah Ditreskrimsus Polda Sulbar mengedepankan Restorative Justice dalam perkara sengketa informasi publik di Mamuju patut diapresiasi. Penegakan hukum modern memang harus menitikberatkan pada keadilan substantif dan kondusifitas daerah, bukan sekadar pemidanaan kaku. #HukumPresisi #PoldaSulbar',
                'post_url'        => 'https://x.com/arifin_law_sulbar/status/1852001',
                'likes_count'     => 1420,
                'shares_count'    => 380,
                'comments_count'  => 85,
                'views_count'     => 28500,
                'sentiment'       => 'positif',
                'virality_score'  => 78,
                'is_bookmarked'   => true,
                'posted_at'       => now()->subHours(2),
            ],
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMamuju?->id,
                'platform'        => 'x',
                'author_name'     => 'Aliansi Warga Mamuju Kritis',
                'author_handle'   => '@suara_mamuju',
                'is_kol'          => true,
                'kol_category'    => 'Akun Warga / Opini Publik',
                'post_id'         => 'x_1852002',
                'content'         => 'Mohon atensi Pak Kapolda @PoldaSulbar dan Kapolresta Mamuju, antrean solar di SPBU Simbuang siang ini mengular sampai menutup setengah badan jalan utama. Rawan kecelakaan dan bikin macet parah. Butuh pengaturan personel lantas di lokasi! 🚨🛵',
                'post_url'        => 'https://x.com/suara_mamuju/status/1852002',
                'likes_count'     => 3250,
                'shares_count'    => 890,
                'comments_count'  => 312,
                'views_count'     => 64000,
                'sentiment'       => 'negatif',
                'virality_score'  => 88,
                'is_bookmarked'   => true,
                'posted_at'       => now()->subHours(4),
            ],
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresPolman?->id,
                'platform'        => 'x',
                'author_name'     => 'Khabar Polman Update',
                'author_handle'   => '@polman_update',
                'is_kol'          => false,
                'kol_category'    => 'Akun Komunitas Daerah',
                'post_id'         => 'x_1852003',
                'content'         => 'Operasi tertib knalpot brong jajaran Satlantas Polres Polman di Wonomulyo malam minggu kemarin bikin warga tidur nyenyak. Salut dan terima kasih pak polisi, tolong dirutinkan tiap akhir pekan! 👍👏',
                'post_url'        => 'https://x.com/polman_update/status/1852003',
                'likes_count'     => 920,
                'shares_count'    => 140,
                'comments_count'  => 45,
                'views_count'     => 15600,
                'sentiment'       => 'positif',
                'virality_score'  => 62,
                'is_bookmarked'   => false,
                'posted_at'       => now()->subHours(6),
            ],

            // 🎵 TikTok - Video Viral & Edukasi
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMamuju?->id,
                'platform'        => 'tiktok',
                'author_name'     => 'Bripka Fadly (Sahabat Warga)',
                'author_handle'   => '@fadly_humas_marano',
                'is_kol'          => true,
                'kol_category'    => 'Polisi Influencer / Edukasi',
                'post_id'         => 'tt_987601',
                'content'         => 'Aksi humanis Bhabinkamtibmas Polresta Mamuju membantu seberangkan anak-anak sekolah dan warga lansia di tengah cuaca hujan deras. Melayani dengan hati, Polri hadir untuk masyarakat! ❤️🇮🇩 #PolriPresisi #PoldaSulbar #PolisiHumanis',
                'post_url'        => 'https://tiktok.com/@fadly_humas_marano/video/987601',
                'thumbnail_url'   => 'https://images.unsplash.com/photo-1544717305-2782549b5136?w=600&auto=format&fit=crop&q=80',
                'likes_count'     => 48200,
                'shares_count'    => 6300,
                'comments_count'  => 1450,
                'views_count'     => 520000,
                'sentiment'       => 'positif',
                'virality_score'  => 94,
                'is_bookmarked'   => true,
                'posted_at'       => now()->subHours(8),
            ],
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMajene?->id,
                'platform'        => 'tiktok',
                'author_name'     => 'Info Majene Viral',
                'author_handle'   => '@infomajene_live',
                'is_kol'          => false,
                'kol_category'    => 'Akun Warga / Opini Publik',
                'post_id'         => 'tt_987602',
                'content'         => 'Detik-detik evakuasi material longsor batu besar di Jalur Trans Sulawesi Somba - Tubo oleh personel Samapta Polres Majene bersama warga. Arus lalu lintas sempat lumpuh 2 jam kini sudah mulai bisa dilewati roda dua. Tetap waspada cuaca ekstrem lur!',
                'post_url'        => 'https://tiktok.com/@infomajene_live/video/987602',
                'thumbnail_url'   => 'https://images.unsplash.com/photo-1509316975850-ff9c5deb0cd9?w=600&auto=format&fit=crop&q=80',
                'likes_count'     => 18900,
                'shares_count'    => 2400,
                'comments_count'  => 480,
                'views_count'     => 210000,
                'sentiment'       => 'netral',
                'virality_score'  => 84,
                'is_bookmarked'   => false,
                'posted_at'       => now()->subHours(10),
            ],

            // 📸 Instagram - Feed & Story Dokumentasi
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMamuju?->id,
                'platform'        => 'instagram',
                'author_name'     => 'Radar Sulbar Official',
                'author_handle'   => '@radarsulbar_online',
                'is_kol'          => true,
                'kol_category'    => 'Media Massa / Jurnalis',
                'post_id'         => 'ig_554401',
                'content'         => 'Kapolda Sulbar Irjen Pol Adang Ginanjar pimpin Apel Gelar Pasukan Kesiapsiagaan Pilkada Damai 2024 di Lapangan Maradika Mamuju. Sebanyak 1.500 personel gabungan TNI-Polri disiagakan untuk menjamin kamtibmas tetap kondusif di 6 kabupaten.',
                'post_url'        => 'https://instagram.com/p/Cxy9876Z',
                'thumbnail_url'   => 'https://images.unsplash.com/photo-1579783900882-c0d3dad7b119?w=600&auto=format&fit=crop&q=80',
                'likes_count'     => 3400,
                'shares_count'    => 410,
                'comments_count'  => 115,
                'views_count'     => 42000,
                'sentiment'       => 'positif',
                'virality_score'  => 72,
                'is_bookmarked'   => false,
                'posted_at'       => now()->subHours(12),
            ],
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresPasangkayu?->id,
                'platform'        => 'instagram',
                'author_name'     => 'LBH Keadilan Rakyat Mandar',
                'author_handle'   => '@lbh_mandar',
                'is_kol'          => true,
                'kol_category'    => 'LBH & Pembela HAM',
                'post_id'         => 'ig_554402',
                'content'         => 'Pernyataan Sikap: Kami mengawal penanganan konflik batas lahan warga dengan perusahaan sawit di Pasangkayu. Kami meminta aparat kepolisian mengedepankan pendekatan dialogis dan netralitas penegakan hukum.',
                'post_url'        => 'https://instagram.com/p/Cxy1234A',
                'thumbnail_url'   => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&auto=format&fit=crop&q=80',
                'likes_count'     => 1280,
                'shares_count'    => 290,
                'comments_count'  => 78,
                'views_count'     => 19400,
                'sentiment'       => 'netral',
                'virality_score'  => 68,
                'is_bookmarked'   => true,
                'posted_at'       => now()->subHours(14),
            ],

            // ▶️ YouTube - Liputan & Podcast
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMamuju?->id,
                'platform'        => 'youtube',
                'author_name'     => 'Tribratanews Polda Sulbar Channel',
                'author_handle'   => '@PoldaSulbarOfficial',
                'is_kol'          => false,
                'kol_category'    => 'Kanal Resmi Kedinasan',
                'post_id'         => 'yt_112201',
                'content'         => 'DIALOG KHUSUS KABID HUMAS: Strategi Pengamanan Siber & Mitigasi Hoaks Pilkada Serentak Sulawesi Barat Bersama Bawaslu dan Akademisi Unsulbar.',
                'post_url'        => 'https://youtube.com/watch?v=sulbar_cyber_2026',
                'thumbnail_url'   => 'https://images.unsplash.com/photo-1598488035139-bdbb2231ce04?w=600&auto=format&fit=crop&q=80',
                'likes_count'     => 850,
                'shares_count'    => 190,
                'comments_count'  => 62,
                'views_count'     => 12800,
                'sentiment'       => 'positif',
                'virality_score'  => 55,
                'is_bookmarked'   => false,
                'posted_at'       => now()->subHours(16),
            ],
            [
                'polda_id'        => $sulbar->id,
                'polres_id'       => $polresMamasa?->id,
                'platform'        => 'youtube',
                'author_name'     => 'Ekspedisi Nusantara Mamasa',
                'author_handle'   => '@jejak_mamasa_doc',
                'is_kol'          => true,
                'kol_category'    => 'Kreator Dokumenter Independen',
                'post_id'         => 'yt_112202',
                'content'         => 'Perjuangan Polisi Pelosok: Menembus Jalur Berlumpur Pegunungan Gandangdewata Demi Layanan Kesehatan Warga Dusun Terisolir.',
                'post_url'        => 'https://youtube.com/watch?v=mamasa_polisi_desa',
                'thumbnail_url'   => 'https://images.unsplash.com/photo-1464822759023-fed622ff2c3b?w=600&auto=format&fit=crop&q=80',
                'likes_count'     => 5400,
                'shares_count'    => 1100,
                'comments_count'  => 380,
                'views_count'     => 89000,
                'sentiment'       => 'positif',
                'virality_score'  => 82,
                'is_bookmarked'   => true,
                'posted_at'       => now()->subHours(18),
            ],
        ];

        foreach ($posts as $postData) {
            SocialPost::updateOrCreate(
                [
                    'polda_id' => $postData['polda_id'],
                    'post_id'  => $postData['post_id'],
                ],
                $postData
            );
        }
    }
}
