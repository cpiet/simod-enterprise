# 👥 Modul Personalia, Otoritas Hierarki & Matriks Hak Akses (RBAC)

Modul **Personalia & Petugas** (`/personalia`) adalah pusat tata kelola sumber daya manusia, kredensial dinas, dan pengawasan otoritas operasional seluruh personel humas POLRI di lingkungan Mabes Polri, Polda Jajaran, hingga Polres/Satker kewilayahan. Modul ini terintegrasi langsung secara bi-directional dengan modul **Hak Akses & Matriks RBAC** (`/roles`).

---

## 🏛️ 1. Hierarki Otoritas Personel

Aplikasi SIMOD Enterprise menerapkan sistem pembagian peran (*role-based access control*) berlapis yang merefleksikan rantai komando kedinasan POLRI:

| Kode Role | Nama Jabatan Kedinasan | Tingkat Otoritas | Cakupan Akses | Modul Aktif |
|:---|:---|:---|:---|:---:|
| `kadiv_humas` | **Kadiv Humas Polri** | Pimpinan Tertinggi Mabes | Nasional (Seluruh Polda & Satwil) | 21 / 20 |
| `super_admin_mabes` | **Super Admin Mabes** | Administrator Sistem Pusat | Nasional (Sistem & Keamanan Penuh) | 21 / 20 |
| `polda_leader` | **Kabid Humas Polda** | Pimpinan Satwil Daerah | Regional Polda & Seluruh Polres Jajaran | 16 / 20 |
| `verifikator` | **Kasubbid / Verifikator** | Validator Konten & Fakta | Regional Polda (Verifikasi Isu & Rilis) | 12 / 20 |
| `analis` | **Analis Intelijen Media** | Pengolah Data & Opini | Regional / Nasional (Radar & Analisis AI) | 8 / 20 |
| `operator` | **Operator Satwil / Polres** | Pelaksana Lapangan | Lokal Polres / Satker Tertentu | 7 / 20 |

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

## 🧩 3. Pemetaan 20 Modul Operasional pada 4 Pilar

Setiap role memiliki hak akses terdefinisi pada **20 kunci modul** (`RolePermission::defaultModules()`) yang dikelompokkan ke dalam 4 pilar utama:

```
├── 1. KOMANDO & INTELIJEN (6 Modul)
│   ├── dashboard_view              -> Command Center & Situasi Wilayah (EWS, peta)
│   ├── media_intelligence_view     -> Radar Media Online (kliping RSS)
│   ├── media_intelligence_ai       -> Sinkron berita & analisis otomatis AI, naskah WhatsApp
│   ├── media_intelligence_manage   -> Master feed RSS (admin pemeliharaan)
│   ├── social_intelligence_view    -> Radar Media Sosial, tagar, KOL
│   └── social_intelligence_sync    -> Sinkron Apify & pengaturan medsos
│
├── 2. MANAJEMEN ISU & TUGAS (5 Modul)
│   ├── issues_view                 -> Pemantauan daftar isu publik
│   ├── issues_create               -> Input laporan lapangan & bukti
│   ├── issues_verify               -> Verifikasi fakta & klarifikasi hoaks
│   ├── tasks_disposition           -> Surat perintah & disposisi tugas
│   └── tasks_execute               -> Laporan pelaksanaan tugas personel
│
├── 3. PUBLIKASI & DOKUMENTASI (3 Modul)
│   ├── publications_manage         -> Publikasi rilis multi-kanal
│   ├── gallery_access              -> Galeri dokumentasi & arsip bukti
│   └── reports_export              -> Laporan Anev & ekspor CSV/PDF
│
└── 4. ADMINISTRASI & SISTEM (6 Modul)
    ├── personalia_manage           -> Manajemen akun personalia & Satker
    ├── polda_settings              -> Konfigurasi master satwil (Polda/Polres)
    ├── security_center             -> Keamanan siber, audit log, Mesin Intelijen AI
    ├── database_backup             -> Backup & restore basis data
    ├── maintenance_ops             -> Pemeliharaan, news cleanup & RSS sync
    └── roles_matrix_manage         -> Otoritas & matriks hak akses (RBAC)
```

> **Penegakan server-side:** matriks ini ditegakkan oleh middleware `module:*` (`EnsureModulePermission`) pada seluruh rute modul di `routes/web.php`, selain menentukan visibilitas menu (`MenuItem::getNavigationForUser`) dan `auth.permissions` di frontend. Super Admin/Kadivhumas selalu lolos; peran lain harus memiliki minimal satu kunci yang disyaratkan rute. Perubahan matriks di `/roles` berlaku seketika. Pemetaan rute→kunci mengikuti `permission_required` pada menu (mis. `/personalia` → `personalia_manage`, `/security`, `/audit-logs`, `/settings/ai` → `security_center`). Pelanggaran → HTTP 403 (JSON untuk request AJAX).

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
  - Kolom Otoritas Modul (`X / 20 Modul Aktif` dengan tombol *Inspect* cepat).
  - Kolom Satwil/Polres Penugasan.
  - Kolom Kredensial Login (Username & Email dinas).
  - Kolom Status Switch (Aktif / Nonaktif dengan konfirmasi proteksi).
  - Tombol Aksi: Inspeksi Otoritas, Edit Data, Reset Password Cepat, dan Hapus Personel.
- **5 Modal Interaktif**:
  1. *Modal Tambah Personel*: Dilengkapi preview live modul yang dapat diakses sesuai role yang dipilih, serta pembuatan otomatis username dari nama.
  2. *Modal Edit Personel*: Form pembaruan komprehensif pangkat, satwil, role, dan kredensial.
  3. *Modal Inspeksi Otoritas (RBAC)*: Menampilkan ke-20 modul lengkap yang dikelompokkan dalam 4 pilar beserta indikator hijau (Aktif) atau abu-abu (Tidak Ada Akses).
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

