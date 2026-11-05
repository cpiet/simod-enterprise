# 🧭 22. Peta Kode & Sumber Kebenaran (Source of Truth)

Dokumen ini adalah **peta tunggal** antara dokumentasi dan kode SIMOD Enterprise. Seluruh angka di sini diverifikasi langsung dari repositori pada **5 November 2026** (baseline `php artisan test`: **151 tests, 905 assertions, PASS**).

> [!IMPORTANT]
> **Aturan emas:** bila dokumen lain bertentangan dengan dokumen ini atau dengan kode, **kode yang benar**. Perbarui dokumennya, bukan sebaliknya. Dokumen berstatus *snapshot historis* (angka test/berita lama) ditandai eksplisit di dokumennya masing-masing.

---

## 1. Sumber Kebenaran per Topik

| Topik | Sumber kebenaran (kode) | Dokumen penjelas |
|:---|:---|:---|
| Resolusi tenant & sesi | `app/Http/Controllers/Controller.php` (`resolveActiveTenant`, `authorizeTenantRecord`), `HandleInertiaRequests.php`, `TenantController.php` | [01](01-arsitektur-dan-multi-tenant.md), [13](13-keamanan-tenant-dan-mesin-intelijen-ai.md) |
| Daftar rute | `routes/web.php`, `routes/api.php`, `routes/console.php` | bagian 4 di bawah |
| Matriks RBAC (modul & peran bawaan) | `app/Models/RolePermission.php` (`defaultModules`, `defaultRoles`) | [12](12-modul-personalia-dan-rbac-roles.md) |
| Menu sidebar & tab keamanan | `app/Models/MenuItem.php` (`seedDefaults`) + tabel `menu_items` | [15](15-manajemen-menu-dinamis-dan-portabilitas-multi-domain.md) |
| Skema basis data | `database/migrations/*` | bagian 6 di bawah |
| Data awal (tenant, feed, akun) | `database/seeders/*` | [02](02-perintah-artisan-dan-cli.md), [PANDUAN_SETUP](PANDUAN_SETUP_KOMPUTER_RUMAH.md) |
| Perintah CLI & jadwal | `app/Console/Commands/*`, `routes/console.php` | [02](02-perintah-artisan-dan-cli.md) |
| Aturan wajib AI agent | `CLAUDE.md`, `AGENTS.md`, `.github/copilot-instructions.md` | [21](21-standarisasi-multi-agent-dan-sinkronisasi-lintas-model.md) |

---

## 2. Stack & Versi (dari `composer.json` / `package.json`)

| Lapisan | Teknologi |
|:---|:---|
| Backend | PHP `^8.2` (Laragon: `php-8.2.28`), Laravel `^12.0`, `inertiajs/inertia-laravel ^3.5` |
| Frontend | Vue `^3.5` (`<script setup>`), `@inertiajs/vue3 ^3.9`, Vite `^6`, Tailwind CSS `^4` (`@tailwindcss/vite`), `lucide-vue-next`, `marked` |
| Basis data | MySQL/MariaDB (`simod_enterprise`) untuk operasional; **SQLite in-memory** khusus test (`phpunit.xml`, dilarang diubah) |
| Sesi / cache / antrean | Driver `database` (`SESSION_DRIVER`, `CACHE_STORE`, `QUEUE_CONNECTION`) |
| Worker eksternal | `scraper/media_crawler.py` (Python) → `POST /api/v1/ingest/*` |

> **Catatan Windows:** pada Git Bash `php` mungkin tidak ada di `PATH`. Gunakan PHP Laragon (`D:\laragon\bin\php\php-8.2.28-nts-Win32-vs16-x64\php.exe`) atau terminal Laragon. Untuk npm di PowerShell gunakan `npm.cmd`.

---

## 3. Model Tenant & Sesi (Perilaku Nyata)

**Kunci sesi:** `active_polda_code` (mis. `SULBAR`, `ALL`) dan `active_polda_id`.

| Kondisi pengguna | Perilaku `resolveActiveTenant()` / `HandleInertiaRequests::share()` |
|:---|:---|
| Personel kewilayahan (`polda_id` terisi & bukan `kadiv_humas`/`super_admin_mabes`/`superadmin`) | **Terkunci** ke Polda miliknya. Parameter apa pun diabaikan. |
| Mabes / Super Admin | Membaca `active_polda_code` dari sesi (default `ALL`). Parameter `?polda=` **hanya dihormati bila `APP_ENV` = `local` atau `testing`** (kompatibilitas test/CLI); di produksi diabaikan. **Tidak boleh dipakai di tautan UI.** |
| Tamu (belum login) | Tidak dapat mengakses rute aplikasi (redirect ke `/login`). Props Inertia untuk tamu kosong: tanpa data tenant, navigasi, metrik, atau identitas pimpinan. |
| Kode `ALL` atau `DIVHUMAS` | Alias nasional → tenant `DIVHUMAS` (Mabes), kode sesi dinormalkan menjadi `ALL`. |

**Pergantian tenant yang sah:** `POST /tenant/switch` dengan payload **`{ "code": "SULBAR" }`** (bukan `polda_id`). Personel kewilayahan yang meminta kode selain miliknya ditolak (403 JSON / flash error).

**Simulasi peran:** `?role=` / `active_simulated_role` hanya dihormati untuk pengguna yang **sedang login dan Super Admin/Kadivhumas**; untuk pengguna lain simulasi dihapus dari sesi. Efeknya hanya pada menu & `auth.permissions` di frontend, bukan hak akses backend.

**Zona waktu tampilan:** mengikuti `poldas.timezone` tenant aktif (default `Asia/Makassar`; label `WIB` saat `ALL`).

---

## 4. Peta Rute (117 rute aplikasi: 113 web + 4 API)

> Seluruh rute web (kecuali `/login` dan `/logout`) berada di grup `Route::middleware('auth')`. Setiap rute modul juga memakai `->middleware('module:kunci')` (lihat bagian 7). `POST /login` dibatasi `throttle:10,1`.

### 4.1. Web (`routes/web.php`)

| Grup | Rute utama | Controller |
|:---|:---|:---|
| Auth | `GET/POST /login` (`guest`), `POST /logout`, `GET /logout` | `Auth\AuthController` |
| Tenant | `POST /tenant/switch` | `TenantController` |
| Command Center | `/`, `/dashboard`, `/command-center` | `DashboardController` |
| Radar Media Online | `/media-intelligence`, `/monitoring-media-online`; bookmark, analisis Gemini, chat SADEWA, pengaturan Gemini; CRUD & analisis **topik atensi** (`/media-intelligence/topics/*`), `adopt-suggestion`, `dismiss-suggestion` | `MediaIntelligenceController` |
| Radar Media Sosial | `/social-intelligence`; `sync`, `sync-trends`, `track-keyword`, `analyze-ai`, `reports/{id}`, `cases/{id}/close\|refresh`, `settings`, `test-connection`, bookmark | `SocialIntelligenceController` |
| Isu | `/issues`, `/issues/create`, `/issues/{id}`, `/issues-export-csv`; aksi `clarify`, `verify`, `return`, `resolve`, `close`, `task`, `release` | `IssueController` |
| Siaran pers | `POST /press-releases` | `PressReleaseController` |
| Publikasi | `/publications` (GET, POST) | `PublicationController` |
| Tugas | `/tasks` (GET, POST), `POST /tasks/{id}/update` | `TaskController` |
| Modul sistem | `/verification-queue`, `/gallery`, `/reports` (+`/reports/export/csv`), `/personalia` (CRUD + `toggle-status`, `reset-password`), `/audit-logs`, `/profile`, `/settings/polda` (+`update`, `create`), `/settings/polres/create`, `/security`, `/roles` (+`reset`) | `SystemModuleController` |
| Backup | `/database/backup` (+`create`, `download`, `restore`, `upload-restore`, `delete`, `settings`) | `DatabaseBackupController` |
| Pembersihan berita | `/maintenance/news-cleanup` (GET, POST) | `NewsCleanupController` |
| Sinkron RSS | `/maintenance/rss-sync` (+`trigger`, `feeds`, `feeds/{id}/toggle`, `feeds/test`, DELETE `feeds/{id}`) | `RssSyncController` |
| Mesin AI | `/settings/ai` (+`test`, `reset-prompt`, `apify`, `test-apify`) | `AiSettingController` |
| Menu dinamis | `/settings/menus` (+`toggle`, `move`, `reset`, PUT/DELETE `{id}`) | `MenuManagementController` |

Rute tambahan setelah perapihan alur: `POST /issues/{id}/submit` (draf → verifikasi), `POST /publications/{id}/approve|reject`, `POST /profile/password`, serta grup `/sadewa/*` (meta, conversations, chat) untuk SADEWA AI ([doc 26](26-asisten-sadewa-multi-sumber-dan-riwayat-percakapan.md)); `POST /media-intelligence/chat` dihapus. Halaman `/settings/mail` dan `/maintenance` **sudah dihapus** ([doc 24](24-penyederhanaan-isu-tugas-publikasi-dan-administrasi.md)).

### 4.2. API (`routes/api.php`, prefiks `/api`)

| Endpoint | Fungsi |
|:---|:---|
| `GET /api/v1/health` | Status layanan |
| `GET /api/v1/poldas` *(token)* | Daftar Polda aktif beserta Polres |
| `POST /api/v1/ingest/article` *(token)* | Ingest satu artikel (duplikat berdasarkan `url` → `status: updated`; `polda_code` tak dikenal → fallback Polda pertama) |
| `POST /api/v1/ingest/bulk` *(token)* | Ingest banyak artikel |

Endpoint bertanda *(token)* memakai middleware `ingest.token`: header `Authorization: Bearer <INGEST_API_TOKEN>` atau `X-Ingest-Token`; token server kosong → 503, token salah → 401. `GET /api/v1/health` dan `GET /up` publik.

---

## 5. Komponen Backend

### 5.1. Services (`app/Services`)

| Service | Peran |
|:---|:---|
| `RssFeedService` | Parser RSS/Atom paralel (`curl_multi`) + cache 5 menit |
| `RssSyncService` | Orkestrasi sinkron terjadwal, mutex, fair quota, auto-cap per portal |
| `GoogleNewsCrawlerService` | Penarikan Google News on-demand untuk topik atensi |
| `SocialSyndicationService` | Ingesti medsos Tier 1 (Google News `site:`) → Tier 2 (RSS publik) → Tier 3 (fallback sintetis) |
| `ApifySyncService` | Scraper Apify (token pool + failover), `crawlKeyword`, `syncPolda` |
| `GeminiService` | Analisis AI (media & medsos, SADEWA), pool kunci Gemini + sanitizer |
| `DatabaseBackupService` | Snapshot SQL terkompresi, hash SHA-256, pruning |
| `NewsCleanupService` | Pembersihan berita multi-Polda (kebal bookmark) |

### 5.2. Perintah Artisan & Jadwal

| Perintah | Keterangan |
|:---|:---|
| `rss:sync {--polda=} {--force} {--scheduled}` | Sinkron RSS aman (mutex). `--polda=ALL` = Divhumas |
| `media:prune {--days=} {--polda=}` | Pangkas berita lama non-bookmark (default `MEDIA_RETENTION_DAYS=90`) |
| `db:backup {--type=scheduled} {--force} {--retention=}` | Snapshot basis data |
| `simod:scrape-rss {--polda=}` | Perintah lama: tarik RSS seluruh portal Satwil |
| `simod:sync-feeds {--polda=SULBAR} {--force}` | Perintah lama: sinkron feed terdaftar |

**Scheduler (`routes/console.php`):** `rss:sync --scheduled` tiap menit (`withoutOverlapping(10)`); `db:backup --type=scheduled` tiap 10 menit (`withoutOverlapping(20)`); `media:prune` harian 02:00 (hanya bila `MEDIA_RETENTION_DAYS > 0`).

### 5.3. Middleware

`HandleInertiaRequests` (props bersama: tenant, navigasi, permission, flash, metrik sidebar; kosong untuk tamu), `PreventBackHistory` (header anti-cache pada grup `web`), `EnsureModulePermission` (alias `module`, penegakan RBAC per rute), `EnsureIngestToken` (alias `ingest.token`, API ingest), `trustProxies(at: '*')`. Pengecualian CSRF: `social-intelligence/test-connection`, `media-intelligence/gemini-test`, `settings/ai/test`.

---

## 6. Basis Data (38 tabel = 31 domain + 7 infrastruktur Laravel)

| Kelompok | Tabel |
|:---|:---|
| Wilayah & pengguna | `poldas`, `polres`, `users` |
| Media online | `media_feeds`, `media_articles`, `media_topics`, `media_article_topic`, `media_intelligence_reports`, `early_warnings`, `rss_sync_settings`, `rss_sync_logs` |
| Media sosial | `social_posts`, `social_settings`, `social_investigation_cases`, `social_intelligence_reports`, `monitored_instagram_accounts` |
| Isu, rilis & tugas | `issues`, `issue_status_history`, `press_releases`, `tasks`, `task_updates`, `publications`, `media_files` |
| Keamanan, AI & sistem | `audit_logs`, `security_settings`, `role_permissions`, `menu_items`, `ai_settings`, `sadewa_conversations`, `backup_settings`, `backup_records` |
| Infrastruktur Laravel | `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `sessions`, `password_reset_tokens` |

---

## 7. RBAC: 21 Kunci Modul & 6 Peran Bawaan

**20 kunci modul** (`RolePermission::defaultModules()`), per pilar:

| Pilar | Kunci |
|:---|:---|
| 1. Komando & Intelijen (6) | `dashboard_view`, `media_intelligence_view`, `media_intelligence_ai`, `media_intelligence_manage`, `social_intelligence_view`, `social_intelligence_sync` |
| 2. Manajemen Isu & Tugas (5) | `issues_view`, `issues_create`, `issues_verify`, `tasks_disposition`, `tasks_execute` |
| 3. Publikasi & Dokumentasi (3) | `publications_manage`, `gallery_access`, `reports_export` |
| 4. Administrasi & Sistem (6) | `personalia_manage`, `polda_settings`, `security_center`, `database_backup`, `maintenance_ops`, `roles_matrix_manage` |

**6 peran bawaan** (`defaultRoles()`; dapat diubah per Polda di `/roles`, disimpan di `role_permissions`):

| `role_key` | Nama | Modul aktif |
|:---|:---|:---:|
| `kadiv_humas` | Kadivhumas Polri | 21 / 20 |
| `super_admin_mabes` | Super Administrator Mabes | 21 / 20 |
| `polda_leader` | Pimpinan Polda (Kabid Humas) | 16 / 20 |
| `verifikator` | Verifikator (Kasubbid Penmas) | 12 / 20 |
| `analis` | Analis Intelijen Media & Siber | 8 / 20 |
| `operator` | Operator Satwil (Polres / Sie Humas) | 7 / 20 |

`User::isSuperAdmin()` bernilai benar untuk peran `super_admin_mabes`, `kadiv_humas`, `superadmin` dan peran ini selalu lolos `module:*`.

**Penegakan server-side:** middleware `module:kunci` (pisahkan beberapa kunci dengan `|`, salah satu cukup) memeriksa `RolePermission::isGranted()`; kustomisasi per Polda di `/roles` berlaku seketika. Pelanggaran → HTTP 403 (JSON untuk AJAX). Pemetaan rute→kunci: `/` → `dashboard_view`; `/media-intelligence*` → `media_intelligence_view` (aksi AI/crawl → `media_intelligence_ai`); `/social-intelligence*` → `social_intelligence_view` (sync/track/settings/refresh → `social_intelligence_sync`); `/issues*` → `issues_view` (`create`/store → `issues_create`; clarify/verify/return/resolve/close/release & `/press-releases` → `issues_verify`; `/issues/{id}/task` → `tasks_disposition`); `/tasks*` → `tasks_execute\|tasks_disposition` (POST `/tasks` → `tasks_disposition`); `/publications` → `publications_manage`; `/gallery` → `gallery_access`; `/reports*` → `reports_export`; `/verification-queue` → `issues_view`; `/personalia*` → `personalia_manage`; `/settings/polda|polres` → `polda_settings`; `/security`, `/audit-logs`, `/settings/ai` → `security_center`; `/roles`, `/settings/menus` → `roles_matrix_manage`; `/database/backup*` → `database_backup`; `/maintenance*` → `maintenance_ops`. `/profile` dan `/tenant/switch` cukup login.

---

## 8. Data Awal (Seeder) & Cakupan Tenant

`DatabaseSeeder` memanggil berurutan: `EnterpriseDatabaseSeeder` (Polda/Polres/akun dasar, memanggil `SulbarRssFeedsSeeder`) → `MultiTenantPoldasSeeder` → `DivhumasNationalRssFeedsSeeder` → `MultiPoldaArticlesHistoricalSeeder` → `CommandCenterDynamicSeeder` → `SocialIntelligenceSeeder`, lalu `MenuItem::ensureDefaultMenusSeeded()`.

| Item | Jumlah nyata |
|:---|:---|
| Tenant yang di-seed | **6**: Divhumas Polri (Mabes) + Polda Sulbar, Gorontalo, Sulsel, Sultra, Kaltim |
| Feed RSS Sulbar (`SulbarRssFeedsSeeder`) | **29** |
| Feed RSS nasional Divhumas (`DivhumasNationalRssFeedsSeeder`) | **33** |

> **"34 Polda"** pada dokumen lain adalah **target cakupan arsitektur** (`php artisan rss:sync` tanpa opsi memproses semua Polda aktif di basis data), bukan jumlah Polda yang di-seed saat ini.

Kredensial akun awal hanya didokumentasikan di [PANDUAN_SETUP_KOMPUTER_RUMAH.md](PANDUAN_SETUP_KOMPUTER_RUMAH.md); jangan diduplikasi ke dokumen lain.

---

## 9. Temuan Audit & Status Perbaikan

Temuan saat menyelaraskan dokumen dengan kode (5 Nov 2026), beserta status perbaikannya.

| # | Temuan | Status |
|:--|:---|:---|
| 1 | Rute aplikasi tidak dibungkus middleware `auth`; tamu mendapat pengguna fallback "pimpinan". | ✅ **Diperbaiki** — grup `auth` pada seluruh rute; props tamu dikosongkan. Diuji di `AuthHardeningTest`. |
| 2 | Login tanpa kata sandi / username kosong → `kadivhumas` / sandi demo hardcoded / pengguna tak dikenal tetap masuk; mode PIN palsu; kredensial demo tertanam di `Login.vue`. | ✅ **Diperbaiki** — username + sandi wajib valid, galat seragam, `throttle:10,1`, audit `auth.login_failed`, `remember` default nonaktif; mode PIN dan kredensial demo dihapus dari UI. |
| 3 | RBAC hanya memengaruhi menu; `hasPermission` tak dipanggil. | ✅ **Diperbaiki** — middleware `module:*` di seluruh rute modul (bagian 7). Diuji di `ModulePermissionEnforcementTest`. |
| 4 | `?role=` dan `?polda=` dibaca server di semua lingkungan. | ✅ **Diperbaiki** — `?polda=` hanya di `local`/`testing`; `?role=` hanya untuk Super Admin yang login. |
| 5 | Kunci lama `news_cleanup`, `rss_sync_manage` pada `polda_leader`. | ✅ **Dihapus**; test memastikan semua kunci peran bawaan valid. |
| 6 | API ingest tanpa token. | ✅ **Diperbaiki** — `INGEST_API_TOKEN`, fail-closed (503 bila kosong). Worker Python mengirim Bearer token. |
| 7 | Kata sandi akun dinas dalam teks biasa di `PANDUAN_SETUP_KOMPUTER_RUMAH.md`. | ✅ **Dihapus dari dokumen**. ⚠️ Kata sandi seed masih ada di `EnterpriseDatabaseSeeder`/`PersonaliaSeeder` dan riwayat git — anggap bocor untuk lingkungan non-lokal dan **ganti** setelah seeding. |
| 8 | Berkas RSS duplikat di root. | ✅ **Dihapus** (identik dengan doc 14). |
| 10 | Endpoint mengambil tenant dari input klien (`?polda=` default SULBAR pada analisis Gemini; `polda_id`/`polda_code` pada topik, medsos, matriks RBAC, sinkron RSS). | ✅ **Diperbaiki** — tenant dari sesi; override hanya Super Admin (`requestedPoldaOverride`, `scopedPoldaId`). Diuji di `TenantOverrideProtectionTest`. Lihat [doc 23](23-optimalisasi-performa-dan-ux-radar-media-online.md) §2d. |
| 11 | Halaman Mail & Pemeliharaan hanya mock klien; 8 parameter Pusat Keamanan tersimpan tetapi tidak diterapkan; "Ganti Kata Sandi"/PIN di Profil hanya notifikasi. | ✅ **Diperbaiki** — halaman dihapus; Keamanan memakai 3 parameter yang ditegakkan (lockout login, batas sesi); ganti kata sandi nyata. Lihat [doc 24](24-penyederhanaan-isu-tugas-publikasi-dan-administrasi.md). |
| 9 | Syarat keamanan worker doc 14 (SSRF, XXE, dedup berlapis) belum tentu terimplementasi. | ⏳ **Terbuka** — audit `RssFeedService` sebelum mengaktifkan feed baru. |

**Risiko tersisa yang perlu keputusan:** (a) pengecualian CSRF pada 3 endpoint `test-connection` (`social-intelligence/test-connection`, `media-intelligence/gemini-test`, `settings/ai/test`); (b) kata sandi seed pada seeder/riwayat git; (c) `GET /logout` masih tersedia (logout lewat GET rawan CSRF-logout ringan); (d) `trustProxies(at: '*')`.

---

## 10. Pengujian

`tests/Feature` memuat 22 berkas (plus `tests/Unit/ExampleTest.php`): `CommandCenterNationalMapAndKpiTest` (5 test), `DatabaseBackupTest`, `DivhumasNationalFeedsTest`, `DynamicMenuAndSecurityAuditTest`, `EndToEndAuditStressTest`, `EnterpriseFullSuiteTest`, `ExampleTest`, `GeminiMediaIntelligenceTest`, `InstagramAccountMonitoringTest` (2 test), `MediaTopicDossierTest`, `NewsCleanupTest`, `PersonaliaAuthorityTest` (6 test), `RolePermissionMatrixTest`, `RssSyncTest`, `SadewaChatTest` (7 test), `SecurityCenterTest`, `SocialIntelligenceTest` (12 test), `TenantSecurityTest` (3 test), `AdminSystemEnforcementTest` (4 test: lockout login, batas sesi, ganti kata sandi, halaman mock terhapus), `TenantOverrideProtectionTest` (4 test: tenant tidak dapat dialihkan klien), `AuthHardeningTest` (9 test: gerbang login, kredensial, simulasi peran, token ingest), `ModulePermissionEnforcementTest` (6 test: penegakan RBAC server-side).

Baseline wajib sebelum dan sesudah perubahan: `php artisan test` → **151 passed (905 assertions)** dan `npm.cmd run build` tanpa galat. Helper test: `$this->actingAsSuperAdmin()` (`tests/TestCase.php`).

---

## 11. Checklist Menjaga Dokumen Tetap Konsisten

1. Ubah kode → perbarui bagian terkait di dokumen ini **dan** dokumen modulnya dalam commit yang sama.
2. Setiap angka (test, rute, tabel, modul RBAC, feed) harus dapat diverifikasi ulang dengan perintah berikut:
   ```powershell
   php artisan test                      # baseline test
   php artisan route:list --json         # jumlah rute
   Select-String -Path app\Models\RolePermission.php -Pattern "'key'"   # kunci modul
   ```
3. Jangan menulis `?polda=` pada contoh tautan UI; gunakan `POST /tenant/switch` dengan payload `code`.
4. Path proyek yang berlaku: `d:\laragon\www\simod-enterprise` (bukan `d:\Foto\laragon\...`).
5. Perintah npm di Windows: `npm.cmd`.
