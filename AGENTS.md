# AGENTS.md — Panduan Induk untuk Semua AI Coding Agent

Berkas ini adalah **satu-satunya sumber aturan** bagi AI coding agent (Claude Code, OpenAI Codex, Gemini CLI / Antigravity, GitHub Copilot, Cursor, DeepSeek, dan lainnya) yang bekerja di repositori **SIMOD Enterprise**.

- `CLAUDE.md` dan `GEMINI.md` hanya mengimpor berkas ini (`@AGENTS.md`), jadi isinya tidak perlu disalin.
- `.github/copilot-instructions.md` berisi ringkasan aturan wajib dan merujuk ke sini.
- Angka dan peta kode yang lengkap ada di [`docs/22-peta-kode-dan-sumber-kebenaran.md`](docs/22-peta-kode-dan-sumber-kebenaran.md).
- **Bila dokumen bertentangan dengan kode, kode yang benar.** Perbaiki dokumennya dalam commit yang sama.

Bahasa kerja proyek: **Bahasa Indonesia** (UI, pesan galat, komentar, dokumentasi, pesan commit).

---

## 1. Ringkasan Sistem

**SIMOD Enterprise** (Sistem Monitoring dan Opini Publik Digital Polri) adalah platform pemantauan media dan opini publik untuk Divisi Humas Polri dan Polda jajaran. Fungsinya: agregasi berita RSS, radar media sosial, analisis AI (Gemini), manajemen isu dan peringatan dini (EWS), disposisi tugas, publikasi, serta administrasi.

| Lapisan | Teknologi |
|:---|:---|
| Backend | PHP 8.2, Laravel 12, `inertiajs/inertia-laravel` 3.x; pola Controller + Service (`app/Services`) |
| Frontend | Vue 3.5 (`<script setup>`, Composition API), `@inertiajs/vue3` 3.x, Vite 6 |
| Styling | Tailwind CSS v4 lewat `@tailwindcss/vite` (**tanpa** `tailwind.config.js`); token tema di `resources/css/app.css` |
| Ikon / Markdown | `lucide-vue-next`, `marked` |
| Basis data | MySQL/MariaDB `simod_enterprise` (Laragon); **SQLite in-memory** khusus test |
| Sesi / cache / antrean | Driver `database` |
| AI | Google Gemini (`app/Services/GeminiService.php`), dibatasi `app/Services/AiQuota.php` |
| Scraper eksternal | Apify (`ApifyClient` = failover token + rem harian `SIMOD_APIFY_DAILY_RUNS`; `ApifySyncService`, `SocialAccountMonitoringService`, `CommentDigestService`), worker Python `scraper/` → `POST /api/v1/ingest/*` |

Lingkungan lokal: Windows 11 + Laragon, vhost `http://simod-enterprise.test`. Folder proyek bisa berbeda antar komputer (mis. `D:\Foto\laragon\www\simod-enterprise` atau `D:\laragon\www\simod-enterprise`); jangan menulis path absolut di kode maupun di tautan dokumen. Gunakan path relatif.

---

## 2. Perintah Penting

```bash
php artisan test                         # seluruh test (SQLite in-memory); wajib hijau
php artisan test tests/Feature/NamaTest.php
npm.cmd run build                        # build Vite (di PowerShell pakai npm.cmd, bukan npm)
npm.cmd run dev                          # dev server dengan HMR

php artisan migrate --seed               # skema + data awal (DatabaseSeeder)
php artisan rss:sync --polda=SULBAR      # sinkron RSS satu Polda (mutex lock)
php artisan rss:sync --polda=ALL         # sinkron feed nasional Divhumas
php artisan rss:sync                     # semua Polda aktif
php artisan media:prune --days=90        # pangkas berita lama (bookmark aman)
php artisan db:backup --force            # snapshot basis data
php artisan route:list                   # daftar rute
```

Catatan Windows:
- PowerShell dapat memblokir `npm` (execution policy). Selalu pakai `npm.cmd`.
- `php` Laragon ada di `<laragon>\bin\php\php-8.2.x\php.exe`. Bila `php` tidak dikenal di terminal, pakai terminal Laragon.

Scheduler (sumber tunggal `app/Support/ScheduledTasks.php`, didaftarkan `routes/console.php`): `rss:sync --scheduled` tiap menit, `db:backup --type=scheduled` tiap 10 menit, `social:sync-free` tiap 30 menit (sindikasi gratis, `SIMOD_SOCIAL_AUTO_SYNC`), `social:trends-refresh` tiap jam (Google Trends), `audit:prune` 02:30, `media:prune` harian 02:00 (bila `MEDIA_RETENTION_DAYS > 0`). **Tidak ada** penjadwalan otomatis untuk analisis AI maupun sinkron Apify berbayar.

---

## 3. Aturan Wajib (Non-Negotiable)

### 3.1. Testing
1. **Jangan mengubah `phpunit.xml`.** Test memakai `DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`. Bila diarahkan ke MySQL, basis data lokal akan terhapus.
2. Sebelum menyatakan tugas selesai, jalankan `php artisan test` **dan** `npm.cmd run build`. Semua test harus lulus. Angka baseline terakhir tercatat di doc 22 §10.
3. Fitur atau perbaikan baru disertai test di `tests/Feature`. Gunakan helper `$this->actingAsSuperAdmin()` (`tests/TestCase.php`) atau buat user dengan peran yang sesuai.
4. Test tidak boleh memanggil API sungguhan (Gemini, Apify, Google News). Gunakan `Http::fake()` dan kosongkan kunci API di config/env bila perlu.

### 3.2. Multi-tenant (Mabes → Polda → Polres)
1. Setiap query data operasional (`MediaArticle`, `MediaTopic`, `Issue`, `Task`, `EarlyWarning`, `Publication`, `SocialPost`, dsb.) **wajib** difilter `polda_id`.
2. Tenant aktif **selalu** diambil dari sesi:
   ```php
   [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
   ```
   Jangan membaca `polda`, `polda_id`, atau `polda_code` dari input klien. Override tenant hanya lewat `requestedPoldaOverride()` / `scopedPoldaId()`, dan keduanya hanya berlaku untuk Super Admin (`app/Http/Controllers/Controller.php`).
3. Personel daerah (`polda_id` terisi dan bukan `kadiv_humas`/`super_admin_mabes`/`superadmin`) **terkunci** ke Polda-nya.
4. Pergantian tenant hanya lewat `POST /tenant/switch` dengan payload `{ "code": "SULBAR" }`. Kode `ALL`/`DIVHUMAS` berarti lingkup nasional (tenant Divhumas Polri). **Jangan pernah** membuat tautan atau tombol dengan `?polda=...`. Server hanya menghormatinya di `APP_ENV=local|testing` untuk kompatibilitas test.
5. Pada aksi mutasi (store/update/destroy), verifikasi kepemilikan dengan `$this->authorizeTenantRecord($request, $record)` dan paksa tenant milik pengguna daerah:
   ```php
   if (!$request->user()->isSuperAdmin() && $request->user()->polda_id) {
       $validated['polda_id'] = $request->user()->polda_id;
   }
   ```
6. Pada lingkup nasional, laporan eksekutif (`latestMediaReport`, `latestSocialReport`) harus menargetkan `polda_id` milik Polda berkode `DIVHUMAS` secara eksplisit, jangan query tanpa filter.

### 3.3. Autentikasi & otorisasi
1. Semua rute web berada di grup `Route::middleware(['auth', 'idle'])`, kecuali `/login` dan `/logout`. `idle` adalah batas sesi tidak aktif.
2. Setiap rute modul **wajib** memakai `->middleware('module:kunci_modul')`. Beberapa kunci dipisahkan `|`, cukup salah satu yang dimiliki. Kunci yang sah ada di `RolePermission::defaultModules()`. Rute baru tanpa `module:` dianggap bug keamanan.
3. API ingest (`/api/v1/ingest/*`, `/api/v1/poldas`) memakai middleware `ingest.token` (`INGEST_API_TOKEN`; bila kosong → 503).
4. Akun yang kebal hapus/nonaktif **di kode**: `id = 1`, username `kadivhumas`, dan `superadmin` (`SystemModuleController`). Pengguna tidak boleh menghapus atau menonaktifkan akunnya sendiri.
5. Jangan menulis kata sandi, token API, atau isi `.env` ke kode, dokumen, maupun commit.
6. Rahasia (token Apify, kunci Gemini, kata sandi) **tidak boleh** masuk props Inertia/respons ke browser. Kirim status (`*_set`) dan petunjuk tersamar saja; token hanya dikelola admin di `/settings/ai`. Menu dan tab yang tampil harus sama dengan yang diizinkan middleware `module:` (`NavigationSweepTest`).

### 3.4. Frontend
1. Hanya `<script setup>` (tanpa Options API). Navigasi internal pakai `<Link>` dari `@inertiajs/vue3`. Tautan eksternal pakai `<a target="_blank" rel="noopener noreferrer">`.
2. **Dua tema**: kelas `.dark` / `.light` pada `<html>`, disimpan di `localStorage('simod_theme')`, dengan skrip anti-flash di `resources/views/app.blade.php`. Selalu sediakan varian terang dan gelap. Jangan meng-hardcode teks hitam yang tidak terbaca di mode gelap (atau sebaliknya). Pakai token di `resources/css/app.css`.
3. **Paritas angka UI**: angka pada dropdown atau filter (mis. `availablePortals`) dihitung dengan `computed()` dari data yang benar-benar dimuat, supaya angka di dropdown selalu sama dengan jumlah kartu yang tampil.
4. **Tanpa angka karangan**: bila data kosong, tampilkan `—` atau pesan jujur. Jangan memakai nilai bawaan palsu, gambar stok, atau klaim seperti "SITUASI KONDUSIF". Di backend kembalikan `null` (bukan 0/100/78) untuk nilai yang belum dapat dihitung. Di Vue dilarang pola `x || 29` / `x || 80` untuk angka; gunakan `??` atau `null` + teks "Belum ada data" (`||` juga mengubah nilai nyata 0 menjadi angka bawaan). Setiap label statistik (mis. "6 Polres", "4 Platform") harus dihitung dari data. Status/matriks harus punya definisi tertulis yang dapat dibaca pengguna (lihat doc 29 §6.1).
5. Notifikasi: flash dari backend (`success`/`error`) atau `window.dispatchEvent(new CustomEvent('simod-toast', { detail: { message, type: 'success'|'error' } }))`. Tombol aksi diberi status `disabled` dan teks proses untuk mencegah klik ganda.
6. SADEWA AI dibuka lewat `window.dispatchEvent(new CustomEvent('open-sadewa-copilot', { detail: { scope, subject } }))`.

### 3.5. Performa
- Biarkan `INERTIA_DEVTOOLS_ENABLED=false`.
- Data bersama di `HandleInertiaRequests::share()` dicache dengan kunci versi `simod.shell_version`. Model baru yang memengaruhi data bersama (menu, Polda, peran) harus memakai trait `App\Models\Concerns\BumpsShellCache`.
- Pada daftar ribuan baris, hindari `Carbon::diffForHumans()`/`format()` per baris. Gunakan `App\Support\FastTime`.

### 3.6. AI (Gemini) & kuota
- Semua aksi AI yang dipicu pengguna harus melewati `AiQuota` (batas harian per pengguna `ai_settings.daily_user_limit`, penahanan 10 menit saat Google membalas 429, log di `ai_usage_logs`).
- Analisis tidak diulang bila data sama (`source_hash` pada laporan media/medsos; `ai_analyzed_at` pada topik). Analisis paksa hanya dengan `force=1`.
- Rantai model: model pilihan admin → `gemini-3.5-flash-lite` → `gemini-3.1-flash-lite` → `gemini-3-flash-preview` → `gemini-3.8-flash` (`GeminiService::modelChain()`). Templat prompt kustom ada di `ai_settings.custom_prompt_template`.
- Pesan galat AI ke pengguna diringkas lewat `friendlyAiError()`. Detail teknis hanya masuk log.

### 3.7. RSS & berita
- **Kuota adil per sumber.** Jangan memotong secara global (`array_slice` setelah pengurutan waktu), karena portal daerah bisa hilang. Pemangkasan berimbang ada di `RssSyncService`.
- Sinkron memakai `Cache::lock('rss_sync_engine_lock', 600)`. Artikel `is_bookmarked = true` tidak pernah dipangkas.
- Portal yang diblokir Cloudflare atau bertautan rusak diambil lewat Google News RSS (`site:domain`).
- Artikel tanpa gambar memakai `image_url = null` dan placeholder "Tidak Ada Gambar Ditampilkan", bukan gambar stok.

### 3.8. Daftar berita Radar Media Online
- Pencarian, filter, penggabungan berita ganda, dan paginasi dilakukan di server lewat `App\Services\MediaArticleListing`; jangan mengirim ribuan artikel ke browser. Facet dihitung dengan semua filter aktif kecuali dirinya sendiri (paritas angka dropdown = jumlah kartu).
- Sentimen berita ditentukan `App\Support\NewsSentiment` (konteks Polri), unggahan medsos oleh `NewsSentiment::detectSocial()` (aturan sama + ungkapan warganet), pencarian oleh `App\Support\NewsSearch`. Jangan menulis ulang aturan sentimen di tempat lain. Alasan label untuk UI dari `explain()`/`explainSocial()` (popup `SentimentInfo.vue`). Metrik medsos yang tidak diketahui disimpan `null`, bukan 0/acak (doc 34). Setelah mengubah aturan, jalankan `php artisan media:reindex`.
- Aksi pada berita/topik (bookmark, topik, tag, analisis, crawl) wajib memanggil `authorizeTenantRecord()`.

### 3.9. Audit Log, Laporan Isu, dan data yang dihapus
- Audit Log boleh dibersihkan (manual/retensi), tetapi `audit.purged` dan `audit.auto_pruned` (`AuditLog::PROTECTED_ACTIONS`) tidak pernah ikut terhapus dan setiap pembersihan harus meninggalkan catatan baru. Jangan menambah jalur penghapusan log yang melewati `AuditLogController`/`audit:prune`.
- Tampilan Audit Log wajib memakai `AuditLog::scopeVisibleTo()` (tenant). Metadata sensitif disamarkan lewat `AuditLog::redact()`; jangan menampilkan atau mengekspor metadata mentah.
- Hapus `Issue` = hapus lunak (Sampah, dapat dipulihkan). Hapus permanen hanya dari Sampah dengan konfirmasi `HAPUS`. Relasi dari entitas lain ke `Issue` memakai `withTrashed()`. Tindakan arsip/hapus/pulihkan/massal selalu memeriksa tenant dan mencatat ke Audit Log dan riwayat status.

### 3.10. Isolasi tenant demo & data nyata
- Penentuan agregasi nasional **hanya** lewat `$this->aggregatesNational($selectedCode)`; jangan menulis ulang `$selectedCode === 'ALL'` untuk memutuskan cakupan data. Divhumas (`ALL`) hanya melihat data Divhumas kecuali `SIMOD_NATIONAL_AGGREGATE=true`.
- Penyimpanan berita selalu berkunci `(polda_id, url)`; jangan `updateOrCreate(['url' => ...])` saja.
- Jangan menambah data buatan di UI atau seeder bawaan. Data contoh hanya bila `SIMOD_SEED_SAMPLE=true`; fallback medsos buatan hanya bila `SIMOD_SOCIAL_SYNTHETIC=true`. Persiapan demo: `php artisan simod:demo-prepare` (doc 31).

### 3.11. Kode & dokumentasi
- Pertahankan PHPDoc dan komentar yang ada. Hapus kode mati alih-alih menyembunyikannya.
- Fitur yang tidak benar-benar berfungsi (mock, tombol yang hanya menampilkan notifikasi, pengaturan yang tidak ditegakkan) **jangan ditampilkan**. Bangun penegakannya atau hapus.
- Setiap perubahan perilaku: perbarui doc modul terkait dan doc 22 dalam commit yang sama. Untuk perubahan besar, tambahkan dokumen baru `docs/NN-judul.md` dan daftarkan di `docs/README.md`.
- Commit memakai Conventional Commits dalam Bahasa Indonesia, mis. `fix(media-intelligence): ...`.

---

## 4. Peta Navigasi (4 Pilar)

Sumber: `app/Models/MenuItem.php` (`seedDefaults`) + tabel `menu_items`, dapat diubah admin di `/settings/menus`. Kunci RBAC di kolom kanan.

```
1. KOMANDO & INTELIJEN
   Command Center            /                         dashboard_view
   Radar Media Online        /media-intelligence       media_intelligence_view (AI: media_intelligence_ai)
   Radar Media Sosial        /social-intelligence      social_intelligence_view (sync: social_intelligence_sync)
2. MANAJEMEN ISU & TUGAS
   Laporan Polres & Isu      /issues                   issues_view
   Input Laporan Baru        /issues/create            issues_create
   Antrean Verifikasi        /verification-queue       issues_view (aksi: issues_verify)
   Disposisi Tugas           /tasks                    tasks_execute | tasks_disposition
3. PUBLIKASI & DOKUMENTASI
   Publikasi Multi-Kanal     /publications             publications_manage
   Galeri Media Aman         /gallery                  gallery_access
   Laporan & Anev            /reports                  reports_export
4. ADMINISTRASI & SISTEM
   Personalia & Petugas      /personalia               personalia_manage
   Pengaturan Satwil & Mabes /settings/polda           polda_settings
   Keamanan & Pemeliharaan   8 sub-tab (komponen SecurityMaintenanceTabs.vue):
     Keamanan                /security                 security_center
     Hak Akses               /roles                    roles_matrix_manage
     Manajemen Menu          /settings/menus           roles_matrix_manage
     Audit Log               /audit-logs               security_center
     Backup Data             /database/backup          database_backup
     Pembersihan Berita      /maintenance/news-cleanup maintenance_ops
     Otomasi Sinkron RSS     /maintenance/rss-sync     maintenance_ops
     Mesin Intelijen AI      /settings/ai              security_center
   Profil Saya               /profile                  (cukup login)
```

Halaman yang **sudah dihapus** dan jangan dibuat ulang tanpa penegakan nyata: `/settings/mail` (SMTP), `/maintenance` (mode pemeliharaan), serta PIN dinas lama. (Masuk cepat PIN **perangkat tepercaya** kini ada dan benar-benar ditegakkan: lihat doc 30, addendum 7 Nov 2026.)

**RBAC:** 20 kunci modul dan 6 peran bawaan (`kadiv_humas`, `super_admin_mabes` = semua modul; `polda_leader` 16; `verifikator` 12; `analis` 8; `operator` 7). Matriks dapat diubah per Polda di `/roles` (tabel `role_permissions`). `User::isSuperAdmin()` bernilai benar untuk `super_admin_mabes`, `kadiv_humas`, `superadmin`, dan peran ini selalu lolos `module:*`.

---

## 5. Peta Kode Singkat

| Area | Lokasi |
|:---|:---|
| Resolusi tenant & otorisasi rekaman | `app/Http/Controllers/Controller.php` |
| Props bersama Inertia | `app/Http/Middleware/HandleInertiaRequests.php` |
| Middleware | `EnsureModulePermission` (`module`), `EnforceSessionTimeout` (`idle`), `EnsureIngestToken` (`ingest.token`), `PreventBackHistory`; didaftarkan di `bootstrap/app.php` |
| Controller utama | `DashboardController`, `MediaIntelligenceController`, `SocialIntelligenceController`, `IssueController`, `TaskController`, `PublicationController`, `PressReleaseController`, `SadewaController`, `SystemModuleController`, `AiSettingController`, `RssSyncController`, `DatabaseBackupController`, `NewsCleanupController`, `MenuManagementController`, `TenantController` |
| Services | `RssFeedService`, `RssSyncService`, `GoogleNewsCrawlerService`, `SocialSyndicationService`, `ApifySyncService`, `InstagramAccountMonitoringService`, `GeminiService`, `AiQuota`, `DatabaseBackupService`, `NewsCleanupService` |
| RBAC & menu | `app/Models/RolePermission.php`, `app/Models/MenuItem.php` |
| Alur status isu | backend `Issue::STATUS_GROUPS`; frontend `resources/js/utils/issueFlow.js` (`draft → monitoring → investigasi → klarifikasi → selesai → arsip`) |
| Layout & komponen | `resources/js/Layouts/EnterpriseLayout.vue`; `resources/js/Components/` (`SadewaCopilot`, `BriefingRefresh`, `SituationMap`, `IssueStepper`, `SecurityMaintenanceTabs`) |
| Halaman | `resources/js/Pages/**` (halaman terbesar: `MediaIntelligence/Index.vue`, `SocialIntelligence/Index.vue`, `Dashboard.vue`) |
| Rute | `routes/web.php`, `routes/api.php`, `routes/console.php` |
| Data awal | `database/seeders/DatabaseSeeder.php` (Divhumas + 5 Polda; 29 feed Sulbar; 33 feed nasional) |

---

## 6. Alur Kerja Agent

1. **Awal sesi:** `git status` (jangan menimpa perubahan orang lain yang belum di-commit), baca berkas ini dan doc 22, lalu jalankan `php artisan test` untuk baseline.
2. **Saat mengubah:** ikuti aturan §3, tambah atau ubah test, dan perbarui dokumen terkait.
3. **Sebelum selesai:** `php artisan test` + `npm.cmd run build` harus bersih. Laporkan apa yang belum dicek, mis. tampilan di browser.
4. **Commit/push** hanya bila diminta pengguna.

### Demo publik (ngrok)
Bila pengguna meminta "jalankan ngrok" / "buka tunnel demo", jalankan sebagai proses latar:
```bash
ngrok http --domain=humped-obvious-blatantly.ngrok-free.dev --host-header=simod-enterprise.test 80
```
`trustProxies` dan deteksi HTTPS di `AppServiceProvider` sudah menangani aset dan form melalui tunnel.

---

## 7. Indeks Dokumentasi

Daftar lengkap ada di [`docs/README.md`](docs/README.md). Yang paling sering dibutuhkan:

| Kebutuhan | Dokumen |
|:---|:---|
| Angka, rute, tabel, RBAC, temuan audit | [22. Peta Kode & Sumber Kebenaran](docs/22-peta-kode-dan-sumber-kebenaran.md) |
| Tenant & arsitektur | [01](docs/01-arsitektur-dan-multi-tenant.md), [13](docs/13-keamanan-tenant-dan-mesin-intelijen-ai.md) |
| Pencarian server, kesegaran data, matriks Satwil, Command Center data nyata | [29](docs/29-radar-media-pencarian-server-dan-command-center-data-nyata.md) |
| Audit Log, keamanan, kesiapan hosting, arsip/hapus laporan | [30](docs/30-audit-log-keamanan-hosting-dan-arsip-hapus-laporan.md) |
| Demo Divhumas + Sulbar, deploy Hostinger | [31](docs/31-demo-divhumas-sulbar-dan-panduan-deploy-hostinger.md) |
| Tren pencarian, SADEWA cari internet, pantauan banyak KOL | [33](docs/33-tren-pencarian-sadewa-internet-dan-pantauan-banyak-kol.md) |
| Metodologi sentimen, popup alasan, data medsos jujur | [34](docs/34-metodologi-sentimen-dan-data-medsos-jujur.md) |
| Social listening lintas platform, failover Apify, ringkasan komentar | [35](docs/35-social-listening-lintas-platform-failover-apify-dan-ringkasan-komentar.md) |
| Radar Media Online, RSS, Topik Atensi | [03](docs/03-modul-media-intelligence-dan-rss.md), [10](docs/10-daftar-rss-feed-resmi-sulbar.md), [14](docs/14-katalog-rss-media-online-nasional.md), [18](docs/18-optimalisasi-ux-dossier-dan-arsitektur-4-pilar-media-intelligence.md), [23](docs/23-optimalisasi-performa-dan-ux-radar-media-online.md) |
| Radar Media Sosial | [11](docs/11-modul-radar-media-sosial-apify.md), [19](docs/19-optimalisasi-radar-media-sosial-dan-sintesis-ai-sadewa.md), [25](docs/25-peta-grafik-dan-pantauan-instagram.md) |
| Isu, tugas, publikasi, administrasi | [24](docs/24-penyederhanaan-isu-tugas-publikasi-dan-administrasi.md), [04](docs/04-modul-manajemen-isu-dan-ews.md), [05](docs/05-modul-publikasi-dan-siaran-pers.md), [06](docs/06-modul-disposisi-tugas-dan-audit-trail.md) |
| AI: SADEWA, briefing, kuota | [26](docs/26-asisten-sadewa-multi-sumber-dan-riwayat-percakapan.md), [27](docs/27-perbarui-briefing-command-center.md), [28](docs/28-batas-pemakaian-ai-dan-penghematan-kuota.md) |
| RBAC & personalia | [12](docs/12-modul-personalia-dan-rbac-roles.md) |
| Setup & deployment | [PANDUAN_SETUP](docs/PANDUAN_SETUP_KOMPUTER_RUMAH.md), [08](docs/08-panduan-deployment-dan-virtual-host.md), [02](docs/02-perintah-artisan-dan-cli.md) |
| Konvensi multi-agent | [21](docs/21-standarisasi-multi-agent-dan-sinkronisasi-lintas-model.md) |

Dokumen bernomor lama (≤ 21) sebagian berisi **catatan sejarah perubahan**. Angka test atau fitur di dalamnya bisa jadi snapshot masa lalu; yang berlaku adalah doc 22 dan kode.
