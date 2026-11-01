<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class EnterpriseDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. SEED POLDAS (TENANTS)
        $sulbarId = DB::table('poldas')->insertGetId([
            'code'             => 'SULBAR',
            'name'             => 'Polda Sulawesi Barat',
            'region'           => 'Sulawesi Barat',
            'timezone'         => 'Asia/Makassar',
            'kapolda_name'     => 'Irjen Pol. Drs. R. Adang Ginanjar S., M.M.',
            'kabid_humas_name' => 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        $gorontaloId = DB::table('poldas')->insertGetId([
            'code'             => 'GORONTALO',
            'name'             => 'Polda Gorontalo',
            'region'           => 'Gorontalo',
            'timezone'         => 'Asia/Makassar',
            'kapolda_name'     => 'Irjen Pol. Drs. Pudji Prasetijanto Hadi, M.H.',
            'kabid_humas_name' => 'Kombes Pol. Desmont Harjendro A.P, S.I.K., M.T.',
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);

        // 2. SEED POLRES SULBAR (DENGAN GEOSPATIAL COORDINATES KAMTIBMAS MAP)
        $sulbarPolres = [
            [
                'code'       => 'MAMUJU',
                'name'       => 'Polresta Mamuju',
                'short_name' => 'Mamuju',
                'tag_desc'   => 'Pusat Ibukota Provinsi',
                'latitude'   => -2.6718000,
                'longitude'  => 118.8872000,
                'map_x'      => 37,
                'map_y'      => 52,
            ],
            [
                'code'       => 'MAJENE',
                'name'       => 'Polres Majene',
                'short_name' => 'Majene',
                'tag_desc'   => 'Pesisir Barat / Jalur Trans',
                'latitude'   => -3.5414000,
                'longitude'  => 118.9712000,
                'map_x'      => 31,
                'map_y'      => 74,
            ],
            [
                'code'       => 'POLMAN',
                'name'       => 'Polres Polewali Mandar',
                'short_name' => 'Polewali Mandar',
                'tag_desc'   => 'Wilayah Selatan / Pintu Gerbang',
                'latitude'   => -3.4328000,
                'longitude'  => 119.3435000,
                'map_x'      => 50,
                'map_y'      => 88,
            ],
            [
                'code'       => 'PASANGKAYU',
                'name'       => 'Polres Pasangkayu',
                'short_name' => 'Pasangkayu',
                'tag_desc'   => 'Perbatasan Palu/Utara',
                'latitude'   => -1.1925000,
                'longitude'  => 119.3789000,
                'map_x'      => 58,
                'map_y'      => 15,
            ],
            [
                'code'       => 'MAMASA',
                'name'       => 'Polres Mamasa',
                'short_name' => 'Mamasa',
                'tag_desc'   => 'Kawasan Pegunungan',
                'latitude'   => -2.9340000,
                'longitude'  => 119.3175000,
                'map_x'      => 66,
                'map_y'      => 67,
            ],
            [
                'code'       => 'MATENG',
                'name'       => 'Polres Mamuju Tengah',
                'short_name' => 'Mamuju Tengah',
                'tag_desc'   => 'Tobadak & Jalur Logistik',
                'latitude'   => -2.0392000,
                'longitude'  => 119.2885000,
                'map_x'      => 56,
                'map_y'      => 34,
            ],
        ];
        $firstSulbarPolresId = null;
        foreach ($sulbarPolres as $p) {
            $id = DB::table('polres')->updateOrInsert(
                ['polda_id' => $sulbarId, 'code' => $p['code']],
                [
                    'name'       => $p['name'],
                    'short_name' => $p['short_name'],
                    'tag_desc'   => $p['tag_desc'],
                    'latitude'   => $p['latitude'],
                    'longitude'  => $p['longitude'],
                    'map_x'      => $p['map_x'],
                    'map_y'      => $p['map_y'],
                    'is_active'  => true,
                    'updated_at' => now(),
                ]
            );
            if (! $firstSulbarPolresId) {
                $firstSulbarPolresId = DB::table('polres')->where('polda_id', $sulbarId)->where('code', $p['code'])->value('id');
            }
        }

        // 3. SEED POLRES GORONTALO
        $gorontaloPolres = [
            ['code' => 'KOTA_GORONTALO', 'name' => 'Polresta Gorontalo Kota'],
            ['code' => 'GORONTALO', 'name' => 'Polres Gorontalo'],
            ['code' => 'BOALEMO', 'name' => 'Polres Boalemo'],
            ['code' => 'POHUWATO', 'name' => 'Polres Pohuwato'],
            ['code' => 'BONE_BOLANGO', 'name' => 'Polres Bone Bolango'],
        ];
        foreach ($gorontaloPolres as $p) {
            DB::table('polres')->insert([
                'polda_id'   => $gorontaloId,
                'code'       => $p['code'],
                'name'       => $p['name'],
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 4. SEED USERS MULTI-TENANT
        DB::table('users')->insert([
            // Pimpinan Tertinggi Divhumas Polri (Komando Nasional)
            [
                'polda_id'     => null,
                'polres_id'    => null,
                'name'         => 'Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.',
                'username'     => 'kadivhumas',
                'email'        => 'kadivhumas@polri.go.id',
                'password'     => Hash::make('DivhumasPolri#2026'),
                'role'         => 'kadiv_humas',
                'badge_number' => '75060789',
                'phone'        => '081119750607',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            // Super Admin Mabes Polri (Lintas Polda)
            [
                'polda_id'     => null,
                'polres_id'    => null,
                'name'         => 'Super Administrator Mabes',
                'username'     => 'superadmin',
                'email'        => 'superadmin@polri.go.id',
                'password'     => Hash::make('MabesPolri#2026'),
                'role'         => 'super_admin_mabes',
                'badge_number' => '80010001',
                'phone'        => '081100001111',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            // Tech Admin Lutfi (Developer)
            [
                'polda_id'     => $sulbarId,
                'polres_id'    => null,
                'name'         => 'Lutfi Alfandra',
                'username'     => 'cpiet',
                'email'        => 'alumnidarus@gmail.com',
                'password'     => Hash::make('T0p1s@y@bund@r'),
                'role'         => 'super_admin_mabes',
                'badge_number' => '89020089',
                'phone'        => '081299998888',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            // Pimpinan Polda Sulbar
            [
                'polda_id'     => $sulbarId,
                'polres_id'    => null,
                'name'         => 'Kombes Pol. Slamet Wahyudi (Kabid Humas)',
                'username'     => 'kabid_sulbar',
                'email'        => 'kabid.sulbar@polri.go.id',
                'password'     => Hash::make('SulbarHumas#2026'),
                'role'         => 'polda_leader',
                'badge_number' => '76040112',
                'phone'        => '081234567891',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
            // Pimpinan Polda Gorontalo
            [
                'polda_id'     => $gorontaloId,
                'polres_id'    => null,
                'name'         => 'Kombes Pol. Desmont Harjendro (Kabid Humas)',
                'username'     => 'kabid_gorontalo',
                'email'        => 'kabid.gorontalo@polri.go.id',
                'password'     => Hash::make('GorontaloHumas#2026'),
                'role'         => 'polda_leader',
                'badge_number' => '77080223',
                'phone'        => '081234567892',
                'is_active'    => true,
                'created_at'   => now(),
                'updated_at'   => now(),
            ],
        ]);

        // 5. SEED EARLY WARNING SYSTEM (EWS) PER POLDA
        DB::table('early_warnings')->insert([
            [
                'polda_id'        => $sulbarId,
                'polres_id'       => $firstSulbarPolresId,
                'title'           => 'Insiden Anak Tenggelam di Pantai Arteri Mamuju: Narasi Duka dan Pertanyaan Publik',
                'level'           => 'yellow',
                'category'        => 'Kecelakaan Perairan / Fasilitas Publik',
                'facts'           => 'Media lokal melaporkan anak berusia 11 tahun ditemukan meninggal setelah diduga tenggelam di kawasan Pantai Arteri Mamuju. Polresta Mamuju dikutip mengonfirmasi penemuan korban.',
                'reputation_risk' => 'Komunikasi yang spekulatif, defensif, atau menjadikan tragedi sebagai materi pencitraan dapat memperbesar luka keluarga dan merusak kepercayaan publik.',
                'protocol_0_2h'   => 'Sampaikan belasungkawa resmi, konfirmasi fakta dasar kepada Polresta Mamuju, serta lindungi identitas dan privasi keluarga korban.',
                'protocol_2_6h'   => 'Sinkronkan kronologi dan pembagian peran pencarian dengan instansi terkait. Siapkan satu keterangan faktual, jangan menyimpulkan penyebab sebelum pemeriksaan tuntas.',
                'protocol_6_24h'  => 'Berikan pembaruan hanya jika ada informasi terkonfirmasi dan relevan bagi publik. Pisahkan edukasi keselamatan pesisir dari pemberitaan kasus.',
                'pic_units'       => 'Polresta Mamuju, Ditpolairud Polda Sulbar, Bidhumas',
                'status'          => 'active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
            [
                'polda_id'        => $gorontaloId,
                'polres_id'       => null,
                'title'           => 'Potensi Unjuk Rasa Sengketa Lahan Pertambangan Emas Rakyat di Pohuwato',
                'level'           => 'yellow',
                'category'        => 'Harkamtibmas / Agraria',
                'facts'           => 'Kelompok masyarakat lingkar tambang merencanakan aksi penyampaian aspirasi damai terkait batas konsesi tambang rakyat.',
                'reputation_risk' => 'Potensi pembingkaian represif jika pengamanan tidak mengedepankan pendekatan humanis dan komunikasi persuasif.',
                'protocol_0_2h'   => 'Koordinasikan intelijen keamanan Polres Pohuwato dan Satbrimob untuk pemetaan korlap dan titik kumpul.',
                'protocol_2_6h'   => 'Siapkan tim dokumentasi digital Bidhumas dan rilis penerimaan aspirasi secara terbuka.',
                'protocol_6_24h'  => 'Publikasikan dokumentasi pelayanan unjuk rasa yang humanis serta pengawalan arus lalu lintas.',
                'pic_units'       => 'Polres Pohuwato, Ditintelkam, Bidhumas Polda Gorontalo',
                'status'          => 'active',
                'created_at'      => now(),
                'updated_at'      => now(),
            ],
        ]);

        // 6. SEED MEDIA FEEDS PER POLDA
        DB::table('media_feeds')->insert([
            [
                'polda_id'   => $gorontaloId,
                'name'       => 'Antara Gorontalo',
                'url'        => 'https://gorontalo.antaranews.com/rss/terkini.xml',
                'category'   => 'Regional Gorontalo',
                'is_active'  => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        // Seed 29 RSS Sources Resmi Terorganisir untuk Polda Sulbar
        $this->call(SulbarRssFeedsSeeder::class);
    }
}
