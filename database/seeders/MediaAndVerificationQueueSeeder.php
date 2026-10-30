<?php

namespace Database\Seeders;

use App\Models\Issue;
use App\Models\IssueStatusHistory;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MediaAndVerificationQueueSeeder extends Seeder
{
    public function run(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();
        if (!$sulbar) return;

        $user = User::where('role', 'admin_polda')->where('polda_id', $sulbar->id)->first() ?? User::first();
        $polresList = Polres::where('polda_id', $sulbar->id)->get()->keyBy('code');

        // 1. UPDATE EXISTING ISSUES WITH REPORT CODES & LOCATIONS
        $existingIssues = Issue::where('polda_id', $sulbar->id)->orderBy('id')->get();
        $counter = 1;
        foreach ($existingIssues as $issue) {
            $code = sprintf('SIM-2026-%05d', $counter++);
            $loc = $issue->polres ? "Wilayah Hukum {$issue->polres->name}" : "Wilayah Hukum Polda Sulbar";
            $issue->update([
                'report_code'   => $issue->report_code ?: $code,
                'location_text' => $issue->location_text ?: $loc,
                'occurred_at'   => $issue->occurred_at ?: now()->subDays(rand(1, 4))->subHours(rand(1, 12)),
            ]);

            // Ensure each has at least one status history
            if ($issue->statusHistories()->count() === 0) {
                IssueStatusHistory::create([
                    'issue_id'    => $issue->id,
                    'actor_id'    => $user?->id,
                    'from_status' => 'draft',
                    'to_status'   => $issue->status,
                    'reason'      => 'Registrasi laporan dan telaah awal operator Humas',
                    'created_at'  => $issue->created_at ?: now()->subDays(2),
                ]);
            }
        }

        // 2. CREATE SPECIFIC VERIFICATION QUEUE ISSUES (Menunggu Verifikasi Polres & Polda)
        $queueData = [
            [
                'title'         => 'Laporan Dugaan Penimbunan BBM Subsidi Solar di Pesisir Rangas Mamuju',
                'summary'       => 'Informasi awal dari warga dan media siber lokal terkait aktivitas bongkar muat jeriken solar subsidi berskala besar pada malam hari di dermaga nelayan Rangas.',
                'category'      => 'Kriminalitas',
                'priority'      => 'critical',
                'status'        => 'monitoring',
                'sentiment'     => 'negatif',
                'polres_code'   => 'MAMUJU',
                'location'      => 'Dermaga Tradisional Rangas, Kec. Simboro, Mamuju',
                'source_media'  => 'Laporan Warga & Katinting.com',
                'source_url'    => 'https://katinting.com/berita/dugaan-solar-subsidi-rangas',
                'assigned_unit' => 'Subdit Tipidter Ditreskrimsus & Polresta Mamuju',
                'photo_url'     => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title'         => 'Apresiasi Publik: Pengawalan Distribusi Kotak Suara ke Daerah Terpencil Pulau Balabalakang',
                'summary'       => 'Pemberitaan positif masif mengenai personel gabungan Ditpolairud dan Polres Mamuju yang menerjang ombak Selat Makassar demi memastikan logistik pilkada tiba aman.',
                'category'      => 'Pelayanan Publik',
                'priority'      => 'medium',
                'status'        => 'monitoring',
                'sentiment'     => 'positif',
                'polres_code'   => 'MAMUJU',
                'location'      => 'Kepulauan Balabalakang, Mamuju',
                'source_media'  => 'Tribun Sulbar & Detik Sulsel',
                'source_url'    => 'https://sulbar.tribunnews.com/balabalakang-logistik-aman',
                'assigned_unit' => 'Ditpolairud Polda Sulbar & Bidhumas',
                'photo_url'     => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title'         => 'Klarifikasi Potensi Hoaks Penculikan Anak di Area Pasar Sentral Majene',
                'summary'       => 'Beredar voice note berantai di grup ibu-ibu Majene mengenai mobil minibus mencurigakan yang diduga pelaku penculikan anak sekolah dasar.',
                'category'      => 'Harkamtibmas',
                'priority'      => 'high',
                'status'        => 'investigasi',
                'sentiment'     => 'negatif',
                'polres_code'   => 'MAJENE',
                'location'      => 'Kawasan Pasar Sentral Majene, Banggae',
                'source_media'  => 'WhatsApp & Facebook Group Warga Majene',
                'source_url'    => 'https://facebook.com/infomajene/posts/99120',
                'assigned_unit' => 'Satreskrim Polres Majene & Polsek Banggae',
                'photo_url'     => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title'         => 'Laporan Rekayasa Lalu Lintas Titik Longsor Jalur Lintas Mamasa - Polewali',
                'summary'       => 'Material longsoran tanah menutupi separuh badan jalan di KM 32 Tabone. Satlantas Polres Mamasa memberlakukan sistem buka-tutup jalur trans pegunungan.',
                'category'      => 'Lalu Lintas',
                'priority'      => 'high',
                'status'        => 'monitoring',
                'sentiment'     => 'netral',
                'polres_code'   => 'MAMASA',
                'location'      => 'Jalur Poros Mamasa - Polewali KM 32, Desa Tabone',
                'source_media'  => 'Laporan Radio Handy Talkie (HT) & Radar Sulbar',
                'source_url'    => 'https://radarsulbar.fajar.co.id/longsor-poros-mamasa',
                'assigned_unit' => 'Satlantas Polres Mamasa & BPBD',
                'photo_url'     => 'https://images.unsplash.com/photo-1519501025264-65ba15a82390?w=800&auto=format&fit=crop&q=80',
            ],
            [
                'title'         => 'Kegiatan Patroli Perintis Presisi Antisipasi Knalpot Brong di Pantai Bahari Polman',
                'summary'       => 'Satuan Samapta Polres Polewali Mandar menggelar razia tertib knalpot tidak standar dan pembinaan kelompok remaja balap liar di sepanjang Pantai Bahari.',
                'category'      => 'Harkamtibmas',
                'priority'      => 'medium',
                'status'        => 'investigasi',
                'sentiment'     => 'positif',
                'polres_code'   => 'POLMAN',
                'location'      => 'Kawasan Rekreasi Pantai Bahari, Polewali Mandar',
                'source_media'  => 'Instagram @polres_polman & Tribratanews Sulbar',
                'source_url'    => 'https://tribratanews.sulbar.polri.go.id/polman-pantai-bahari',
                'assigned_unit' => 'Sat Samapta & Satlantas Polres Polman',
                'photo_url'     => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=800&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($queueData as $q) {
            $polres = $polresList->get($q['polres_code']) ?? $polresList->first();
            $issueCode = sprintf('SIM-2026-%05d', $counter++);

            $newIssue = Issue::create([
                'polda_id'         => $sulbar->id,
                'polres_id'        => $polres?->id,
                'created_by'       => $user?->id,
                'report_code'      => $issueCode,
                'title'            => $q['title'],
                'slug'             => \Illuminate\Support\Str::slug($q['title']),
                'summary'          => $q['summary'],
                'location_text'    => $q['location'],
                'occurred_at'      => now()->subHours(rand(2, 24)),
                'category'         => $q['category'],
                'priority'         => $q['priority'],
                'status'           => $q['status'],
                'sentiment'        => $q['sentiment'],
                'source_media'     => $q['source_media'],
                'source_url'       => $q['source_url'],
                'virality_score'   => rand(45, 90),
                'engagement_count' => rand(1200, 25000),
                'assigned_unit'    => $q['assigned_unit'],
                'is_hoax'          => str_contains(strtolower($q['title']), 'hoaks'),
                'created_at'       => now()->subHours(rand(1, 10)),
                'updated_at'       => now()->subMinutes(rand(10, 50)),
            ]);

            // Status histories:
            IssueStatusHistory::create([
                'issue_id'    => $newIssue->id,
                'actor_id'    => $user?->id,
                'from_status' => 'draft',
                'to_status'   => 'submitted',
                'reason'      => 'Laporan diterbitkan oleh operator lapangan untuk verifikasi jenjang Satwil',
                'created_at'  => $newIssue->created_at,
            ]);

            if ($newIssue->status === 'investigasi') {
                IssueStatusHistory::create([
                    'issue_id'    => $newIssue->id,
                    'actor_id'    => $user?->id,
                    'from_status' => 'submitted',
                    'to_status'   => 'investigasi',
                    'reason'      => 'Diverifikasi oleh Kasihumas Polres dan dilanjutkan ke tahap investigasi fakta lapangan',
                    'created_at'  => $newIssue->created_at->addMinutes(30),
                ]);
            }

            // Media attachment
            MediaFile::create([
                'polda_id'      => $sulbar->id,
                'polres_id'     => $polres?->id,
                'issue_id'      => $newIssue->id,
                'uploaded_by'   => $user?->id,
                'title'         => 'Dokumentasi Visual: ' . $q['title'],
                'caption'       => 'Foto alat bukti dokumentasi lapangan di ' . $q['location'],
                'file_type'     => 'photo',
                'file_path'     => 'uploads/private/' . md5($issueCode) . '.jpg',
                'file_url'      => $q['photo_url'],
                'size_bytes'    => rand(1200000, 3800000),
                'mime_type'     => 'image/jpeg',
                'review_status' => 'approved',
                'is_private'    => true,
            ]);
        }

        // 3. SEED ADDITIONAL STANDALONE GALLERY MEDIA FILES (Dokumentasi Resmi Humas)
        $galleryMedia = [
            [
                'title'       => 'Apel Gelar Pasukan Operasi Mantap Praja Siamasei 2026',
                'caption'     => 'Kesiapsiagaan penuh 1.200 personel Polda Sulbar mengamankan tahapan inti pesta demokrasi.',
                'file_type'   => 'photo',
                'polres_code' => 'MAMUJU',
                'url'         => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title'       => 'Patroli Dialogis Sambang Warga Pesisir Teluk Mamuju',
                'caption'     => 'Bhabinkamtibmas menyerap aspirasi nelayan dan mengedukasi kamtibmas kondusif.',
                'file_type'   => 'photo',
                'polres_code' => 'MAMUJU',
                'url'         => 'https://images.unsplash.com/photo-1509440159596-0249088772ff?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title'       => 'Pelatihan Penanganan Konten Disinformasi & Fact-Checking Humas',
                'caption'     => 'Workshop penguatan kapasitas tim siber media jajaran 6 Polres se-Sulawesi Barat.',
                'file_type'   => 'photo',
                'polres_code' => 'MAMUJU',
                'url'         => 'https://images.unsplash.com/photo-1517245386807-bb43f82c33c4?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title'       => 'Bakti Sosial Kesehatan & Pengobatan Gratis Warga Terdampak Banjir',
                'caption'     => 'Dokkes Polda Sulbar menyalurkan bantuan medis dan sembako kepada 350 kepala keluarga.',
                'file_type'   => 'photo',
                'polres_code' => 'MATENG',
                'url'         => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title'       => 'Rekaman CCTV Pos Pantau Simpang Empat Kota Majene',
                'caption'     => 'Arus lalu lintas trans-Sulawesi terpantau ramai lancar dan kondusif.',
                'file_type'   => 'video',
                'polres_code' => 'MAJENE',
                'url'         => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=1200&auto=format&fit=crop&q=80',
            ],
            [
                'title'       => 'Konferensi Pers Pengungkapan Kasus Curanmor Lintas Kabupaten Polman',
                'caption'     => 'Kapolres Polman bersama Kasatreskrim merilis barang bukti 8 unit sepeda motor kepada pemiliknya.',
                'file_type'   => 'photo',
                'polres_code' => 'POLMAN',
                'url'         => 'https://images.unsplash.com/photo-1583863788434-e58a36330cf0?w=1200&auto=format&fit=crop&q=80',
            ],
        ];

        foreach ($galleryMedia as $gm) {
            $polres = $polresList->get($gm['polres_code']) ?? $polresList->first();
            MediaFile::create([
                'polda_id'      => $sulbar->id,
                'polres_id'     => $polres?->id,
                'issue_id'      => null,
                'uploaded_by'   => $user?->id,
                'title'         => $gm['title'],
                'caption'       => $gm['caption'],
                'file_type'     => $gm['file_type'],
                'file_path'     => 'uploads/private/' . md5($gm['title']) . ($gm['file_type'] === 'video' ? '.mp4' : '.jpg'),
                'file_url'      => $gm['url'],
                'size_bytes'    => rand(2500000, 15000000),
                'mime_type'     => $gm['file_type'] === 'video' ? 'video/mp4' : 'image/jpeg',
                'review_status' => 'approved',
                'is_private'    => true,
            ]);
        }
    }
}
