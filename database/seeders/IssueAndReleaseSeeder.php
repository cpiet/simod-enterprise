<?php

namespace Database\Seeders;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IssueAndReleaseSeeder extends Seeder
{
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        $gorontalo = Polda::where('code', 'GORONTALO')->first();
        $sulbarUser = User::where('role', 'admin_polda')->where('polda_id', $sulbar?->id)->first();
        $mamujuPolres = Polres::where('code', 'MAMUJU')->first();
        $kotaGorontalo = Polres::where('code', 'KOTA_GORONTALO')->first();

        // 1. SEED ISSUES UNTUK POLDA SULBAR
        if ($sulbar) {
            DB::table('issues')->insert([
                [
                    'polda_id'               => $sulbar->id,
                    'polres_id'              => $mamujuPolres?->id,
                    'created_by'             => $sulbarUser?->id,
                    'title'                  => 'Viral Narasi Kenaikan Tarif Parkir Liar di Kawasan Pantai Manakarra',
                    'slug'                   => 'viral-narasi-tarif-parkir-pantai-manakarra',
                    'summary'                => 'Beredar video di TikTok dan grup WhatsApp warga Mamuju yang mengeluhkan oknum juru parkir liar mematok tarif Rp 10.000 di area publik Manakarra menjelang akhir pekan.',
                    'category'               => 'Pelayanan Publik',
                    'priority'               => 'medium',
                    'status'                 => 'investigasi',
                    'sentiment'              => 'negatif',
                    'source_media'           => 'TikTok & WhatsApp',
                    'source_url'             => 'https://tiktok.com/@wargamamuju/video/7391823912',
                    'virality_score'         => 78,
                    'engagement_count'       => 14200,
                    'assigned_unit'          => 'Satreskrim Polresta Mamuju & Satpol PP',
                    'is_hoax'                => false,
                    'fact_check_result'      => 'Benar terjadi adanya pungutan liar oleh oknum non-resmi pada Sabtu malam. Polresta Mamuju telah mengamankan 2 orang oknum untuk pembinaan.',
                    'official_clarification' => 'Polresta Mamuju bersama Dishub telah menertibkan jukir liar di kawasan Pantai Manakarra. Masyarakat diimbau melapor ke call center 110 bila menemukan pungli.',
                    'clarification_url'      => 'https://humas.polri.go.id/sulbar/penertiban-pantai-manakarra',
                    'clarified_at'           => now()->subHours(3),
                    'created_at'             => now()->subHours(6),
                    'updated_at'             => now()->subHours(2),
                ],
                [
                    'polda_id'               => $sulbar->id,
                    'polres_id'              => $mamujuPolres?->id,
                    'created_by'             => $sulbarUser?->id,
                    'title'                  => 'Hoaks Isu Begal di Jalan Trans Sulawesi Jalur Tarailu - Mamuju Tengah',
                    'slug'                   => 'hoaks-isu-begal-tarailu-mateng',
                    'summary'                => 'Pesan berantai voice note dan tangkapan layar mengklaim adanya komplotan begal bersenjata tajam yang beraksi pada malam hari di jalur kebun sawit Tarailu.',
                    'category'               => 'Harkamtibmas',
                    'priority'               => 'high',
                    'status'                 => 'klarifikasi',
                    'sentiment'              => 'negatif',
                    'source_media'           => 'Facebook & WhatsApp Group',
                    'source_url'             => 'https://facebook.com/groups/infomamujuterkini/posts/1029381',
                    'virality_score'         => 91,
                    'engagement_count'       => 28500,
                    'assigned_unit'          => 'Ditreskrimum & Subdit Siber Ditreskrimsus',
                    'is_hoax'                => true,
                    'fact_check_result'      => 'Klarifikasi Tim Siber: Voice note tersebut adalah rekaman kejadian tahun 2021 di provinsi lain yang disebarkan ulang dengan narasi menyesatkan.',
                    'official_clarification' => '[FAKTA ATAU HOAKS] Bidhumas Polda Sulbar menegaskan informasi begal di Tarailu adalah HOAKS. Jalur trans Sulawesi aman dan rutin dipatroli personel Samapta.',
                    'clarification_url'      => 'https://humas.polri.go.id/sulbar/hoaks-begal-tarailu',
                    'clarified_at'           => now()->subHours(1),
                    'created_at'             => now()->subHours(4),
                    'updated_at'             => now()->subHours(1),
                ],
                [
                    'polda_id'               => $sulbar->id,
                    'polres_id'              => null,
                    'created_by'             => $sulbarUser?->id,
                    'title'                  => 'Apresiasi Publik: Pengawalan Distribusi Logistik Pemilu ke Daerah Terpencil Pulau Balabalakang',
                    'slug'                   => 'apresiasi-publik-pengawalan-balabalakang',
                    'summary'                => 'Liputan media nasional dan postingan positif netizen mengapresiasi personel Polairud Polda Sulbar yang menembus ombak besar mengantar kotak suara dan bantuan logistik.',
                    'category'               => 'Harkamtibmas',
                    'priority'               => 'low',
                    'status'                 => 'selesai',
                    'sentiment'              => 'positif',
                    'source_media'           => 'Detikcom, Kompas, Instagram',
                    'source_url'             => 'https://detik.com/sulsel/berita/polairud-sulbar-balabalakang',
                    'virality_score'         => 85,
                    'engagement_count'       => 45000,
                    'assigned_unit'          => 'Ditpolairud Polda Sulbar',
                    'is_hoax'                => false,
                    'fact_check_result'      => null,
                    'official_clarification' => null,
                    'clarification_url'      => null,
                    'clarified_at'           => null,
                    'created_at'             => now()->subDays(1),
                    'updated_at'             => now()->subHours(8),
                ],
            ]);

            // SEED PRESS RELEASES SULBAR
            DB::table('press_releases')->insert([
                [
                    'polda_id'           => $sulbar->id,
                    'polres_id'          => null,
                    'author_id'          => $sulbarUser?->id,
                    'title'              => 'Siaran Pers: Polda Sulbar Siagakan 1.200 Personel Amankan Rangkaian Pilkada Serentak 2026',
                    'press_number'       => 'B/SP-18/X/HUM.6.1.1/2026/Bidhumas',
                    'content'            => "MAMUJU — Kepolisian Daerah Sulawesi Barat menyiagakan 1.200 personel gabungan dalam rangka pengamanan tahapan kampanye dan pemungutan suara Pilkada Serentak 2026. Kapolda Sulbar menekankan pentingnya netralitas Polri, profesionalisme dalam pengawalan logistik, serta respons cepat patroli siber terhadap ujaran kebencian di media sosial.\n\nBidhumas Polda Sulbar mengajak seluruh lapisan masyarakat dan insan pers untuk bersama-sama menjaga kondusifitas kamtibmas demi terwujudnya pemilu damai dan berintegritas.",
                    'talking_points'     => json_encode([
                        'Polri menjamin 100% netralitas dalam seluruh tahapan Pilkada 2026.',
                        'Personel disebar ke seluruh TPS rawan dan pulau terluar Kepulauan Balabalakang.',
                        'Patroli siber Bidhumas dan Ditreskrimsus aktif 24 jam memitigasi narasi provokatif.',
                    ]),
                    'dos_and_donts'      => json_encode([
                        'DO: Tekankan kesiapan pengamanan, netralitas personel, dan nomor darurat 110.',
                        'DONT: Jangan mengomentari dinamika politik pasangan calon atau survei elektabilitas.',
                    ]),
                    'status'             => 'published',
                    'spokesperson_name'  => 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
                    'published_at'       => now()->subHours(5),
                    'created_at'         => now()->subHours(5),
                    'updated_at'         => now()->subHours(5),
                ],
            ]);
        }

        // 2. SEED ISSUES UNTUK POLDA GORONTALO
        if ($gorontalo) {
            DB::table('issues')->insert([
                [
                    'polda_id'               => $gorontalo->id,
                    'polres_id'              => $kotaGorontalo?->id,
                    'created_by'             => null,
                    'title'                  => 'Keluhan Warga Terkait Balap Liar Malam Minggu di Jalur Bypass Tamalate',
                    'slug'                   => 'keluhan-balap-liar-bypass-tamalate',
                    'summary'                => 'Warga sekitar mengeluhkan knalpot brong dan aksi balap liar remaja pada dini hari yang membahayakan pengguna jalan lain.',
                    'category'               => 'Lalu Lintas',
                    'priority'               => 'medium',
                    'status'                 => 'investigasi',
                    'sentiment'              => 'negatif',
                    'source_media'           => 'Instagram @gorontalo_update',
                    'source_url'             => 'https://instagram.com/p/DB182910',
                    'virality_score'         => 65,
                    'engagement_count'       => 9800,
                    'assigned_unit'          => 'Satlantas Polresta Gorontalo Kota',
                    'is_hoax'                => false,
                    'fact_check_result'      => 'Aksi balap liar terjadi sekitar pukul 01.30 WITA. Satlantas telah menyita 12 unit kendaraan berknalpot brong.',
                    'official_clarification' => 'Polresta Gorontalo Kota mengintensifkan Patroli Blue Light di sepanjang Jalan Bypass Tamalate dan menindak tegas pelanggar.',
                    'clarification_url'      => null,
                    'clarified_at'           => now()->subHours(2),
                    'created_at'             => now()->subHours(8),
                    'updated_at'             => now()->subHours(2),
                ],
            ]);
        }
    }
}

