# 09 — Katalog Lengkap 21 Modul & Halaman SIMOD Enterprise

Dokumen ini mencatat pemetaan lengkap seluruh modul/halaman pada **SIMOD Enterprise Multi-Tenant** yang mengadopsi standar kepangkatan, hierarki satwil, dan tata kelola informasi publik Polri. Rute, controller, dan view diverifikasi terhadap `routes/web.php` dan `resources/js/Pages` (lihat [doc 22](22-peta-kode-dan-sumber-kebenaran.md)).

> Nama berkas dokumen ini mempertahankan sufiks `19-modul` agar tautan lama tidak putus; isi yang berlaku adalah **21 modul & halaman**.

---

## 1. Matriks 21 Modul Terintegrasi

| No | Modul | Rute URL | Pengontrol (Controller) | Tampilan (Inertia Vue) | Pilar Hierarki |
|:---|:---|:---|:---|:---|:---|
| 1 | **Command Center** | `/`, `/dashboard`, `/command-center` | `DashboardController` | `Dashboard.vue` | Komando & Intelijen |
| 2 | **Radar Media Online (29 RSS Sulbar / 33 RSS Nasional)** | `/media-intelligence` | `MediaIntelligenceController` | `MediaIntelligence/Index.vue` | Komando & Intelijen |
| 3 | **Radar Media Sosial** | `/social-intelligence` | `SocialIntelligenceController` | `SocialIntelligence/Index.vue` | Komando & Intelijen |
| 4 | **Laporan Polres & Isu** | `/issues` | `IssueController` | `Issues/Index.vue`, `Issues/Show.vue` | Manajemen Isu & Tugas |
| 5 | **Input Laporan Baru** | `/issues/create` | `IssueController` | `Issues/Create.vue` | Manajemen Isu & Tugas |
| 6 | **Antrean Verifikasi** | `/verification-queue` | `SystemModuleController` | `VerificationQueue/Index.vue` | Manajemen Isu & Tugas |
| 7 | **Disposisi Tugas** | `/tasks` | `TaskController` | `Tasks/Index.vue` | Manajemen Isu & Tugas |
| 8 | **Publikasi Multi-Kanal** | `/publications` | `PublicationController` | `Publications/Index.vue` | Publikasi & Dokumentasi |
| 9 | **Galeri Media Aman** | `/gallery` | `SystemModuleController` | `Gallery/Index.vue` | Publikasi & Dokumentasi |
| 10 | **Laporan & Rekap Anev** | `/reports` | `SystemModuleController` | `Reports/Index.vue` | Publikasi & Dokumentasi |
| 11 | **Personalia & Petugas** | `/personalia` | `SystemModuleController` | `Personalia/Index.vue` | Administrasi & Sistem |
| 12 | **Pengaturan Satwil (Polda/Polres)** | `/settings/polda` | `SystemModuleController` | `Settings/Polda.vue` | Administrasi & Sistem |
| 13 | **Keamanan Siber (pintu masuk Keamanan & Pemeliharaan)** | `/security` | `SystemModuleController` | `Settings/Security.vue` | Administrasi & Sistem |
| 14 | **Hak Akses (RBAC Matrix)** | `/roles` | `SystemModuleController` | `Settings/Roles.vue` | Administrasi & Sistem |
| 15 | **Manajemen Menu Dinamis** | `/settings/menus` | `MenuManagementController` | `Settings/Menus.vue` | Administrasi & Sistem |
| 16 | **Audit Logs (Jejak Digital)** | `/audit-logs` | `SystemModuleController` | `AuditLogs/Index.vue` | Administrasi & Sistem |
| 17 | **Backup & Restore Basis Data** | `/database/backup` | `DatabaseBackupController` | `Settings/Backup.vue` | Administrasi & Sistem |
| 18 | **Pembersihan Berita Multi-Polda** | `/maintenance/news-cleanup` | `NewsCleanupController` | `Settings/NewsCleanup.vue` | Administrasi & Sistem |
| 19 | **Otomasi Sinkron RSS & Kestabilan** | `/maintenance/rss-sync` | `RssSyncController` | `Settings/RssSync.vue` | Administrasi & Sistem |
| 20 | **Mesin Intelijen AI (Gemini & Apify)** | `/settings/ai` | `AiSettingController` | `Settings/Ai.vue` | Administrasi & Sistem |
| 21 | **Profil Saya & Ganti Kata Sandi** | `/profile` | `SystemModuleController` | `Profile/Index.vue` | Administrasi & Sistem |
| — | Portal Login Dinas *(autentikasi, bukan modul menu)* | `/login` | `Auth\AuthController` | `Auth/Login.vue` | Autentikasi |

Modul 13–20 adalah **8 sub-tab Keamanan & Pemeliharaan** (`menu_location = security_tabs`), ditambah Profil di sidebar. Sidebar utama berisi 4 pilar dan 14 item menu (lihat [doc 15](15-manajemen-menu-dinamis-dan-portabilitas-multi-domain.md)).

---

## 2. Navigasi Sidebar Presisi (`EnterpriseLayout.vue`)

Sidebar dirancang dengan prinsip **Command Center Kontras Tinggi**:
- **Lebar Tetap Desktop**: 256px (`w-64`), dapat diminimalkan menjadi mini-sidebar (`w-20`).
- **Responsive Drawer Mobile**: Slide-over interaktif dengan backdrop gelap untuk layar sentuh ponsel (360px+).
- **Indikator Aktif**: Highlight kuning emas presisi (`bg-amber-400 text-slate-950 font-black`) dengan pill penanda aktif di sisi kiri.
- **Badge Counter Dinamis**:
  - `total_issues`: Jumlah isu dipantau.
  - `pending_verify`: Antrean verifikasi belum diputuskan (merah rose berdenyut jika ada isu siaga).
  - `active_tasks`: Surat perintah tugas yang sedang berjalan.
  - `total_publications`: Jumlah konten terpublikasi di 6 kanal media sosial.
- **Footer Semboyan Presisi**:
  - Semboyan resmi: `PREDIKTIF • RESPONSIBILITAS • TRANSPARANSI BERKEADILAN`.
  - Indikator status tenant: `🟢 Multi-Tenant Active v2.0`.

---

## 3. Top Bar Presisi & Dual-Theme Switcher
- **Dual-Theme Switcher**: Tombol instan di top header bar (dan mobile drawer) untuk beralih antara:
  - *Command Center Dark Mode* (`#070d1d`, tactical navy)
  - *Executive Light Mode* (`#ffffff` on `#f1f5f9`, clean white)
- **Tenant Selector (Polda Switcher)**: Mengizinkan pergantian konteks instan dari *Konsolidasi Nasional (Mabes)* ke *Polda Sulawesi Barat* atau Polda lainnya.
- **WITA Realtime Clock**: Mengikuti zona waktu resmi Indonesia Tengah (`Asia/Makassar`).
- **Lonceng Notifikasi**: Memberikan alert cepat terhadap jumlah laporan lapangan yang menunggu verifikasi pimpinan.
- **Profil Pimpinan**: Menampilkan avatar, pangkat, nama, jabatan, serta tautan cepat ke profil dan pusat keamanan.
