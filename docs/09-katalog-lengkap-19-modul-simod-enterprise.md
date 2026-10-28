# 09 — Katalog Lengkap 19 Modul SIMOD Enterprise & Paritas Fitur

Dokumen ini mencatat pemetaan lengkap seluruh 19 modul pada **SIMOD Enterprise Multi-Tenant** yang mengadopsi standar kepangkatan, hierarki satwil, dan tata kelola informasi publik Polri (paritas penuh dengan `simod-humas.test`).

---

## 1. Matriks 19 Modul Terintegrasi

| No | Modul | Rute URL | Pengontrol (Controller) | Tampilan (Inertia Vue) | Kategori |
|---|---|---|---|---|---|
| 1 | **Command Center** | `/` & `/dashboard` | `DashboardController` | `Dashboard.vue` | Komando & Intelijen |
| 2 | **Media Intelligence & EWS** | `/media-intelligence` | `MediaIntelligenceController` | `MediaIntelligence/Index.vue` | Komando & Intelijen |
| 3 | **Manajemen Isu Lapangan** | `/issues` | `IssueController` | `Issues/Index.vue` | Operasional Humas |
| 4 | **Antrean Verifikasi** | `/verification-queue` | `SystemModuleController` | `VerificationQueue/Index.vue` | Operasional Humas |
| 5 | **Disposisi & Surat Tugas** | `/tasks` | `TaskController` | `Tasks/Index.vue` | Operasional Humas |
| 6 | **Publikasi Multi-Kanal** | `/publications` | `PublicationController` | `Publications/Index.vue` | Operasional Humas |
| 7 | **Galeri Media Aman** | `/gallery` | `SystemModuleController` | `Gallery/Index.vue` | Operasional Humas |
| 8 | **Laporan & Rekap Anev** | `/reports` | `SystemModuleController` | `Reports/Index.vue` | Operasional Humas |
| 9 | **Siaran Pers Resmi** | `/press-releases` | `PressReleaseController` | (Modal di Issues/Dashboard) | Operasional Humas |
| 10 | **Personalia & Pengguna** | `/personalia` | `SystemModuleController` | `Personalia/Index.vue` | Administrasi & Sistem |
| 11 | **Pengaturan Satwil (Polda/Polres)** | `/settings/polda` | `SystemModuleController` | `Settings/Polda.vue` | Administrasi & Sistem |
| 12 | **Pusat Keamanan Siber** | `/security` | `SystemModuleController` | `Settings/Security.vue` | Administrasi & Sistem |
| 13 | **Matriks Hak Akses (RBAC)** | `/roles` | `SystemModuleController` | `Settings/Roles.vue` | Administrasi & Sistem |
| 14 | **Konfigurasi Mail Server (SMTP)** | `/settings/mail` | `SystemModuleController` | `Settings/Mail.vue` | Administrasi & Sistem |
| 15 | **Backup Basis Data** | `/database/backup` | `SystemModuleController` | `Settings/Backup.vue` | Administrasi & Sistem |
| 16 | **Audit Logs (Jejak Digital)** | `/audit-logs` | `SystemModuleController` | `AuditLogs/Index.vue` | Administrasi & Sistem |
| 17 | **Mode Pemeliharaan (Maintenance)** | `/maintenance` | `SystemModuleController` | `Settings/Maintenance.vue` | Administrasi & Sistem |
| 18 | **Profil Saya & PIN Cepat** | `/profile` | `SystemModuleController` | `Profile/Index.vue` | Administrasi & Sistem |
| 19 | **Portal Login Dinas** | `/login` | `AuthController` | `Auth/Login.vue` | Autentikasi |

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
- **Footer Motto Presisi**:
  - Semboyan resmi: `PREDIKTIF • RESPONSIBILITAS • TRANSPARANSI BERKEADILAN`.
  - Indikator status tenant: `🟢 Multi-Tenant Active v2.0`.

---

## 3. Top Bar Presisi
- **Tenant Selector (Polda Switcher)**: Mengizinkan pergantian konteks instan dari *Konsolidasi Nasional (Mabes)* ke *Polda Sulawesi Barat* atau Polda lainnya.
- **WITA Realtime Clock**: Mengikuti zona waktu resmi Indonesia Tengah (`Asia/Makassar`).
- **Lonceng Notifikasi**: Memberikan alert cepat terhadap jumlah laporan lapangan yang menunggu verifikasi pimpinan.
- **Profil Pimpinan**: Menampilkan avatar, pangkat, nama, jabatan, serta tautan cepat ke profil dan pusat keamanan.

