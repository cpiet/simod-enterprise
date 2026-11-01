<?php

namespace Database\Seeders;

use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\User;
use Illuminate\Database\Seeder;

class MediaTopicsSeeder extends Seeder
{
    public function run(): void
    {
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();
        $sulbar = Polda::where('code', 'SULBAR')->first();
        $admin = User::first();

        if ($divhumas) {
            MediaTopic::updateOrCreate(
                [
                    'polda_id' => $divhumas->id,
                    'title'    => 'Penggeledahan Ponpes Sukoharjo & Respon Komnas HAM',
                ],
                [
                    'keywords'    => 'pesantren, ponpes, sukoharjo, karanganyar, komnas ham, penggeledahan, propam, kadivpropam',
                    'description' => 'Atensi Khusus Kadiv Humas Polri: Pantau dinamika respons publik dan rekomendasi Komnas HAM terkait penggeledahan di Ponpes Sukoharjo & Karanganyar. Siapkan data klarifikasi transparan dan humanis.',
                    'priority'    => 'urgent',
                    'status'      => 'active',
                    'created_by'  => $admin?->id,
                ]
            );

            MediaTopic::updateOrCreate(
                [
                    'polda_id' => $divhumas->id,
                    'title'    => 'Penanganan Bencana Banjir & Longsor Aceh',
                ],
                [
                    'keywords'    => 'banjir, aceh barat, nagan raya, evakuasi, tanggap darurat, bnpb',
                    'description' => 'Atensi Pimpinan: Amplifikasi aksi kemanusiaan cepat tanggap Polri-TNI dalam evakuasi warga dan distribusi logistik korban banjir.',
                    'priority'    => 'high',
                    'status'      => 'active',
                    'created_by'  => $admin?->id,
                ]
            );
        }

        if ($sulbar) {
            MediaTopic::updateOrCreate(
                [
                    'polda_id' => $sulbar->id,
                    'title'    => 'Patroli Siber & Harkamtibmas Pilkada Sulbar',
                ],
                [
                    'keywords'    => 'patroli, siber, pilkada, mamuju, majene, polman, pasangkayu, netralitas',
                    'description' => 'Atensi Kapolda Sulbar: Monitoring 24 jam isu kamtibmas dan potensi hoaks di ruang digital wilayah hukum 6 Polres.',
                    'priority'    => 'high',
                    'status'      => 'active',
                    'created_by'  => $admin?->id,
                ]
            );
        }
    }
}
