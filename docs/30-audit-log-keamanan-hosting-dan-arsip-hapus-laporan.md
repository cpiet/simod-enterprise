# 🛡️ 30. Audit Log, Pusat Keamanan, Kesiapan Hosting, serta Arsip & Hapus Laporan

Dokumen ini mencatat perubahan 6 November 2026 pada pilar **Keamanan & Pemeliharaan** dan modul **Laporan Polres & Isu**. Baseline setelah perubahan: **228 tests, 1727 assertions**, 129 rute, 39 tabel.

---

## 1. Hasil audit halaman Keamanan & Pemeliharaan

| Halaman | Temuan | Perbaikan |
|:---|:---|:---|
| **Audit Log** `/audit-logs` | Banyak tiruan: total "1842" bawaan, "Bulan Berjalan", "Terenkripsi SHA-256", "0 Insiden · 100% Terproteksi WAF", "Sinkron NTP Mabes Polri", latensi "14.8 ms", versi "MySQL 8.0", daftar centang SSL/WAF selalu hijau. Status selalu "SUCCESS" walau gagal masuk. Filter tipe (`LOGIN`, `ISSUE`, …) tidak cocok dengan kode aksi nyata sehingga **tidak pernah menemukan apa pun**. Tombol "Export CSV" mengekspor laporan isu, bukan log. Kueri `orWhereNull` tanpa pengelompokan membocorkan log Polda lain saat filter dipakai. | Ditulis ulang: semua angka dari basis data, filter dinamis, ekspor CSV log yang benar, tenant aman (lihat §2). |
| **Pusat Keamanan** `/security` | "Aktivitas keamanan terbaru" dibatasi 10 baris, tanpa filter/bersihkan, dan **tidak dibatasi tenant** (personel Polda A melihat peristiwa Polda B). | Dibatasi tenant, dapat disaring, ada tombol Bersihkan (§3). |
| **Hak Akses** `/roles` | Menyimpan kunci peran/modul sembarang dari klien; tenant sasaran dibaca dari `?polda=`. | Hanya peran dan modul yang dikenal diterima (nilai dipaksa boolean); tenant dari sesi server. Diuji di `RolesMatrixValidationTest`. |
| **Backup Data** | Fallback palsu `1.86 MB`, `28 Tabel`, "SHA-256 Valid", "Kompresi Gzip **Terenkripsi**" (gzip tidak mengenkripsi). | Nilai nyata atau "—"; kartu integritas menampilkan jumlah cadangan yang punya checksum tercatat. |
| **Mesin Intelijen AI** | Klaim "Kredensial dilindungi enkripsi AES-256", padahal kunci API disimpan apa adanya di basis data. | Teks diganti dengan klaim yang benar. **Temuan terbuka:** lihat §6. |
| **Manajemen Menu** | Angka bawaan palsu "4 Pilar" dan "10 Tab" saat data kosong. | `?? 0` dari data. |

---

## 2. Audit Log baru

Kode: [`AuditLogController`](../app/Http/Controllers/AuditLogController.php), model [`AuditLog`](../app/Models/AuditLog.php), tampilan `resources/js/Pages/AuditLogs/Index.vue`.

- **Ringkasan nyata:** total log, 24 jam terakhir, gagal/ditolak 24 jam (kartu dapat diklik sebagai filter), pengguna aktif 24 jam, retensi.
- **Filter dinamis dan sungguhan:** kata kunci (aksi, IP, objek, rincian, nama pengguna), kategori (Masuk & Keluar, Akun & Hak Akses, Laporan Isu, Radar & Analisis, Sistem), kode aksi, pengguna, rentang tanggal, hasil (berhasil/gagal). Daftar kategori, aksi, dan pengguna dibentuk dari **isi log** beserta jumlahnya. URL menyimpan filter.
- **Hasil nyata:** `GAGAL` untuk aksi `*_failed`, `*_denied`, `*_locked`, `*_blocked`; selain itu `BERHASIL`.
- **Rincian:** baris dapat dibuka untuk melihat objek, perangkat, dan metadata. Nilai sensitif (`password`, `token`, `api_key`, `secret`, …) **disamarkan** di layar dan CSV.
- **Waktu:** dibentuk server sesuai zona waktu Polda aktif (WIB pada mode Nasional), bukan zona waktu browser.
- **Ekspor CSV:** mengikuti filter, UTF-8 dengan BOM (terbaca Excel), sel berawalan `= + - @` dinetralkan (anti CSV injection), maksimum 100.000 baris, dicatat sebagai `audit.export`.
- **Cakupan tenant** (`AuditLog::scopeVisibleTo`): personel daerah hanya Polda-nya; Mabes mode Nasional melihat semuanya; Mabes di satu Polda melihat Polda itu + log tingkat sistem.

### 2.1. Bersihkan log

Tombol **Bersihkan log** (Audit Log) dan **Bersihkan** (Pusat Keamanan) membuka dialog [`AuditPurgeDialog`](../resources/js/Components/AuditPurgeDialog.vue):

| Pilihan | Keterangan | Konfirmasi |
|:---|:---|:---|
| Hapus yang sudah lama | > 7 / 30 / 90 / 180 / 365 hari | tombol biasa |
| Sesuai filter aktif | hanya yang tampil di daftar (muncul bila ada filter) | ketik `HAPUS` |
| Semua log | mengosongkan seluruhnya | ketik `HAPUS` |

Pratinjau jumlah dan rentang waktu yang akan terhapus dihitung server **sebelum** Anda memastikan (`GET /audit-logs/purge-preview`). Tersedia "Unduh CSV dulu". Penghapusan per potongan 2.000 baris agar tidak mengunci tabel besar di hosting.

**Jejak pembersihan tidak pernah hilang:** aksi `audit.purged` dan `audit.auto_pruned` dilindungi dari semua jenis pembersihan, dan setiap pembersihan menambah catatan baru berisi siapa, berapa, mode, dan kriteria.

### 2.2. Retensi otomatis (bawaan 180 hari)
Pilihan di **Retensi otomatis** (0 / 30 / 60 / 90 / 180 / 365 / 730 hari; bawaan **180**; 0 = simpan selamanya) disimpan di `security_settings.audit_retention_days`. Perintah `php artisan audit:prune` (dijadwalkan harian 02:30 di `routes/console.php`) memangkas log yang lebih lama. Seperti semua tugas terjadwal, ia memerlukan cron `schedule:run` di hosting.

---

## 3. Pusat Keamanan: aktivitas keamanan

Tab **Semua / Gagal masuk / Berhasil masuk / Perubahan pengaturan & akun**, empat angka nyata (total, gagal 24 jam, berhasil masuk 24 jam, perubahan 24 jam), "Tampilkan lebih banyak" (15 → 50 → 100), tautan ke Audit Log lengkap, dan tombol **Bersihkan** (lingkup `security`: hanya peristiwa autentikasi dan akun/hak akses). Label dan hasil berasal dari server.

---

## 4. Kesehatan server & kesiapan hosting

Tab kedua di Audit Log memakai [`SystemHealth`](../app/Services/SystemHealth.php): semua diukur atau diperiksa **saat halaman dibuka**, tanpa klaim statis.

- **Terukur:** versi PHP/Laravel, lingkungan, memori puncak, mesin dan versi basis data, status koneksi, latensi kueri (rata-rata 3 kali), driver sesi/cache/antrean, ruang disk, jumlah tugas antrean gagal.
- **Daftar kesiapan hosting** (siap / perlu perhatian / kritis, dengan saran): HTTPS, `APP_DEBUG`, `APP_ENV`, cookie sesi, kesesuaian `APP_URL` dengan alamat situs, folder `storage` dan `bootstrap/cache` dapat ditulis, ekstensi PHP yang dibutuhkan, penjadwal (cron) berjalan, cadangan terakhir, parameter penguncian login, antrean gagal, token API ingest.

Pada komputer lokal beberapa butir bernilai "perlu perhatian" (HTTP, debug, `APP_ENV=local`); itu wajar dan harus hijau di hosting. Jalankan halaman ini setelah deploy sebagai pemeriksaan akhir.

---

## 5. Laporan Polres & Isu: arsip, hapus, pulihkan, aksi massal

Kode: [`IssueController`](../app/Http/Controllers/IssueController.php), migrasi `2026_11_06_140000`, `Issues/Index.vue`, `Issues/Show.vue`, [`ConfirmDialog.vue`](../resources/js/Components/ConfirmDialog.vue).

| Tindakan | Perilaku | Izin |
|:---|:---|:---|
| **Arsipkan** | Status menjadi `arsip`; tahap sebelumnya disimpan (`status_before_archive`) beserta `archived_at`. Draf dan yang sudah arsip tidak dapat diarsipkan. Alasan opsional tercatat di riwayat status. | `issues_verify` |
| **Pulihkan** | Dari Arsip: kembali ke tahap semula. Dari Sampah: laporan kembali ke daftar. | `issues_verify` |
| **Hapus** | **Hapus lunak** (`deleted_at`, `deleted_by`): laporan pindah ke **Sampah**, hilang dari seluruh daftar, KPI, dan Command Center, tetapi dapat dipulihkan. | pengelola (`issues_verify`); pembuat hanya **drafnya sendiri** |
| **Hapus permanen** | Hanya dari Sampah, wajib mengetik `HAPUS`; foto bukti ikut dihapus. | `issues_verify` |
| **Aksi massal** | Pilih beberapa laporan: Arsipkan, Pulihkan, Hapus, Hapus permanen, Ubah prioritas (maks. 100 per aksi, `POST /issues-bulk`). Yang tidak memenuhi syarat atau di luar wilayah **dilewati dan dilaporkan** ("2 diarsipkan. Dilewati: 1 (masih draf), 1 (di luar wilayah Anda)"). | `issues_verify` |

Perubahan daftar:
- **Semua** tidak lagi memuat laporan arsip; **Arsip** (selalu tampil) dan **Sampah** (hanya pengelola) punya tab dan hitungan sendiri.
- Kotak centang per kartu, "Pilih semua di halaman", bilah aksi massal yang menempel di atas daftar, menu ⋯ per kartu sesuai wewenang (flag `can_archive`, `can_restore`, `can_delete`, `can_force_delete` dihitung server).
- Filter lanjutan menambah **Urutan** (terbaru, terlama, prioritas tertinggi dulu, terakhir diperbarui) dan **jumlah per halaman** (15/30/50).
- Dialog konfirmasi menjelaskan akibat tindakan; tombolnya bernama jelas ("Pindahkan ke Sampah"), bukan "OK".
- Halaman detail: menu Lainnya memuat Arsipkan (tahap mana pun), Pulihkan, Hapus; laporan di Sampah menampilkan banner dengan tombol Pulihkan.
- Semua tindakan tercatat di Audit Log (`issue.archive`, `issue.restore`, `issue.delete`, `issue.delete_permanent`, `issue.bulk`) dan di riwayat status laporan.
- Relasi `Task`, `Publication`, `MediaFile` ke laporan memakai `withTrashed()` sehingga halaman lain tidak error bila laporannya di Sampah.

Test: `IssueArchiveAndTrashTest` (11), `AuditLogManagementTest` (14), `RolesMatrixValidationTest` (2).

---

## 5b. Modul Penjadwal (cron)

Tab **Penjadwal** di Audit Log (dan banner kuning di atas halaman bila tidak aktif) menampilkan status nyata:

- **Aktif / Tidak aktif:** setiap kali cron memanggil `schedule:run`, tugas `simod-heartbeat` menulis "detak" ke cache. Aktif bila detak terakhir kurang dari 3 menit; selain itu tidak aktif.
- **Tugas otomatis:** sinkron RSS, cadangan basis data, pemangkasan log audit, pembersihan berita lama, masing-masing dengan status *Berjalan / Terlambat / Gagal / Belum pernah / Nonaktif* dan waktu terakhir berjalan.
- **Saran pemasangan:** perintah siap salin untuk Windows (Task Scheduler) dan Linux/hosting (cron, mis. hPanel → Cron Jobs), sudah berisi lokasi proyek ini.
- Definisi tugas ada di satu tempat, [`App\Support\ScheduledTasks`](../app/Support/ScheduledTasks.php), dipakai oleh `routes/console.php` dan halaman ini. **Tugas terjadwal baru wajib ditambahkan di sana** agar tampil dan terpantau. Butir "Penjadwal" pada kesiapan hosting kini memakai detak ini.
- Test: `SchedulerStatusTest` (6).

---

## 6. Temuan terbuka (perlu keputusan)

- **Kunci API (Gemini, Apify) tersimpan apa adanya di basis data** (`ai_settings`), tanpa enkripsi. Sebelum hosting publik, disarankan memakai cast `encrypted` Laravel (butuh migrasi data lama dan `APP_KEY` yang dijaga). Klaim "AES-256" sudah dihapus dari antarmuka.
- Laporan tidak memiliki **kedaluwarsa otomatis Sampah**; laporan yang dihapus tetap di Sampah sampai dihapus permanen.
- Cakupan aksi yang ditulis ke Audit Log masih terbatas (mis. verifikasi, tugas, dan publikasi belum semuanya tercatat); halaman akan menampilkan apa pun yang tercatat.
