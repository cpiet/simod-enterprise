# 🧹 24. Penyederhanaan Alur Isu & Tugas, Publikasi & Dokumentasi, serta Administrasi & Sistem

Dokumen ini mencatat perapihan alur dan desain tiga pilar: **Manajemen Isu & Tugas**, **Publikasi & Dokumentasi**, dan **Administrasi & Sistem**. Prinsip: *tampilkan hanya yang berfungsi*, satu langkah berikutnya yang jelas per tahap, aksi sekunder masuk menu `⋯`, dan angka selalu nyata (tidak ada nilai bawaan karangan).

---

## 1. Manajemen Isu & Tugas

### 1.1. Alur resmi tunggal
`draft → monitoring → investigasi → klarifikasi → selesai → arsip`

Sumber tunggal di frontend: [`resources/js/utils/issueFlow.js`](../resources/js/utils/issueFlow.js) (label, warna, kelompok, prioritas, status tugas). Di backend: `Issue::STATUS_GROUPS` dan `Issue::statusesForFilter()`. Status lama (`submitted`, `unit_verified`, `polda_verified`, `in_progress`, `resolved`, `closed`) dipetakan ke tahap setara.

| Tahap | Label di UI | Tindakan berikutnya (tombol utama) |
|:---|:---|:---|
| `draft` | Draf | **Ajukan ke Verifikasi** → `monitoring` |
| `monitoring` | Perlu Telaah | **Verifikasi Laporan** → `investigasi` (atau *Kembalikan* untuk revisi) |
| `investigasi` | Dalam Penanganan | **Rilis Klarifikasi / Hoaks** → `klarifikasi`; tombol sekunder **Disposisi Tugas** |
| `klarifikasi` | Klarifikasi | **Selesaikan Kasus** → `selesai` |
| `selesai` | Selesai | **Arsipkan** → `arsip` |

Komponen [`IssueStepper.vue`](../resources/js/Components/IssueStepper.vue) menampilkan posisi laporan pada 5 tahap di halaman detail.

### 1.2. Bug alur yang ditemukan dan diperbaiki
| # | Masalah | Perbaikan |
|:--|:---|:---|
| 1 | Formulir **Input Laporan** mengirim `status = submitted` (status lama). Laporan tersimpan di luar `monitoring`, sehingga **tidak pernah muncul di Antrean Verifikasi** dan tombol Verifikasi tidak tampil. | `store()` hanya menerima dua status awal: `draft` atau `monitoring`. Formulir kini mengirim `monitoring`. |
| 2 | Filter status pada **Daftar Laporan** memakai nilai status lama sehingga memilih "Sedang Ditangani" dsb. tidak menemukan data riil. | Filter memakai **kelompok status** (`pending`, `active`, `clarified`, `done`, `archived`, `draft`) dan hitungan per kelompok dari server. |
| 3 | Filter **kategori** berisi nilai bertanda emoji yang tidak sama dengan kategori di basis data. | Pilihan kategori diambil dari kategori yang benar-benar ada (`categoryOptions`). |
| 4 | Aksi **Verifikasi** pada item `investigasi` di antrean tidak mengubah status (tetap `investigasi`) — laporan tidak pernah keluar dari antrean. | Item `investigasi` memakai **Tindak Lanjut** (ke detail: klarifikasi/penyelesaian). Verifikasi cepat hanya untuk `monitoring`. |
| 5 | `verify` menerima `target_status` sembarang dan dari status apa pun. | Divalidasi: `investigasi`/`klarifikasi`, hanya dari `monitoring`/`investigasi`. |
| 6 | **Draf** tidak punya jalan keluar. | Endpoint baru `POST /issues/{id}/submit` (izin `issues_create`) → `monitoring`. |
| 7 | Laporan lapangan diisi `virality_score`/`engagement_count` **acak**. | Diisi 0. |
| 8 | **Disposisi Tugas** tidak memiliki filter status yang bekerja, tanpa paginasi, tanpa empty state, label mentah (`in_progress`). | Tab status (Semua/Berjalan/Selesai) + chip Mendesak, paginasi, penanda *lewat tenggat*, tautan ke laporan terkait, riwayat progres dapat dilipat. |

### 1.3. Ringkasan perubahan tampilan
- **Daftar Laporan**: judul + satu aksi utama (*Buat Laporan*, sesuai izin) + menu `⋯` (Ekspor CSV); tab kelompok status dengan hitungan menggantikan 5 kartu KPI dan dropdown status; filter lanjutan di panel **Filter**; kartu: *Buka* + `⋯` (Klarifikasi cepat).
- **Detail Laporan**: 6 tombol sejajar diganti **satu langkah berikutnya** + (bila relevan) satu tombol sekunder + menu **Lainnya**; stepper tahapan.
- **Antrean Verifikasi**: 4 kartu KPI diganti 2 tab tahap (*Perlu Telaah*, *Dalam Penanganan*) + chip *Prioritas Kritis*.
- **Input Laporan**: bar aksi ganda (atas dan bawah) menjadi satu bar **sticky**; sumber media/medsos dilipat (otomatis terbuka bila terisi dari eskalasi Radar).

---

## 2. Publikasi & Dokumentasi

| Halaman | Masalah | Perbaikan |
|:---|:---|:---|
| **Publikasi** | Setiap konten berlabel **"Terbit Resmi"** padahal draf rilis dari detail isu berstatus `pending_approval` dan tidak ada cara menyetujuinya. Filter hanya 3 dari 7 kanal. | Status persetujuan nyata (*Menunggu Persetujuan / Terbit / Ditolak*) dengan hitungan; tombol **Setujui & Terbitkan** dan **Tolak** (catatan wajib) lewat `POST /publications/{id}/approve\|reject` (izin `publications_manage`, tenant-aman); filter 7 kanal lengkap dengan hitungan. |
| **Galeri** | "Unggah berkas" hanya menyimpan **tautan**; ukuran berkas **acak**; opsi *Privat* diabaikan (selalu privat); angka bawaan palsu (14,8 MB, "28 Okt 2026"); gambar stok Unsplash sebagai pengganti; nama "Polda Sulbar" tertanam; klaim "Tersimpan Aman (AES)" tanpa dasar. | Unggah **berkas nyata** (JPG/PNG/WEBP/MP4/PDF, maks. 20 MB; ukuran & tipe dari berkas) atau tautan; `is_private` dihormati; semua nilai bawaan palsu dihapus; strip statistik nyata. |
| **Laporan & Anev** | "Tingkat SLA" sebenarnya rasio selesai/total dan tampil **100%** saat data kosong; sentimen satwil bawaan **85%**; kategori tetap (6 nama) tidak cocok dengan data; ringkasan eksekutif & pesan WA bertuliskan "SANGAT KONDUSIF"/"berjalan optimal" secara otomatis. | Angka `null` (tampil `—`) bila belum ada data; dinamai **Tingkat penyelesaian**; kategori dinamis dari data; ringkasan WA hanya berisi angka nyata; teks klaim dihapus. |

---

## 3. Administrasi & Sistem

### 3.1. Halaman yang **dihapus** (tidak berfungsi)
| Halaman | Alasan |
|:---|:---|
| **Konfigurasi Mail (SMTP)** | Murni mock di sisi klien: tombol "Simpan" hanya menampilkan notifikasi, tidak ada penyimpanan maupun pengiriman email. |
| **Mode Pemeliharaan** | Mock: tidak ada endpoint, tidak memengaruhi aplikasi. |

Rute, metode controller, halaman Vue, entri menu (`menu_items`), kunci RBAC `mail_settings`, dan baris menu lama pada instalasi berjalan (migrasi `2026_11_05_120000_...`) ikut dibersihkan. Sub-tab Keamanan & Pemeliharaan kini **8**: Keamanan, Hak Akses, Manajemen Menu, Audit Log, Backup Data, Pembersihan Berita, Sinkron RSS, Mesin AI. Modul RBAC menjadi **20 kunci**.

### 3.2. Pusat Keamanan: hanya yang benar-benar diterapkan
Sebelumnya 8 parameter tersimpan ke basis data tetapi **tidak satu pun dipakai aplikasi** (Turnstile, PIN, IP whitelist berisi IP contoh, "media privat", skor keamanan berbasis tombol). Kini hanya tiga parameter, semuanya **ditegakkan**:

| Parameter | Penegakan |
|:---|:---|
| `max_login_attempts`, `lockout_minutes` | `AuthController::login` memakai `RateLimiter` per akun + IP; akun terkunci menolak walaupun kata sandi benar. Diuji. |
| `session_timeout_minutes` | Middleware [`EnforceSessionTimeout`](../app/Http/Middleware/EnforceSessionTimeout.php) (`idle`) mengakhiri sesi yang tidak aktif. Diuji. |

Tabel "aktivitas keamanan" memakai nama aksi audit yang benar (`auth.login`, `auth.login_failed`, `auth.logout`, `auth.password_changed`, …).

### 3.3. Profil Saya
Sebelumnya "Ganti Kata Sandi" dan "PIN Dinas" hanya menampilkan notifikasi tanpa menyimpan apa pun. Kini `POST /profile/password` (throttle 6/menit) mewajibkan kata sandi saat ini, minimal 10 karakter, konfirmasi, dan berbeda dari yang lama; dicatat di audit log. Halaman menampilkan identitas nyata akun.

### 3.4. Konsistensi
- Bar sub-modul administrasi menjadi **satu baris** yang dapat digulir (sebelumnya grid 10 tombol dua baris + lencana).
- Menu pengguna menampilkan **nama akun yang sedang login** (sebelumnya nama pejabat contoh sesuai peran). *Pratinjau tampilan peran* hanya muncul untuk Super Admin dan diberi label jujur ("hanya menu").

---

## 4. Pengujian
`php artisan test` → **142 passed (853 assertions)**; `npm.cmd run build` tanpa galat. Berkas baru: `AdminSystemEnforcementTest` (lockout, idle timeout, ganti kata sandi, halaman terhapus).

## 5. Belum dikerjakan / keputusan
- Halaman **Personalia, Pengaturan Satwil, Backup, Pembersihan Berita, Sinkron RSS, Hak Akses, Manajemen Menu, Mesin AI** berfungsi penuh dan dipertahankan; header masing-masing belum diseragamkan ke pola baru (hanya bar sub-modul yang seragam).
- Fitur yang dihapus (IP whitelist, Turnstile, PIN, SMTP) dapat dibangun kembali **bersama penegakannya** bila memang dibutuhkan.
- Pengecekan visual di browser (Dark/Light, ponsel) belum dilakukan.
