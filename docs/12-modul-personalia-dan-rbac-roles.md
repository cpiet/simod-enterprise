# 👥 Modul Personalia, Otoritas Hierarki & Matriks Hak Akses (RBAC)

Modul **Personalia & Petugas** (`/personalia`) adalah pusat tata kelola sumber daya manusia, kredensial dinas, dan pengawasan otoritas operasional seluruh personel humas POLRI di lingkungan Mabes Polri, Polda Jajaran, hingga Polres/Satker kewilayahan. Modul ini terintegrasi langsung secara bi-directional dengan modul **Hak Akses & Matriks RBAC** (`/roles`).

---

## 🏛️ 1. Hierarki Otoritas Personel

Aplikasi SIMOD Enterprise menerapkan sistem pembagian peran (*role-based access control*) berlapis yang merefleksikan rantai komando kedinasan POLRI:

| Kode Role | Nama Jabatan Kedinasan | Tingkat Otoritas | Cakupan Akses | Modul Aktif |
|:---|:---|:---|:---|:---:|
| `kadiv_humas` | **Kadiv Humas Polri** | Pimpinan Tertinggi Mabes | Nasional (Seluruh Polda & Satwil) | 17 / 17 |
| `super_admin_mabes` | **Super Admin Mabes** | Administrator Sistem Pusat | Nasional (Sistem & Keamanan Penuh) | 17 / 17 |
| `polda_leader` | **Kabid Humas Polda** | Pimpinan Satwil Daerah | Regional Polda & Seluruh Polres Jajaran | 15 / 17 |
| `verifikator` | **Kasubbid / Verifikator** | Validator Konten & Fakta | Regional Polda (Verifikasi Isu & Rilis) | 13 / 17 |
| `analis` | **Analis Intelijen Media** | Pengolah Data & Opini | Regional / Nasional (Radar & Analisis AI) | 11 / 17 |
| `operator` | **Operator Satwil / Polres** | Pelaksana Lapangan | Lokal Polres / Satker Tertentu | 8 / 17 |

---

## 🛡️ 2. Aturan Keamanan & Proteksi Otoritas Kedinasan

Untuk menjaga integritas dan stabilitas sistem komando, aturan proteksi diterapkan secara ketat di tingkat controller (`SystemModuleController.php`):

1. **Proteksi Akun Master (*Protected Master Accounts*)**:
   - Akun utama sistem (`id: 1`, `username: kadivhumas`, `username: superadmin`, `username: cpiet`) berstatus kebal (*immutable*).
   - Akun master **tidak dapat dihapus** (`deleteUser`) dan **tidak dapat dinonaktifkan** (`toggleUserStatus`) untuk mencegah *accidental lockout*.
2. **Pencegahan Bunuh Diri Sesi (*Anti-Self Destruction & Anti-Self Demotion*)**:
   - Pengguna yang sedang login **tidak dapat menghapus akunnya sendiri**.
   - Pengguna yang sedang login **tidak dapat menonaktifkan status aktifnya sendiri**.
   - Pengguna tidak dapat mendemosi atau mencabut role kedinasan miliknya sendiri.
3. **Pemisahan Otoritas Wilayah (*Boundary Check*)**:
   - Administrator tingkat Polda / Satwil **dilarang keras** membuat akun baru dengan role Mabes (`kadiv_humas`, `super_admin_mabes`).
   - Administrator tingkat Polda **dilarang keras** mengedit, mendemosi, atau menghapus personel Mabes Polri.
4. **Audit Trail Otomatis**:
   - Seluruh mutasi data personel (Tambah, Edit, Toggle Status, Reset Password, Hapus) dicatat ke dalam tabel `audit_logs` secara otomatis dengan menyertakan IP Address, User Agent, dan metadata perubahan.

---

## 🧩 3. Pemetaan 17 Modul Operasional pada 4 Pilar

Setiap role memiliki hak akses terdefinisi pada 17 modul sistem yang dikelompokkan ke dalam 4 pilar utama:

```
├── 1. KOMANDO & INTELIJEN (6 Modul)
│   ├── command_center      -> Dashboard Eksekutif & Early Warning System
│   ├── media_intelligence  -> Radar Media Online & Kliping Berita 29 RSS
│   ├── social_intelligence -> Radar Media Sosial & KOL Tracker
│   ├── ai_briefing         -> Analisis Intelijen AI & Executive Briefing
│   ├── ews_alert           -> Notifikasi Eskalasi Krisis Kamtibmas
│   └── whatsapp_draft      -> Generator Naskah Briefing WhatsApp Pimpinan
│
├── 2. MANAJEMEN ISU & TUGAS (5 Modul)
│   ├── issue_monitoring    -> Pemantauan Daftar Isu Publik
│   ├── issue_input         -> Formulir Input Laporan Lapangan
│   ├── verification_queue  -> Antrean Verifikasi Fakta & Cek Hoaks
│   ├── task_disposition    -> Surat Perintah & Disposisi Tugas Personel
│   └── task_recap          -> Rekapitulasi & Progres Kinerja Anggota
│
├── 3. PUBLIKASI & DOKUMENTASI (3 Modul)
│   ├── multi_channel_pub   -> Publikasi Multi-Kanal (Tribratanews, Medsos)
│   ├── secure_gallery      -> Galeri Dokumentasi Foto & Video Aman
│   └── reports_anev        -> Laporan & Analisa Evaluasi (Anev) Berkala
│
└── 4. ADMINISTRASI & SISTEM (3 Modul)
    ├── personalia          -> Manajemen Personel & Akun Dinas
    ├── settings_polda      -> Konfigurasi Satwil Polda & Polres
    └── system_maintenance  -> Keamanan, Hak Akses, Backup DB, & Pemeliharaan
```

---

## 💻 4. Fitur Interaktif pada Antarmuka `/personalia`

Halaman `resources/js/Pages/Personalia/Index.vue` dilengkapi dengan:
- **Tautan Cepat Matriks Hak Akses**: Tombol `🔗 Matriks Hak Akses (RBAC) &rarr;` di pojok kanan atas menuju `/roles`.
- **4 KPI Ringkasan Personel**:
  1. *Total Personel Terdaftar*
  2. *Komando & Staf Mabes*
  3. *Bidhumas Polda*
  4. *Operator Satwil & Polres*
- **Bilah Filter Terpadu**: Filter instan berdasarkan Role, Wilayah Satker/Polres, Status Akun (Aktif/Nonaktif), dan Kolom Pencarian realtime.
- **Tabel Personel Komprehensif**:
  - Kolom Profil (Avatar inisial pangkat, Nama, NRP, Pangkat/Jabatan).
  - Kolom Role & Badge Hak Akses.
  - Kolom Otoritas Modul (`X / 17 Modul Aktif` dengan tombol *Inspect* cepat).
  - Kolom Satwil/Polres Penugasan.
  - Kolom Kredensial Login (Username & Email dinas).
  - Kolom Status Switch (Aktif / Nonaktif dengan konfirmasi proteksi).
  - Tombol Aksi: Inspeksi Otoritas, Edit Data, Reset Password Cepat, dan Hapus Personel.
- **5 Modal Interaktif**:
  1. *Modal Tambah Personel*: Dilengkapi preview live modul yang dapat diakses sesuai role yang dipilih, serta pembuatan otomatis username dari nama.
  2. *Modal Edit Personel*: Form pembaruan komprehensif pangkat, satwil, role, dan kredensial.
  3. *Modal Inspeksi Otoritas (RBAC)*: Menampilkan ke-17 modul lengkap yang dikelompokkan dalam 4 pilar beserta indikator hijau (Aktif) atau abu-abu (Tidak Ada Akses).
  4. *Modal Reset Password Dinas*: Form ganti password cepat minimal 6 karakter.
  5. *Modal Konfirmasi Hapus Akun*: Dialog konfirmasi aman dengan verifikasi akun master.

---

## 🧪 5. Verifikasi Fungsional & Testing

Modul ini diverifikasi melalui test suite otomatis `tests/Feature/PersonaliaAuthorityTest.php` dengan 6 pengujian dan 18 assertions:

```bash
# Menjalankan test suite Personalia & Otoritas RBAC
php artisan test --filter=PersonaliaAuthorityTest
```

Cakupan pengujian:
1. `test_can_update_personnel_data_and_role`: Memastikan update data dan role personel berhasil.
2. `test_can_toggle_personnel_status`: Memastikan aktivasi dan deaktivasi status akun bekerja lancar.
3. `test_cannot_deactivate_self_or_master_accounts`: Memastikan pencegahan deaktivasi diri sendiri dan akun master (403 Forbidden).
4. `test_can_reset_personnel_password`: Memastikan reset password dinas berhasil dan password terenkripsi bcrypt.
5. `test_can_delete_personnel_with_protections`: Memastikan penghapusan akun non-master berjalan dan akun master terlindungi.
6. `test_regional_admin_cannot_promote_to_mabes_or_edit_mabes_personnel`: Memastikan pembatasan wilayah (admin polda tidak bisa membuat/mengedit akun Mabes).
