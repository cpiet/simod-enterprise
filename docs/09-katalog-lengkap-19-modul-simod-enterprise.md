# 09 — Katalog Lengkap 19 Modul SIMOD Enterprise & Paritas Fitur

Dokumen ini mencatat pemetaan lengkap seluruh 19 modul pada **SIMOD Enterprise Multi-Tenant** yang mengadopsi standar kepangkatan, hierarki satwil, dan tata kelola informasi publik Polri (paritas penuh dengan `simod-humas.test`).

---

## 1. Matriks 19 Modul Terintegrasi

| No | Modul | Rute URL | Pengontrol (Controller) | Tampilan (Inertia Vue) | Pilar Hierarki |
|:---|:---|:---|:---|:---|:---|
| 1 | **Command Center** | `/` & `/dashboard` | `DashboardController` | `Dashboard.vue` | Komando & Intelijen |
| 2 | **Media Online (Radar 29 RSS)** | `/media-intelligence` | `MediaIntelligenceController` | `MediaIntelligence/Index.vue` | Komando & Intelijen |
| 3 | **Radar Media Sosial** | `/social-intelligence` | `SocialIntelligenceController` | `SocialIntelligence/Index.vue` | Komando & Intelijen |
| 3 | **Laporan Polres & Isu** | `/issues` | `IssueController` | `Issues/Index.vue` | Manajemen Isu & Tugas |
| 4 | **Input Laporan Baru** | `/issues/create` | `IssueController` | `Issues/Create.vue` | Manajemen Isu & Tugas |
| 5 | **Antrean Verifikasi** | `/verification-queue` | `SystemModuleController` | `VerificationQueue/Index.vue` | Manajemen Isu & Tugas |
| 6 | **Disposisi Tugas** | `/tasks` | `TaskController` | `Tasks/Index.vue` | Manajemen Isu & Tugas |
| 7 | **Publikasi Multi-Kanal** | `/publications` | `PublicationController` | `Publications/Index.vue` | Publikasi & Dokumentasi |
| 8 | **Galeri Media Aman** | `/gallery` | `SystemModuleController` | `Gallery/Index.vue` | Publikasi & Dokumentasi |
| 9 | **Laporan & Rekap Anev** | `/reports` | `SystemModuleController` | `Reports/Index.vue` | Publikasi & Dokumentasi |
| 10 | **Personalia & Petugas** | `/personalia` | `SystemModuleController` | `Personalia/Index.vue` | Administrasi & Sistem |
| 11 | **Pengaturan Satwil (Polda/Polres)** | `/settings/polda` | `SystemModuleController` | `Settings/Polda.vue` | Administrasi & Sistem |
| 12 | **Keamanan & Pemeliharaan** | `/security` | `SystemModuleController` | `Settings/Security.vue` | Administrasi & Sistem |
| 13 | **Hak Akses (RBAC Matrix)** | `/roles` | `SystemModuleController` | `Settings/Roles.vue` | Administrasi & Sistem |
| 14 | **Audit Logs (Jejak Digital)** | `/audit-logs` | `SystemModuleController` | `AuditLogs/Index.vue` | Administrasi & Sistem |
| 15 | **Backup & Restore Basis Data** | `/database/backup` | `DatabaseBackupController` | `Settings/Backup.vue` | Administrasi & Sistem |
| 16 | **Pembersihan Berita Multi-Polda** | `/maintenance/news-cleanup` | `NewsCleanupController` | `Settings/NewsCleanup.vue` | Administrasi & Sistem |
| 17 | **Otomasi Sinkron RSS & Kestabilan** | `/maintenance/rss-sync` | `RssSyncController` | `Settings/RssSync.vue` | Administrasi & Sistem |
| 18 | **Mode Pemeliharaan (Maintenance)** | `/maintenance` | `SystemModuleController` | `Settings/Maintenance.vue` | Administrasi & Sistem |
| 19 | **Konfigurasi Mail (SMTP)** | `/settings/mail` | `SystemModuleController` | `Settings/Mail.vue` | Administrasi & Sistem |
| 20 | **Profil Saya & PIN Cepat** | `/profile` | `SystemModuleController` | `Profile/Index.vue` | Administrasi & Sistem |
| 21 | **Portal Login Dinas** | `/login` | `AuthController` | `Auth/Login.vue` | Autentikasi |

---

## 2. Navigasi Sidebar Presisi (`EnterpriseLayout.vue`)

Sidebar dirancang dengan prinsip **Command Center Kontras Tinggi**:
- **Lebar Tetap Desktop**: 260px (`w-64`), dapat diminimalkan menjadi mini-sidebar (`w-20`).
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
