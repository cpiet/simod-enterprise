<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\Publication;
use App\Models\Task;
use Illuminate\Database\Seeder;

class PublicationsAndTasksSeeder extends Seeder
{
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        $gorontalo = Polda::where('code', 'GORONTALO')->first();

        if ($sulbar) {
            // Seed Publications Sulbar
            Publication::create([
                'polda_id'        => $sulbar->id,
                'channel'         => 'instagram',
                'title'           => 'Edukasi Tertib Lalu Lintas & Sosialisasi Operasi Patuh Marano 2026',
                'content_draft'   => "Halo Sobat Polri! Demi keselamatan bersama, Ditlantas Polda Sulbar mengimbau seluruh pengendara selalu menggunakan helm SNI dan mematuhi rambu lalu lintas.\n\n#PoldaSulbar #PolisiHumanis #OperasiPatuh2026 #MamujuAman",
                'url'             => 'https://instagram.com/p/DB190823',
                'approval_status' => 'published',
                'reviewed_by'     => 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
                'reviewed_at'     => now()->subHours(4),
            ]);

            Publication::create([
                'polda_id'        => $sulbar->id,
                'channel'         => 'tiktok',
                'title'           => 'Aksi Humanis Bhabinkamtibmas Polresta Mamuju Membantu Lansia Menyeberang Jalan',
                'content_draft'   => "Polisi Hadir, Melayani Sepenuh Hati! Momen hangat Bhabinkamtibmas saat bertugas di Simpang Lima Mamuju. #PolisiSahabatMasyarakat #SulbarMalaqbi",
                'url'             => 'https://tiktok.com/@humaspoldasulbar/video/73919028',
                'approval_status' => 'published',
                'reviewed_by'     => 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
                'reviewed_at'     => now()->subHours(2),
            ]);

            Publication::create([
                'polda_id'        => $sulbar->id,
                'channel'         => 'tribratanews',
                'title'           => 'Kapolda Sulbar Tinjau Langsung Kesiapan Pengamanan Gudang Logistik KPU',
                'content_draft'   => "MAMUJU — Kapolda Sulawesi Barat Irjen Pol. Drs. R. Adang Ginanjar S., M.M. meninjau langsung gudang logistik KPU Provinsi Sulawesi Barat guna memastikan pengamanan 24 jam dengan sistem CCTV terintegrasi dan personel bersenjata lengkap.",
                'url'             => 'https://tribratanews.sulbar.polri.go.id/berita/kapolda-tinjau-kpu-2026',
                'approval_status' => 'published',
                'reviewed_by'     => 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
                'reviewed_at'     => now()->subHours(6),
            ]);

            // Seed Tasks Disposisi Sulbar
            $task1 = Task::create([
                'polda_id'         => $sulbar->id,
                'title'            => 'Patroli Siber Memantau Narasi Provokatif Akun Anonim di Grup Facebook Mamuju',
                'instructions'     => 'Subdit Siber Ditreskrimsus agar mendata 3 akun penyebar ujaran kebencian berkedok isu SARA dan melakukan profiling identitas pemilik akun.',
                'assigned_unit'    => 'Subdit V Siber Ditreskrimsus Polda Sulbar',
                'assigned_officer' => 'Ipda Wahyu Pratama, S.Kom.',
                'due_at'           => now()->addHours(12),
                'priority'         => 'urgent',
                'status'           => 'in_progress',
            ]);

            $task1->updates()->create([
                'note' => 'Profiling awal berhasil mengidentifikasi 2 dari 3 nomor seluler yang terhubung ke akun Facebook tersebut. Tim berkoordinasi dengan provider telekomunikasi.',
            ]);

            Task::create([
                'polda_id'         => $sulbar->id,
                'title'            => 'Penerbitan Konten Narasi Positif Pengawalan Logistik di Wilayah Pesisir',
                'instructions'     => 'Tim Kreatif Bidhumas segera memproduksi 1 reel video dan 1 infografis mengenai kesigapan Polairud Polda Sulbar.',
                'assigned_unit'    => 'Seksi Produksi Kreatif Bidhumas Polda Sulbar',
                'assigned_officer' => 'Bripka Hendra Wijaya',
                'due_at'           => now()->addHours(6),
                'priority'         => 'high',
                'status'           => 'completed',
                'closed_at'        => now()->subHour(),
            ]);

            // Seed Audit Log
            AuditLog::create([
                'polda_id'     => $sulbar->id,
                'user_id'      => null,
                'action'       => 'system_initialization',
                'subject_type' => 'Polda',
                'subject_id'   => 'SULBAR',
                'metadata'     => ['module' => 'Core Setup', 'status' => 'Operational Ready'],
                'ip_address'   => '127.0.0.1',
                'user_agent'   => 'CLI/Artisan',
                'created_at'   => now()->subDay(),
            ]);
        }
    }
}

