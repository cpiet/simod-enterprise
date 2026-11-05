# 32. Audit Total Pra-Unggah (5 November 2026)

Status ringkas: **Minor Fixes Needed** → setelah langkah "Wajib dilakukan pemilik" di bawah, **Ready** untuk demo Divhumas + Sulbar di Hostinger.
Baseline: `php artisan test` 256 lulus (1941 assertion); `npm.cmd run build` bersih.

## 1. UI/UX & Mode Terang
- Pemindai kontras otomatis (headless Chrome, 21 halaman, WCAG AA 4.5:1) di mode terang: awal ±150 elemen di bawah ambang → tersisa ±30, hampir semuanya tombol `disabled` (opacity sengaja) atau lencana kecil.
- Perbaikan: aturan `html.light` di akhir `resources/css/app.css` (slate-400/500, amber, emerald, cyan, rose, lencana latar gelap/solid) dan lencana peran di `Settings/Roles.vue`.
- **Sisa (risiko rendah):** lencana "Instagram" (pink) di Command Center, badge "Critical Spike" di Radar Sosial, hitungan `font-mono opacity-70` di Audit Log (3,6:1). Periksa visual di browser.
- Alur: halaman galat resmi (`Error.vue`), konfirmasi aksi destruktif (`ConfirmDialog`, ketik `HAPUS`), tombol `disabled` saat proses sudah ada. Belum diuji manual: responsif seluler.

## 2. Multi-domain / multi-hosting
- Tidak ada URL host yang ditanam di kode aplikasi. Satu-satunya default: worker Python `scraper/` (`http://127.0.0.1:8000/...`, dapat ditimpa env).
- Aset: Vite `public/build` relatif; `APP_URL`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE` ada di `.env.hostinger.example`. `TRUSTED_PROXIES` dapat dibatasi.
- CORS: tidak ada `config/cors.php`, jadi tidak ada header CORS (aman untuk aplikasi satu-asal). API ingest memakai token, bukan CORS.
- Temuan: Google Fonts dimuat dari CDN eksternal di `resources/views/app.blade.php` (bukan blocker; CSP dibuat sesuai).

## 3. White-labeling AI
- Nama vendor/model/kunci hanya terlihat admin (`isAiAdmin()`: Super Admin atau izin `security_center`). Pengguna lain melihat "Analisis AI"/"SADEWA AI" dan pesan galat netral. Diuji di `ProductionHardeningTest`.
- Tetap menyebut vendor (disengaja): `/settings/ai`, pemeliharaan, dokumentasi teknis.

## 4. Kode mati (sudah dihapus)
- Seeder tak terpakai: `IssueAndReleaseSeeder`, `MediaAndVerificationQueueSeeder`, `PublicationsAndTasksSeeder`, `PersonaliaSeeder`.
- Perintah lama: `simod:scrape-rss`, `simod:sync-feeds` (diganti `rss:sync`).
- Dependensi npm tak terpakai: `pinia` (terdaftar tanpa store), `@lucide/vue` (duplikat `lucide-vue-next`).
- Tidak ditemukan `console.log`/`debugger` di `resources/js`; semua `v-html` terbatas pada label paginasi dan markdown SADEWA yang disanitasi.
- Tidak ada komponen Vue atau util yatim.

## 5. Keamanan
Sudah diterapkan: header keamanan + CSP dasar, CSRF penuh (tanpa pengecualian), logout hanya POST, throttle endpoint AI/login, SSRF guard (`SafeUrl`), verifikasi TLS, kunci API tidak dikirim ke browser, perbaikan IDOR Radar Media, penegakan `module:` pada semua rute modul.
- **Diperbaiki sekarang:** kata sandi akun awal tidak lagi ditanam di `EnterpriseDatabaseSeeder` (repositori publik). Seeder memakai `SIMOD_SEED_PASSWORD` bila diisi, jika tidak membuat kata sandi acak dan menampilkannya sekali di akhir `db:seed`.
- **Terbuka (sedang):** `ai_settings.gemini_api_key` dan `api_keys_pool` tersimpan polos di basis data. Rekomendasi: cast `encrypted` + migrasi kolom `text` + enkripsi ulang data lama. Belum dikerjakan agar tidak merusak data yang ada.
- **Terbuka (sedang):** riwayat git/`.env` lokal pernah memuat kunci Gemini; **putar (rotate) kuncinya**.
- **Terbuka (rendah):** kata sandi lama di berkas test hanya fixture test, bukan akun produksi; tetap ganti kata sandi akun yang sudah pernah di-seed dengan nilai lama (git history).

## Wajib dilakukan pemilik sebelum unggah
1. `APP_ENV=production`, `APP_DEBUG=false`, `php artisan migrate --force`, cron `schedule:run` tiap menit.
2. Ganti kata sandi semua akun yang pernah di-seed dengan kata sandi lama; putar `GEMINI_API_KEY` dan `INGEST_API_TOKEN`.
3. `php artisan simod:demo-prepare` (dry-run) lalu `--force --sync` saat siap.
4. Cek visual mode terang dan seluler di browser.

## Addendum (5 Nov 2026): perbaikan AI setelah uji lapangan
- Bug: penahanan kuota tidak dicabut saat kunci diganti; format kunci `AQ.…` ditolak validasi; rantai model terlalu pendek; angka sentimen laporan medsos disalin dari contoh prompt. Semua diperbaiki (doc 28 bagian 7).
- Baru: indikator pemakaian AI untuk pengguna dan panel admin.
