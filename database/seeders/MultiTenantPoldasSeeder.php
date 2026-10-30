<?php

namespace Database\Seeders;

use App\Models\EarlyWarning;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\Polres;
use Illuminate\Database\Seeder;

class MultiTenantPoldasSeeder extends Seeder
{
    public function run(): void
    {
        // 1. POLDA SULAWESI SELATAN (SULSEL)
        $sulsel = Polda::updateOrCreate(
            ['code' => 'SULSEL'],
            [
                'name'             => 'Polda Sulawesi Selatan',
                'region'           => 'Sulawesi Selatan',
                'timezone'         => 'Asia/Makassar',
                'kapolda_name'     => 'Irjen Pol. Yudhiawan Wibisono, S.I.K., M.Si.',
                'kabid_humas_name' => 'Kombes Pol. Didik Supranoto, S.I.K., M.H.',
                'is_active'        => true,
            ]
        );

        $sulselPolres = [
            ['code' => 'MAKASSAR', 'name' => 'Polrestabes Makassar', 'short_name' => 'Makassar', 'tag_desc' => 'Metropolitan Ibukota', 'latitude' => -5.1477, 'longitude' => 119.4327, 'map_x' => 45, 'map_y' => 60],
            ['code' => 'GOWA', 'name' => 'Polres Gowa', 'short_name' => 'Gowa', 'tag_desc' => 'Kawasan Penyangga', 'latitude' => -5.2012, 'longitude' => 119.4533, 'map_x' => 47, 'map_y' => 65],
            ['code' => 'MAROS', 'name' => 'Polres Maros', 'short_name' => 'Maros', 'tag_desc' => 'Bandara Sultan Hasanuddin', 'latitude' => -5.0069, 'longitude' => 119.5746, 'map_x' => 48, 'map_y' => 52],
            ['code' => 'BONE', 'name' => 'Polres Bone', 'short_name' => 'Bone', 'tag_desc' => 'Pesisir Teluk Bone', 'latitude' => -4.5387, 'longitude' => 120.3283, 'map_x' => 72, 'map_y' => 42],
            ['code' => 'PALOPO', 'name' => 'Polres Palopo', 'short_name' => 'Palopo', 'tag_desc' => 'Kawasan Luwu Raya', 'latitude' => -2.9926, 'longitude' => 120.1972, 'map_x' => 68, 'map_y' => 20],
            ['code' => 'PAREPARE', 'name' => 'Polres Parepare', 'short_name' => 'Parepare', 'tag_desc' => 'Kota Pelabuhan Ajatappareng', 'latitude' => -4.0137, 'longitude' => 119.6264, 'map_x' => 42, 'map_y' => 38],
        ];

        foreach ($sulselPolres as $p) {
            Polres::updateOrCreate(
                ['polda_id' => $sulsel->id, 'code' => $p['code']],
                array_merge($p, ['polda_id' => $sulsel->id, 'is_active' => true])
            );
        }

        // 2. POLDA SULAWESI TENGGARA (SULTRA)
        $sultra = Polda::updateOrCreate(
            ['code' => 'SULTRA'],
            [
                'name'             => 'Polda Sulawesi Tenggara',
                'region'           => 'Sulawesi Tenggara',
                'timezone'         => 'Asia/Makassar',
                'kapolda_name'     => 'Irjen Pol. Dwi Irianto, S.I.K., M.Si.',
                'kabid_humas_name' => 'Kombes Pol. Iis Kristian, S.I.K.',
                'is_active'        => true,
            ]
        );

        $sultraPolres = [
            ['code' => 'KENDARI', 'name' => 'Polresta Kendari', 'short_name' => 'Kendari', 'tag_desc' => 'Pusat Pemerintahan', 'latitude' => -3.9985, 'longitude' => 122.5126, 'map_x' => 50, 'map_y' => 50],
            ['code' => 'BAUBAU', 'name' => 'Polres Baubau', 'short_name' => 'Baubau', 'tag_desc' => 'Kepulauan Buton', 'latitude' => -5.4632, 'longitude' => 122.6074, 'map_x' => 55, 'map_y' => 80],
            ['code' => 'KOLAKA', 'name' => 'Polres Kolaka', 'short_name' => 'Kolaka', 'tag_desc' => 'Kawasan Industri Nikel', 'latitude' => -4.0583, 'longitude' => 121.6015, 'map_x' => 35, 'map_y' => 45],
            ['code' => 'KONAWE', 'name' => 'Polres Konawe', 'short_name' => 'Konawe', 'tag_desc' => 'Kawasan Pertanian & Obvitnas', 'latitude' => -3.8827, 'longitude' => 122.0519, 'map_x' => 45, 'map_y' => 40],
        ];

        foreach ($sultraPolres as $p) {
            Polres::updateOrCreate(
                ['polda_id' => $sultra->id, 'code' => $p['code']],
                array_merge($p, ['polda_id' => $sultra->id, 'is_active' => true])
            );
        }

        // 3. POLDA KALIMANTAN TIMUR (KALTIM - IKN NUSANTARA)
        $kaltim = Polda::updateOrCreate(
            ['code' => 'KALTIM'],
            [
                'name'             => 'Polda Kalimantan Timur',
                'region'           => 'Kalimantan Timur (IKN)',
                'timezone'         => 'Asia/Makassar',
                'kapolda_name'     => 'Irjen Pol. Nanang Avianto, M.Si.',
                'kabid_humas_name' => 'Kombes Pol. Yuliyanto, S.I.K., M.Sc.',
                'is_active'        => true,
            ]
        );

        $kaltimPolres = [
            ['code' => 'BALIKPAPAN', 'name' => 'Polresta Balikpapan', 'short_name' => 'Balikpapan', 'tag_desc' => 'Gerbang Utama IKN', 'latitude' => -1.2379, 'longitude' => 116.8529, 'map_x' => 48, 'map_y' => 60],
            ['code' => 'SAMARINDA', 'name' => 'Polresta Samarinda', 'short_name' => 'Samarinda', 'tag_desc' => 'Ibukota Provinsi Kaltim', 'latitude' => -0.5022, 'longitude' => 117.1537, 'map_x' => 55, 'map_y' => 40],
            ['code' => 'PPU_IKN', 'name' => 'Polres Penajam Paser Utara', 'short_name' => 'PPU (IKN)', 'tag_desc' => 'Kawasan Inti Pusat Pemerintahan IKN', 'latitude' => -1.3094, 'longitude' => 116.7188, 'map_x' => 42, 'map_y' => 55],
            ['code' => 'KUKAR', 'name' => 'Polres Kutai Kartanegara', 'short_name' => 'Kukar', 'tag_desc' => 'Kawasan Mitra Strategis IKN', 'latitude' => -0.4431, 'longitude' => 117.0016, 'map_x' => 40, 'map_y' => 35],
        ];

        foreach ($kaltimPolres as $p) {
            Polres::updateOrCreate(
                ['polda_id' => $kaltim->id, 'code' => $p['code']],
                array_merge($p, ['polda_id' => $kaltim->id, 'is_active' => true])
            );
        }

        // Seed sample feeds for Sulsel
        MediaFeed::firstOrCreate(
            ['polda_id' => $sulsel->id, 'name' => 'Tribun Timur'],
            [
                'url'         => 'https://makassar.tribunnews.com/rss',
                'category'    => 'Regional Sulselbar',
                'is_active'   => true,
            ]
        );
        MediaFeed::firstOrCreate(
            ['polda_id' => $sulsel->id, 'name' => 'Fajar Makassar'],
            [
                'url'         => 'https://fajar.co.id/feed/',
                'category'    => 'Regional Sulselbar',
                'is_active'   => true,
            ]
        );
    }
}
