# 🚀 31. Demo Divhumas + Polda Sulbar (Data Nyata) & Panduan Deploy ke Hostinger

Dokumen ini menjelaskan konfigurasi demo **Divhumas Polri** dan **Polda percontohan Sulawesi Barat**, cara membersihkan data contoh, dan langkah deploy ke **Hostinger**. Baseline: **235 tests (1850 assertions)**, 129 rute.

---

## 1. Prinsip demo

1. **Dua tenant saja:** Divhumas (Mabes) dan Sulbar. Polda lain nonaktif (data intinya tetap, hanya disembunyikan).
2. **Terisolasi:** tampilan Divhumas **hanya** berisi data Divhumas; pengguna Sulbar hanya melihat data Sulbar dan tidak pernah melihat Divhumas. RSS, medsos, isu, tugas, dan laporan AI dipisah per Polda (`polda_id`).
3. **Data nyata saja:** tidak ada data contoh dan tidak ada fallback buatan.
4. Kadivhumas/Super Admin dapat berpindah ke tenant Sulbar lewat pemilih wilayah (`POST /tenant/switch`) untuk meninjau; Sulbar tidak dapat berpindah ke Divhumas.

### 1.1. Tiga saklar konfigurasi (`config/simod.php`, env)

| Env | Bawaan | Arti |
|:---|:---|:---|
| `SIMOD_NATIONAL_AGGREGATE` | `false` | Mode Nasional (`ALL`) hanya Divhumas. `true` = kembali ke gabungan seluruh Polda aktif (untuk peluncuran nasional kelak). |
| `SIMOD_SEED_SAMPLE` | `false` | `db:seed` hanya mengisi data inti (Polda, Polres + koordinat, akun, feed RSS, menu, hak akses, pengaturan medsos). `true` = juga isu, tugas, publikasi, berita historis, postingan, topik contoh. |
| `SIMOD_SOCIAL_SYNTHETIC` | `false` | Mematikan seluruh pembangkit data medsos buatan: postingan sintetis, templat "mock", dan **daftar tren publik statis** (sebelumnya "#TOKEN50RBCUMA40RB" dst. ditampilkan sebagai tren realtime, padahal bukan hasil penarikan data). |

Aturan penerapan: helper `Controller::aggregatesNational()` menggantikan penentuan agregasi nasional di semua controller data. Peta Command Center pada tenant Divhumas menampilkan pin Mabes dan wilayah yang dihitung dari kata kunci pada **pemberitaan Divhumas sendiri**, tanpa pin tenant Polda.

### 1.2. RSS terpisah per Polda
- Sulbar: 29 feed (daftar sama dengan yang Anda tempel; Malaqbi dan Bacasaja ditarik lewat Google News `site:` karena `/feed` langsungnya diblokir Cloudflare atau rusak). Divhumas: feed nasional sendiri.
- **Perbaikan penting:** tiga feed ada di dua Polda (Antara Terkini, CNN Nasional, Detik Berita). Sebelumnya berita disimpan berdasarkan URL saja, sehingga sinkron terakhir "mengambil" berita itu dari Polda lain. Kini kuncinya **(polda_id, url)** di semua jalur penyimpanan (sinkron terjadwal, refresh manual, perintah lama, API ingest, crawler Google News). Diuji di `DivhumasIsolationTest`.

### 1.3. Medsos tanpa data buatan
- Tanpa token Apify: radar memakai sindikasi publik (Google News) saja. Bila tidak ada hasil, tampil pesan jujur ("Belum ada percakapan publik yang ditemukan… tidak ada data buatan yang ditambahkan"), bukan postingan karangan.
- Tab Tren Publik menampilkan keterangan "Sumber data tren publik belum dihubungkan" dan jumlah 0 sampai ada sumber nyata. Tombol refresh tren melaporkan galat yang sama.

---

## 2. Menyiapkan demo: `php artisan simod:demo-prepare`

```bash
php artisan simod:demo-prepare            # DRY-RUN: hanya melaporkan apa yang akan dihapus
php artisan simod:demo-prepare --force    # jalankan
php artisan simod:demo-prepare --force --sync          # lalu sinkron RSS Divhumas dan Sulbar
php artisan simod:demo-prepare --force --keep-articles # pertahankan berita yang ada
```

- **Dibersihkan:** isu (+riwayat, bukti), tugas, publikasi, siaran pers, peringatan dini, topik atensi (+tag), laporan analisis AI media/medsos, postingan dan kasus medsos, riwayat chat SADEWA, catatan pemakaian AI, dan **berita non-bookmark** (diisi ulang dari RSS nyata).
- **Dipertahankan:** akun, Polda, Polres, feed RSS, pengaturan, menu, hak akses, berita ber-bookmark, log audit.
- Hanya Divhumas dan Sulbar yang diaktifkan; waktu "sinkron terakhir" medsos bawaan seeder (angka karangan) dikosongkan.
- Tercatat di Audit Log sebagai `demo.prepare`. Tanpa `--force` tidak ada yang berubah.
- Pada basis data lokal pengembang saat dokumen ini ditulis, dry-run melaporkan antara lain 16 isu, 296 postingan medsos, 14 laporan AI, dan 2.584 berita.

---

## 3. Deploy ke Hostinger (Web/Cloud Hosting, PHP + MySQL)

### 3.1. Prasyarat
- Paket dengan **PHP 8.2 atau lebih baru**, **MySQL**, dan idealnya **akses SSH** (agar `artisan` dan cron mudah). Tanpa SSH, jalankan perintah lewat Terminal di hPanel bila tersedia.
- Domain/subdomain dengan **SSL** aktif (hPanel → SSL) dan paksa HTTPS.
- Di hPanel → PHP Configuration: pilih PHP **8.2+**, aktifkan ekstensi `mbstring`, `openssl`, `curl`, `fileinfo`, `zlib`, `xml`, `ctype`, `tokenizer`, `pdo_mysql`; atur `max_execution_time` **120+**, `memory_limit` **256M+**, `upload_max_filesize` 20M, `post_max_size` 25M.

### 3.2. Langkah
1. **Buat basis data** (hPanel → Databases → MySQL): catat nama database, pengguna, dan kata sandi.
2. **Unggah kode.** Pilihan: (a) Git di hPanel dari repositori Anda, atau (b) unggah ZIP lewat File Manager/SFTP. Letakkan proyek di **luar** `public_html` bila memungkinkan (mis. `~/simod`).
3. **Dokumen akar harus `public/`.** Atur domain agar menunjuk ke folder `public` proyek. Bila hosting hanya memakai `public_html`: pindahkan isi folder `public/` ke `public_html/`, ubah dua jalur di `index.php` (`require __DIR__.'/../simod/vendor/autoload.php'` dan `bootstrap/app.php`), atau buat symlink `public_html` → `~/simod/public` lewat SSH.
4. **Dependensi PHP:** `composer install --no-dev --optimize-autoloader` (SSH). Tanpa Composer di server: jalankan perintah itu di komputer dengan PHP 8.2 lalu unggah folder `vendor/`.
5. **Aset frontend:** `public/build` **tidak** disimpan di Git. Jalankan `npm.cmd run build` di komputer Anda lalu unggah isi `public/build/`.
6. **`.env`:** salin `.env.hostinger.example` menjadi `.env` di server dan isi nilai bertanda `<...>`. Lalu:
   ```bash
   php artisan key:generate --force
   php artisan migrate --force
   php artisan db:seed --force         # data inti saja (SIMOD_SEED_SAMPLE=false)
   php artisan storage:link
   chmod -R 775 storage bootstrap/cache
   php artisan config:cache && php artisan route:cache && php artisan view:cache
   ```
7. **Cron (wajib)**: hPanel → Advanced → **Cron Jobs**, tambah tiap menit:
   ```
   * * * * * /usr/bin/php /home/<user>/<jalur-proyek>/artisan schedule:run >> /dev/null 2>&1
   ```
   Pastikan jalur `php` adalah versi 8.2+ (cek `php -v` di SSH; bila perlu pakai jalur PHP spesifik versi dari hPanel). Perintah yang sama tersedia siap-salin di **Audit Log → Penjadwal**.
8. **Persiapan demo:** `php artisan simod:demo-prepare` (dry-run), lalu `--force --sync`. Atau sinkron manual: `php artisan rss:sync --polda=ALL --force && php artisan rss:sync --polda=SULBAR --force`.
9. **Akun:** ganti **seluruh kata sandi bawaan seeder** lewat Personalia → Reset Password (atau perintah `tinker`, lihat PANDUAN_SETUP). Jangan memakai kata sandi lokal di server publik.
10. **Pemeriksaan akhir:** buka **Audit Log → Kesehatan server & kesiapan hosting** dan **Penjadwal**. Targetnya: HTTPS, `APP_DEBUG`, `APP_ENV`, versi PHP, batas PHP, folder tulis, ekstensi, dan penjadwal semuanya hijau.

### 3.3. Catatan
- **Kunci API (Gemini/Apify)** diisi dari menu Mesin Intelijen AI dan tersimpan apa adanya di basis data (lihat temuan terbuka doc 30 §6): batasi akses Super Admin dan jaga `.env` serta cadangan basis data.
- **Worker Python** (`scraper/`) tidak dijalankan di shared hosting; tidak diperlukan karena RSS ditarik langsung oleh aplikasi.
- **Tile peta** memakai OpenStreetMap publik; untuk penggunaan operasional gunakan penyedia berizin (`VITE_MAP_TILE_URL` pada build).
- **Pembaruan berikutnya:** unggah kode, `composer install --no-dev`, unggah `public/build` baru, `php artisan migrate --force`, lalu `php artisan config:cache route:cache view:cache` dan `php artisan optimize:clear` bila tampilan tidak berubah.
- **Rollback:** pulihkan cadangan dari menu Backup Data (jadwal cadangan memerlukan cron aktif).

## Addendum: Deploy di subfolder (mis. `https://domain/simod-v2/`) (7 Nov 2026)
- Aplikasi mendukung awalan subfolder tanpa mengubah alamat absolut di kode Vue (`/issues`, `/settings/ai`, dst.): `app.blade.php` mengekspos `window.__SIMOD_BASE__` (= `request()->getBasePath()`, kosong di akar domain); `resources/js/utils/basePath.js` (`BASE`, `withBase`, `stripBase`) dipakai oleh `http.onRequest` Inertia (`app.js`), interceptor axios (`bootstrap.js`), pencocokan menu aktif (`EnterpriseLayout.vue`), dan navigasi penuh halaman (`Error.vue`, `Settings/Backup.vue`).
- Build aset untuk subfolder: `ASSET_URL=https://domain/simod-v2 npm run build` (laravel-vite-plugin memakainya sebagai `base`). Build lokal biasa tanpa `ASSET_URL`.
- Susunan server yang disarankan: kode di `simod-v2-app/` SEJAJAR `public_html`; isi `public/` ke `public_html/simod-v2/` dengan `index.php` yang menunjuk `../../simod-v2-app/...` dan `$app->usePublicPath(__DIR__)`. `.env`: `APP_URL`, `ASSET_URL`, `SESSION_PATH=/simod-v2`.
- Diuji di peramban headless dengan server yang meniru subfolder: login, navigasi SPA antar menu, reload langsung, permintaan axios, dan aset berhasil; tanpa 404.
- Test: `ProductionHardeningTest::test_page_exposes_base_path_for_subfolder_deployments`.

## Addendum: Penarikan Google News saat membuat Topik Atensi (7 Nov 2026)
- Di balik Cloudflare + Hostinger, permintaan "Simpan topik" yang ikut menarik Google News dapat berakhir galat **520** walau data masuk. `MediaIntelligenceController::storeTopic` kini menyimpan topik lalu menjalankan penarikan **setelah respons terkirim** (`defer()`), sehingga permintaan cepat selesai; berita muncul beberapa detik kemudian (muat ulang). Kegagalan penarikan hanya dicatat di log, tidak menggagalkan penyimpanan topik.
- Tombol "Tarik berita" manual (`crawl-live`) tetap sinkron karena menampilkan hasil langsung.
- Galat 520 yang tetap muncul di jalur lain: cek log akses hPanel dan coba mode Cloudflare "DNS only" untuk memisahkan penyebab.
- Test: `MediaTopicDossierTest::test_topic_creation_pulls_news_after_response_without_blocking` (memakai tiruan crawler, tanpa jaringan).

## Addendum: cron terpasang, aset gagal dimuat (MIME ""), dan 429 (6 Nov 2026)
- **Cron produksi aktif** (dibuat lewat API Hostinger, terlihat di hPanel → Cron Jobs): `* * * * * /usr/bin/php /home/<user>/domains/<domain>/simod-v2-app/artisan schedule:run`. Pakai bentuk ini (mode "PHP" hPanel). Bentuk `cd <folder> && php artisan ...` terdaftar tetapi **tidak pernah tereksekusi** di server ini. Bukti cron berjalan: denyut `simod.scheduler.tick` (lihat Otomasi Sinkron RSS) diperbarui tiap menit.
- **Gejala keluhan klien:** konsol merah "Loading module ... blocked because of a disallowed MIME type ("")", modal putih kosong saat pindah halaman, dan halaman "429 Too Many Requests". Ketiganya respons **429 dari LiteSpeed/Hostinger** (pembatas per-IP), bukan dari Laravel (rute GET tidak di-throttle). Respons 429 tidak bertipe MIME sehingga modul JS ditolak, dan Inertia menampilkan respons non-Inertia sebagai modal.
- **Perbaikan:**
  - Tautan sidebar tidak lagi `prefetch`. Sebelumnya setiap kursor melewati menu memuat halaman penuh (PHP) di latar.
  - `vite.config.js` `manualChunks` menyatukan pustaka & ikon (`vendor`, `vendor-icons`, `vendor-markdown`). Berkas aset turun dari 72 menjadi 41, dan per halaman sekitar 6 permintaan.
  - `resources/js/app.js`: bila komponen/modul gagal dimuat, halaman dimuat ulang otomatis sekali (dijaga `sessionStorage` 30 detik agar tidak berulang); respons non-Inertia 429/5xx tampil sebagai notifikasi, bukan modal putih; 419 memuat ulang halaman.
  - `public/.htaccess`: aturan cache aset kini cocok untuk subfolder (`/build/assets/`).
- **Aturan deploy:** jangan menghapus `public_html/<subfolder>/build` sebelum mengekstrak build baru. Ekstrak di atasnya agar tab yang masih terbuka dengan hash lama tetap memuat asetnya; bersihkan aset lama paling cepat beberapa hari kemudian.

## Addendum: cadangan basis data di Hostinger (6 Nov 2026)
- Temuan: `db:backup` di produksi **selalu gagal** ("Call to undefined function shell_exec()"), karena Hostinger menonaktifkan `shell_exec`. Di PHP 8 fungsi yang dinonaktifkan memicu galat fatal walau dipanggil dengan `@`, sehingga jalur cadangan PHP/PDO yang sudah ada tidak pernah tercapai. Folder `storage/app/backups` produksi kosong.
- Perbaikan (`DatabaseBackupService`): `shell_exec` hanya dipanggil bila tersedia (`shell()`), dan biner `mysqldump`/`mysql` hanya dipakai bila `proc_open` tersedia (`canRunBinaries()`). Selain itu cadangan dan pemulihan memakai jalur PHP/PDO. Keputusan diambil sebelum proses dimulai, sehingga pemulihan tidak pernah berpindah jalur di tengah jalan.
- Verifikasi setelah deploy: `php artisan db:backup --force` harus menghasilkan berkas di `storage/app/backups`, dan menu Backup Data menampilkannya.
