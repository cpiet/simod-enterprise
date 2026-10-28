<?php

namespace Database\Seeders;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\User;
use App\Models\Issue;
use App\Models\Task;
use App\Models\Publication;
use App\Models\MediaArticle;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CommandCenterDynamicSeeder extends Seeder
{
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (!$sulbar) return;

        $user = User::where('polda_id', $sulbar->id)->first() ?? User::first();

        // 1. UPDATE GEOSPATIAL COORDINATES & SHORT NAMES FOR 6 POLRES SULBAR
        $coords = [
            'MAMUJU' => [
                'name'       => 'Polresta Mamuju',
                'short_name' => 'Mamuju',
                'tag_desc'   => 'Pusat Ibukota Provinsi',
                'latitude'   => -2.6718000,
                'longitude'  => 118.8872000,
                'map_x'      => 37,
                'map_y'      => 52,
            ],
            'MAJENE' => [
                'name'       => 'Polres Majene',
                'short_name' => 'Majene',
                'tag_desc'   => 'Pesisir Barat / Jalur Trans',
                'latitude'   => -3.5414000,
                'longitude'  => 118.9712000,
                'map_x'      => 31,
                'map_y'      => 74,
            ],
            'POLMAN' => [
                'name'       => 'Polres Polewali Mandar',
                'short_name' => 'Polewali Mandar',
                'tag_desc'   => 'Wilayah Selatan / Pintu Gerbang',
                'latitude'   => -3.4328000,
                'longitude'  => 119.3435000,
                'map_x'      => 50,
                'map_y'      => 88,
            ],
            'PASANGKAYU' => [
                'name'       => 'Polres Pasangkayu',
                'short_name' => 'Pasangkayu',
                'tag_desc'   => 'Perbatasan Palu/Utara',
                'latitude'   => -1.1925000,
                'longitude'  => 119.3789000,
                'map_x'      => 58,
                'map_y'      => 15,
            ],
            'MAMASA' => [
                'name'       => 'Polres Mamasa',
                'short_name' => 'Mamasa',
                'tag_desc'   => 'Kawasan Pegunungan',
                'latitude'   => -2.9340000,
                'longitude'  => 119.3175000,
                'map_x'      => 66,
                'map_y'      => 67,
            ],
            'MATENG' => [
                'name'       => 'Polres Mamuju Tengah',
                'short_name' => 'Mamuju Tengah',
                'tag_desc'   => 'Tobadak & Jalur Logistik',
                'latitude'   => -2.0392000,
                'longitude'  => 119.2885000,
                'map_x'      => 56,
                'map_y'      => 34,
            ],
        ];

        foreach ($coords as $code => $data) {
            Polres::where('polda_id', $sulbar->id)
                ->where('code', $code)
                ->update($data);
        }

        $polresMamuju = Polres::where('polda_id', $sulbar->id)->where('code', 'MAMUJU')->first();
        $polresMajene = Polres::where('polda_id', $sulbar->id)->where('code', 'MAJENE')->first();
        $polresPolman = Polres::where('polda_id', $sulbar->id)->where('code', 'POLMAN')->first();
        $polresPasangkayu = Polres::where('polda_id', $sulbar->id)->where('code', 'PASANGKAYU')->first();
        $polresMamasa = Polres::where('polda_id', $sulbar->id)->where('code', 'MAMASA')->first();
        $polresMateng = Polres::where('polda_id', $sulbar->id)->where('code', 'MATENG')->first();

        // 2. HEURISTIC SENTIMENT UPDATE ON MEDIA ARTICLES
        $positiveWords = ['berhasil', 'ungkap', 'aman', 'kondusif', 'apresiasi', 'bantu', 'bansos', 'tertib', 'humanis', 'prestasi', 'presisi', 'sukses', 'penghargaan', 'selamat', 'lancar', 'pengamanan', 'sinergi', 'patroli', 'damai', 'kebaikan', 'ramah', 'juara', 'terkendali'];
        $negativeWords = ['begal', 'curanmor', 'tewas', 'korban', 'pembunuhan', 'narkoba', 'hoaks', 'rusak', 'ricuh', 'bentrok', 'pungli', 'jambret', 'kebakaran', 'longsor', 'banjir', 'aniaya', 'cabul', 'korupsi', 'kejahatan', 'brong', 'balap liar'];

        foreach (MediaArticle::where('polda_id', $sulbar->id)->cursor() as $art) {
            $text = strtolower($art->title . ' ' . $art->summary);
            $isPos = false;
            $isNeg = false;
            foreach ($positiveWords as $w) {
                if (str_contains($text, $w)) { $isPos = true; break; }
            }
            foreach ($negativeWords as $w) {
                if (str_contains($text, $w)) { $isNeg = true; break; }
            }
            if ($isPos && !$isNeg) {
                $art->sentiment = 'positif';
            } elseif ($isNeg && !$isPos) {
                $art->sentiment = 'negatif';
            } else {
                $art->sentiment = 'netral';
            }
            $art->save();
        }

        // 3. RE-SEED RICH REAL ISSUES FOR ALL 6 POLRES
        DB::table('tasks')->where('polda_id', $sulbar->id)->delete();
        DB::table('publications')->where('polda_id', $sulbar->id)->delete();
        DB::table('issues')->where('polda_id', $sulbar->id)->delete();

        $issuesData = [
            // POLRESTA MAMUJU (ATENSI - Pilkada & Balap Liar / Dumas)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMamuju?->id,
                'created_by' => $user?->id,
                'title' => 'Pengamanan Intensif Tahapan Pilkada Serentak di Kantor Penyelenggara Pemilu Mamuju',
                'slug' => Str::slug('Pengamanan Intensif Tahapan Pilkada Serentak Mamuju'),
                'summary' => 'Satgas Operasi Mantap Praja Polresta Mamuju memperketat pengamanan 24 jam di Kantor KPU Sulbar, Bawaslu, dan gudang logistik surat suara guna mengantisipasi eskalasi massa.',
                'category' => 'Harkamtibmas',
                'priority' => 'critical',
                'status' => 'investigasi',
                'sentiment' => 'positif',
                'source_media' => 'Humas Polri & Media Cetak',
                'source_url' => 'https://humas.polri.go.id/sulbar/pengamanan-kpu',
                'virality_score' => 92,
                'engagement_count' => 32400,
                'assigned_unit' => 'Sat Samapta & Sat Intelkam Polresta Mamuju',
                'is_hoax' => false,
                'created_at' => now()->subHours(2),
                'updated_at' => now()->subHours(1),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMamuju?->id,
                'created_by' => $user?->id,
                'title' => 'Viral Narasi Kenaikan Tarif Parkir Liar di Kawasan Pantai Manakarra',
                'slug' => Str::slug('Viral Narasi Kenaikan Tarif Parkir Liar Pantai Manakarra'),
                'summary' => 'Beredar video di TikTok dan WhatsApp warga Mamuju mengeluhkan oknum juru parkir liar mematok tarif Rp 10.000 di area publik Manakarra menjelang akhir pekan.',
                'category' => 'Pelayanan Publik',
                'priority' => 'high',
                'status' => 'monitoring',
                'sentiment' => 'negatif',
                'source_media' => 'TikTok & WhatsApp',
                'source_url' => 'https://tiktok.com/@wargamamuju/video/7391823912',
                'virality_score' => 78,
                'engagement_count' => 14200,
                'assigned_unit' => 'Satreskrim Polresta Mamuju & Satpol PP',
                'is_hoax' => false,
                'created_at' => now()->subHours(8),
                'updated_at' => now()->subHours(3),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMamuju?->id,
                'created_by' => $user?->id,
                'title' => 'Patroli Malam Presisi URC Ditreskrimum Amankan Belasan Remaja Balap Liar Arteri',
                'slug' => Str::slug('Patroli Malam Presisi URC Balap Liar Arteri'),
                'summary' => 'Unit Reaksi Cepat (URC) Polresta Mamuju bersama Ditreskrimum mengamankan 14 unit sepeda motor knalpot brong tanpa surat sah saat balap liar di Jalur Arteri.',
                'category' => 'Laka Lantas & Kriminal',
                'priority' => 'medium',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Detik Sulsel & Instagram',
                'source_url' => 'https://www.detik.com/sulsel/berita/patroli-malam-arteri-mamuju',
                'virality_score' => 71,
                'engagement_count' => 18900,
                'assigned_unit' => 'Satlantas & URC Polresta Mamuju',
                'is_hoax' => false,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subHours(10),
            ],

            // POLRES MAJENE (KONDUSIF - Jalur Trans Sulawesi lancar & bersih)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMajene?->id,
                'created_by' => $user?->id,
                'title' => 'Evakuasi Material Pohon Tumbang & Pembersihan Jalur Trans-Sulawesi Tubo Sendana',
                'slug' => Str::slug('Evakuasi Pohon Tumbang Trans Sulawesi Tubo Sendana'),
                'summary' => 'Personel Polsek Sendana Polres Majene bergotong royong bersama BPBD dan warga membersihkan material longsor batu dan pohon tumbang, arus lalu lintas kembali lancar.',
                'category' => 'Bencana Alam & Lalin',
                'priority' => 'medium',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Antara News Makassar & Facebook',
                'source_url' => 'https://makassar.antaranews.com/berita/polres-majene-evakuasi-longsor',
                'virality_score' => 82,
                'engagement_count' => 16500,
                'assigned_unit' => 'Polsek Sendana & Satlantas Polres Majene',
                'is_hoax' => false,
                'created_at' => now()->subDays(1),
                'updated_at' => now()->subHours(6),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMajene?->id,
                'created_by' => $user?->id,
                'title' => 'Patroli Humanis & Edukasi Keselamatan Sopir Angkutan Barang di Pesisir Majene',
                'slug' => Str::slug('Patroli Humanis Sopir Angkutan Barang Pesisir Majene'),
                'summary' => 'Satlantas Polres Majene menggelar razia simpatik memberikan kopi gratis dan pemeriksaan kesehatan bagi pengemudi lintas provinsi di rest area Somba.',
                'category' => 'Pelayanan Publik',
                'priority' => 'low',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Radar Sulbar',
                'source_url' => 'https://radarsulbar.fajar.co.id/polres-majene-sopir-lintas',
                'virality_score' => 64,
                'engagement_count' => 8400,
                'assigned_unit' => 'Satlantas Polres Majene',
                'is_hoax' => false,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(1),
            ],

            // POLRES POLEWALI MANDAR (KONDUSIF - Perbatasan Aman & Damai)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresPolman?->id,
                'created_by' => $user?->id,
                'title' => 'Operasi Patuh Zebra Marano: Penertiban Knalpot Brong di Kawasan Pasar Wonomulyo',
                'slug' => Str::slug('Operasi Zebra Penertiban Knalpot Brong Wonomulyo'),
                'summary' => 'Guna menciptakan ketenangan warga beribadah, Satlantas Polres Polman menyita 35 knalpot brong dan menindak pengendara tanpa helm standar di Wonomulyo.',
                'category' => 'Harkamtibmas',
                'priority' => 'medium',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Tribun Sulbar',
                'source_url' => 'https://sulbar.tribunnews.com/polman-operasi-knalpot-brong',
                'virality_score' => 74,
                'engagement_count' => 21300,
                'assigned_unit' => 'Satlantas Polres Polman',
                'is_hoax' => false,
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(1),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresPolman?->id,
                'created_by' => $user?->id,
                'title' => 'Mediasi Restorative Justice Sengketa Batas Lahan Warga Tinambung Berjalan Damai',
                'slug' => Str::slug('Mediasi RJ Sengketa Lahan Tinambung Damai'),
                'summary' => 'Bhabinkamtibmas Polsek Tinambung memfasilitasi musyawarah kekeluargaan perselisihan batas tanah antar dua keluarga yang telah berlangsung menahun.',
                'category' => 'Problem Solving',
                'priority' => 'low',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Mekora.id',
                'source_url' => 'https://mekora.id/bhabinkamtibmas-tinambung-mediasi-lahan',
                'virality_score' => 61,
                'engagement_count' => 5200,
                'assigned_unit' => 'Bhabinkamtibmas Polsek Tinambung',
                'is_hoax' => false,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(2),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresPolman?->id,
                'created_by' => $user?->id,
                'title' => 'Penjagaan Ketat Pos Perbatasan Sulbar-Sulsel Jalur Paku Antisipasi Penyelundupan',
                'slug' => Str::slug('Penjagaan Pos Perbatasan Paku Antisipasi Penyelundupan'),
                'summary' => 'Personel gabungan Polres Polman meningkatkan pemeriksaan muatan barang terlarang dan dokumen hewan ternak di pintu gerbang selatan Sulawesi Barat.',
                'category' => 'Harkamtibmas',
                'priority' => 'low',
                'status' => 'monitoring',
                'sentiment' => 'positif',
                'source_media' => 'Globalsulbar',
                'source_url' => 'https://globalsulbar.com/pos-paku-polman-ketat',
                'virality_score' => 69,
                'engagement_count' => 9800,
                'assigned_unit' => 'Sat Samapta Polres Polman',
                'is_hoax' => false,
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subDays(2),
            ],

            // POLRES MAMASA (WASPADA - Kawasan Pegunungan & Potensi Cuaca Ekstrem)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMamasa?->id,
                'created_by' => $user?->id,
                'title' => 'Antisipasi Bencana Longsor Jalur Tabang - Mamasa Akibat Curah Hujan Tinggi',
                'slug' => Str::slug('Antisipasi Longsor Jalur Tabang Mamasa'),
                'summary' => 'Polres Mamasa menyiagakan alat berat dan personel SAR Perintis di titik rawan longsor perbukitan Tabang pasca hujan deras mengguyur selama dua malam berturut-turut.',
                'category' => 'Bencana Alam',
                'priority' => 'high',
                'status' => 'monitoring',
                'sentiment' => 'netral',
                'source_media' => 'Tribun Sulbar & Katinting',
                'source_url' => 'https://katinting.com/polres-mamasa-siaga-longsor-tabang',
                'virality_score' => 79,
                'engagement_count' => 15400,
                'assigned_unit' => 'Sat Samapta & Sat Binmas Polres Mamasa',
                'is_hoax' => false,
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(4),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMamasa?->id,
                'created_by' => $user?->id,
                'title' => 'Bakti Kesehatan Presisi & Pengobatan Gratis Bagi Warga Pelosok Desa Samban Mamasa',
                'slug' => Str::slug('Bakti Kesehatan Pengobatan Gratis Warga Samban Mamasa'),
                'summary' => 'Dokkes bersama Humas Polres Mamasa menembus jalan setapak lereng gunung mengantarkan obat-obatan dan pemeriksaan tensi gratis bagi lansia dan anak.',
                'category' => 'Pelayanan Publik',
                'priority' => 'low',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Bacasaja.id & TikTok',
                'source_url' => 'https://bacasaja.id/bakti-kesehatan-mamasa',
                'virality_score' => 83,
                'engagement_count' => 24100,
                'assigned_unit' => 'Si Dokkes & Seksi Humas Polres Mamasa',
                'is_hoax' => false,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(3),
            ],

            // POLRES PASANGKAYU (KONDUSIF - Perbatasan Utara & Jalur Sawit Aman)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresPasangkayu?->id,
                'created_by' => $user?->id,
                'title' => 'Bakti Sosial & Patroli Terpadu Obvit Perkebunan Kelapa Sawit Sarudu Humanis',
                'slug' => Str::slug('Bakti Sosial Patroli Terpadu Kebun Sawit Sarudu'),
                'summary' => 'Patroli gabungan Polres Pasangkayu menyambangi komunitas buruh kelapa sawit di Sarudu sembari menyalurkan bantuan sembako dan mendengar aspirasi warga pekerja.',
                'category' => 'Humanis',
                'priority' => 'low',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Sulbar Express',
                'source_url' => 'https://sulbarexpress.fajar.co.id/patroli-humanis-pasangkayu-sarudu',
                'virality_score' => 70,
                'engagement_count' => 11200,
                'assigned_unit' => 'Sat Samapta & Sat Binmas Polres Pasangkayu',
                'is_hoax' => false,
                'created_at' => now()->subDays(4),
                'updated_at' => now()->subDays(3),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresPasangkayu?->id,
                'created_by' => $user?->id,
                'title' => 'Pemeriksaan Rutin Pintu Gerbang Lintas Provinsi Pasangkayu - Donggala',
                'slug' => Str::slug('Pemeriksaan Pintu Gerbang Pasangkayu Donggala'),
                'summary' => 'Situasi kamtibmas wilayah perbatasan utara Sulbar terpantau kondusif, arus logistik barang dan mobilitas masyarakat berlangsung tertib.',
                'category' => 'Harkamtibmas',
                'priority' => 'low',
                'status' => 'monitoring',
                'sentiment' => 'positif',
                'source_media' => 'Mesakada.com',
                'source_url' => 'https://mesakada.com/perbatasan-pasangkayu-aman',
                'virality_score' => 62,
                'engagement_count' => 6700,
                'assigned_unit' => 'Polsek Sarjo Polres Pasangkayu',
                'is_hoax' => false,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(4),
            ],

            // POLRES MAMUJU TENGAH (KONDUSIF - Topoyo Aman & Klarifikasi Hoaks Cepat)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMateng?->id,
                'created_by' => $user?->id,
                'title' => 'Hoaks Isu Begal di Jalan Trans Sulawesi Jalur Tarailu - Mamuju Tengah',
                'slug' => Str::slug('Hoaks Isu Begal Tarailu Mateng'),
                'summary' => 'Pesan berantai voice note dan tangkapan layar mengklaim adanya komplotan begal bersenjata tajam yang beraksi pada malam hari di jalur kebun sawit Tarailu.',
                'category' => 'Cyber Patrol & Klarifikasi',
                'priority' => 'high',
                'status' => 'klarifikasi',
                'sentiment' => 'negatif',
                'source_media' => 'WhatsApp & Facebook Group',
                'source_url' => 'https://facebook.com/groups/infomamujuterkini/posts/1029381',
                'virality_score' => 91,
                'engagement_count' => 28500,
                'assigned_unit' => 'Subdit Siber & Humas Polres Mamuju Tengah',
                'is_hoax' => true,
                'fact_check_result' => 'Klarifikasi Humas Mateng: Rekaman suara berasal dari insiden tahun 2021 di provinsi lain. Jalur Tarailu dipatroli rutin dan aman.',
                'official_clarification' => '[FAKTA ATAU HOAKS] Bidhumas Polda Sulbar menegaskan informasi begal di Tarailu adalah HOAKS. Masyarakat diminta tidak meneruskan narasi palsu.',
                'clarification_url' => 'https://humas.polri.go.id/sulbar/hoaks-begal-tarailu',
                'clarified_at' => now()->subHours(6),
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subHours(6),
            ],
            [
                'polda_id' => $sulbar->id,
                'polres_id' => $polresMateng?->id,
                'created_by' => $user?->id,
                'title' => 'Patroli Dialogis Kemitraan Warga Malam Hari Cegah Aksi Balap Liar Tobadak',
                'slug' => Str::slug('Patroli Dialogis Malam Hari Cegah Balap Liar Tobadak'),
                'summary' => 'Personel Sat Samapta Polres Mamuju Tengah berpatroli menyambangi tokoh pemuda di Bundaran Tugu Topoyo guna menjaga kamtibmas malam hari.',
                'category' => 'Harkamtibmas',
                'priority' => 'low',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Rakyat Sulbar',
                'source_url' => 'https://www.rakyatsulbar.com/patroli-dialogis-tobadak',
                'virality_score' => 60,
                'engagement_count' => 7800,
                'assigned_unit' => 'Sat Samapta Polres Mateng',
                'is_hoax' => false,
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(4),
            ],

            // POLDA SULBAR (Teritorial Khusus / Balabalakang)
            [
                'polda_id' => $sulbar->id,
                'polres_id' => null,
                'created_by' => $user?->id,
                'title' => 'Apresiasi Publik: Pengawalan Distribusi Logistik Pemilu ke Daerah Terpencil Pulau Balabalakang',
                'slug' => Str::slug('Apresiasi Pengawalan Logistik Balabalakang'),
                'summary' => 'Liputan media nasional dan postingan positif netizen mengapresiasi personel Ditpolairud Polda Sulbar yang menembus gelombang laut mengantar kotak suara pemilu ke pulau terluar.',
                'category' => 'Harkamtibmas',
                'priority' => 'low',
                'status' => 'selesai',
                'sentiment' => 'positif',
                'source_media' => 'Detikcom & Kompas TV',
                'source_url' => 'https://detik.com/sulsel/berita/polairud-sulbar-balabalakang',
                'virality_score' => 88,
                'engagement_count' => 45000,
                'assigned_unit' => 'Ditpolairud Polda Sulbar',
                'is_hoax' => false,
                'created_at' => now()->subDays(6),
                'updated_at' => now()->subDays(5),
            ],
        ];

        foreach ($issuesData as $item) {
            $issueId = DB::table('issues')->insertGetId($item);

            // Seed corresponding task
            $taskStatus = ($item['status'] === 'selesai' || $item['status'] === 'klarifikasi') ? 'completed' : 'in_progress';
            $taskPriority = match ($item['priority']) {
                'critical' => 'urgent',
                'high' => 'high',
                'medium' => 'medium',
                default => 'low',
            };

            DB::table('tasks')->insert([
                'polda_id' => $item['polda_id'],
                'polres_id' => $item['polres_id'],
                'issue_id' => $issueId,
                'created_by' => $user?->id,
                'title' => 'Tindak Lanjut Cepat: ' . $item['title'],
                'instructions' => 'Lakukan pemantauan lapangan, koordinasi dengan satuan fungsi terkait, dan unggah dokumentasi klarifikasi.',
                'assigned_unit' => $item['assigned_unit'],
                'assigned_officer' => 'Tim Humas & Operasional Satker',
                'due_at' => now()->addDays(2),
                'priority' => $taskPriority,
                'status' => $taskStatus,
                'closed_at' => ($taskStatus === 'completed') ? now()->subHours(2) : null,
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ]);

            // Seed publication draft/published
            DB::table('publications')->insert([
                'polda_id' => $item['polda_id'],
                'polres_id' => $item['polres_id'],
                'issue_id' => $issueId,
                'author_id' => $user?->id,
                'channel' => 'instagram',
                'title' => 'Rilis Edukatif & Fakta Kamtibmas: ' . Str::limit($item['title'], 50),
                'content_draft' => $item['summary'],
                'url' => $item['source_url'],
                'approval_status' => ($taskStatus === 'completed') ? 'published' : 'draft_release',
                'reviewed_by' => $user?->name ?? 'Kasubbid Penmas',
                'reviewed_at' => now()->subHours(1),
                'review_notes' => 'Disetujui untuk disebarluaskan pada kanal resmi.',
                'created_at' => $item['created_at'],
                'updated_at' => $item['updated_at'],
            ]);
        }
    }
}
