# 🏠 Panduan Setup SIMOD Enterprise di Laptop / Komputer Rumah
## *Arsitektur Modern Laravel: 100% Otomatis Menggunakan Migrations & Seeders (Tanpa File SQL Mentah)*

Dokumen ini disusun sebagai panduan langkah demi langkah bagi pengembang atau personel yang ingin menjalankan proyek **SIMOD Enterprise** di komputer rumah/laptop lain agar berjalan persis sama dengan lingkungan kantor pusat tanpa memerlukan file dump `.sql` mentah manual.

---

## 🏛️ 1. Mengapa Tidak Memerlukan File `.sql` Mentah?

Dalam standar industri arsitektur modern Laravel:
1. **Reproducible Database**: Seluruh struktur tabel (36 tabel), data satwil (Divhumas Mabes + 5 Polda beserta Polres jajaran), 62 portal RSS feeds (33 Divhumas Mabes + 29 Sulbar), 103 postingan patroli siber multi-platform (Instagram, TikTok, 𝕏, YouTube), 4 pilar menu navigasi dinamis, dan matriks hak akses RBAC dibangun secara otomatis melalui **Migration & Seeders**.
2. **Bersih & Aman dari Konflik Git**: File `.sql` besar rentan memicu merge conflict di GitHub dan mengekspos kredensial sensitif. Seluruh file cadangan `.sql` telah dimasukkan ke dalam `.gitignore`.
3. **Satu Perintah untuk Semua**: Cukup jalankan satu perintah `php artisan migrate:fresh --seed`, seluruh basis data akan terisi lengkap dan siap digunakan dalam hitungan detik.

---

## ⚡ 2. Langkah Cepat Setup di Komputer Rumah

### Langkah 1: Clone atau Tarik Pembaruan Terakhir dari GitHub
Buka terminal (PowerShell atau Git Bash):

```bash
# Jika proyek sudah ada di laptop rumah, masuk ke foldernya dan pull:
cd d:/laragon/www/simod-enterprise
git pull origin main

# ATAU jika baru pertama kali diinstal di laptop rumah:
cd d:/laragon/www
git clone https://github.com/cpiet/simod-enterprise.git
cd simod-enterprise
```

---

### Langkah 2: Instal Dependencies PHP & Node.js
Pastikan Laragon sudah aktif (PHP 8.2+ dan Composer):

```bash
# 1. Instal dependensi backend Laravel
composer install

# 2. Instal dependensi frontend Vue 3 & Vite
npm install
# (Jika PowerShell membatasi script npm, gunakan: npm.cmd install)
```

---

### Langkah 3: Konfigurasi File Environment (`.env`)
Salin file template `.env.example` menjadi `.env`:

```bash
cp .env.example .env
php artisan key:generate
```

Buka file `.env` di VS Code / editor teks, pastikan konfigurasi basis data mengarah ke MySQL Laragon Anda:
```env
APP_NAME="SIMOD Enterprise"
APP_ENV=local
APP_KEY=base64:... (otomatis dibuat oleh key:generate)
APP_DEBUG=true
APP_URL=http://simod-enterprise.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simod_enterprise
DB_USERNAME=root
DB_PASSWORD=
```

> **Opsional**: Anda juga dapat memasukkan token Apify default pada `.env` jika diinginkan:
> ```env
> APIFY_API_TOKEN=apify_api_xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
> ```
>
> **Wajib bila memakai worker Python** (`scraper/media_crawler.py`): isi `INGEST_API_TOKEN` dengan string acak panjang dan set variabel lingkungan yang sama pada worker (`SIMOD_INGEST_TOKEN`). Bila kosong, API ingest menolak semua permintaan (HTTP 503).

---

### Langkah 4: Buat Database & Jalankan Migrasi + Seeder Otomatis

1. Pastikan servis **MySQL** pada Laragon sudah berstatus **Running** (tekan tombol *Start All* di Laragon).
2. Buat database baru bernama `simod_enterprise` (bisa lewat HeidiSQL di Laragon atau terminal):
   ```sql
   CREATE DATABASE IF NOT EXISTS simod_enterprise;
   ```
3. Jalankan migrasi dan seeder komprehensif:
   ```bash
   php artisan migrate:fresh --seed
   ```
   > 💡 **Apa yang terjadi saat perintah ini berjalan?**
   > - Membuat seluruh 36 tabel sistem.
   > - Membangun akun pimpinan (Kadivhumas, Superadmin Mabes, Kabid Humas Sulbar, akun developer `cpiet`).
   > - Memasukkan 33 feed resmi nasional Divhumas Mabes dan 29 portal media Sulbar.
   > - Memasukkan 103 postingan radar media sosial riil lengkap dengan metrik views, likes, shares, virality, dan tagar aktual.
   > - Menginisialisasi 4 pilar menu navigasi dinamis dan matriks wewenang peran (RBAC).

---

### Langkah 5: Kompilasi Aset Frontend (Vite)

```bash
# Untuk produksi lokal siap pakai:
npm run build
# (atau npm.cmd run build)

# ATAU untuk mode pengembangan dengan Hot Module Reload (HMR):
npm run dev
# (atau npm.cmd run dev)
```

---

### Langkah 6: Konfigurasi VirtualHost Laragon Apache

Laragon biasanya otomatis membuat virtual host. Pastikan file konfigurasi Apache mengarah ke folder `/public`:
* Buka menu Laragon: **Menu -> Apache -> sites-enabled -> auto.simod-enterprise.test.conf**
* Pastikan baris `DocumentRoot` berakhiran `/public`:
  ```apache
  <VirtualHost *:80>
      DocumentRoot "D:/laragon/www/simod-enterprise/public"
      ServerName simod-enterprise.test
      ServerAlias *.simod-enterprise.test
      <Directory "D:/laragon/www/simod-enterprise/public">
          AllowOverride All
          Require all granted
      </Directory>
  </VirtualHost>
  ```
* Muat ulang Apache: **Menu -> Apache -> Reload** (atau restart Laragon).

---

## 🔑 3. Daftar Kredensial Akun Login Resmi

Buka browser dan akses: `http://simod-enterprise.test/login`

| No | Peran / Posisi | Username / Email | Cakupan Otoritas |
|:---|:---|:---|:---|
| 1 | **Super Admin Mabes** | `superadmin` / `superadmin@polri.go.id` | Akses penuh 34 Polda, Manajemen Keamanan, Backup, AI & Audit Log |
| 2 | **Kadivhumas Polri** | `kadivhumas` / `kadivhumas@polri.go.id` | Pucuk Pimpinan Tertinggi, Executive Briefing & Command Center |
| 3 | **Akun Developer / Tech** | `cpiet` / `alumnidarus@gmail.com` | Superadmin Mabes & Hak Akses Khusus Pengembang |
| 4 | **Pimpinan Polda Sulbar** | `kabid_sulbar` / `kabid.sulbar@polri.go.id` | Komando Kehumasan Wilayah Sulawesi Barat |

> **Kata sandi awal tidak lagi dicantumkan di dokumen.** Kata sandi akun awal untuk **lingkungan pengembangan lokal** ditetapkan di seeder [`EnterpriseDatabaseSeeder.php`](../database/seeders/EnterpriseDatabaseSeeder.php) (dan `PersonaliaSeeder.php`). Login kini **wajib username + kata sandi yang valid**; tidak ada lagi bypass, kata sandi demo, maupun mode PIN di halaman login. Pada deployment non-lokal, **ganti seluruh kata sandi seed** lewat menu Personalia → *Reset Password* segera setelah `migrate --seed`.

---

## 🎯 4. Fitur Pembaruan Terakhir yang Langsung Aktif

Setelah Anda menjalankan langkah di atas di laptop rumah, seluruh fitur mutakhir sudah siap digunakan:
1. **Arsitektur Dual-Mode Radar Tren Medsos (Tab 03)**:
   - **Mode 1 — Lensa Kamtibmas & Humas Polri**: Pemantauan tagar kedinasan, penegakan hukum, citra Polri, dan filter platform (Instagram, TikTok, 𝕏, YouTube).
   - **Mode 2 — Lensa Tren Publik Medsos Indonesia**: Menangkap tren warganet Indonesia secara holistik lintas 5 platform (𝕏, YouTube, Instagram, TikTok, Facebook). Termasuk 15 topik teratas 𝕏 Indonesia (`#TOKEN50RBCUMA40RB`, `Kompak Kunci Keberhasilan`, `PLN 50rb`, dll).
   - **Deteksi Kerawanan Siber & Kamtibmas**: Algoritma otomatis menyaring topik yang berindikasi phishing token listrik, kerumunan malam Halloween, dan kerawanan lalu lintas.
   - **Aksi Terpadu 1-Klik**: Tombol `🚨 Eskalasi Jadi Isu` untuk membuat laporan ke `/issues/create` dengan data terisi otomatis, tombol `🔍 Pantau di Radar`, dan `⚡ Refresh Tren Publik (Apify)`.
2. **Katalog Format Tren & Rekomendasi Actor Apify per Platform (Tab 04)**:
   - Tabel panduan format tren dan rekomendasi Actor resmi Apify (`easyapi/twitter-trending-topics-scraper`, `streamers/youtube-scraper`, `yugenox/instagram-trends-scraper`, `apify/facebook-posts-scraper`, `clockworks/free-tiktok-scraper`).
3. **Jam Puncak Obrolan Netizen (*Peak Hours Intelligence*)**:
   - Deteksi jam puncak warganet siang & malam dengan rekomendasi jam rilis konter narasi sesuai zona waktu aktif (`WIB` atau `WITA`).
4. **Direktori Key Opinion Leader (Tab 02)**:
   - Dilengkapi jumlah postingan dan total jangkauan views warganet.
5. **Multi-Token Apify Scraper Pool**:
   - Fitur uji koneksi token Apify bekerja mulus tanpa error CSRF Token Mismatch berkat sinkronisasi token otomatis.
   - Fallback otomatis ke konfigurasi `.env` jika input di web kosong.

---

## ❓ 5. Panduan Pemecahan Masalah (*Troubleshooting*)

* **Q: Halaman menampilkan 403 Forbidden atau hanya daftar folder?**
  * **Solusi**: Periksa `DocumentRoot` Apache Laragon, pastikan mengarah ke `D:/laragon/www/simod-enterprise/public`, bukan folder root proyek.
* **Q: PowerShell memblokir perintah `npm`?**
  * **Solusi**: Jalankan perintah menggunakan ekstensi batch: `npm.cmd install` dan `npm.cmd run build`.
* **Q: Ingin mereset ulang seluruh data seperti keadaan baru?**
  * **Solusi**: Cukup jalankan perintah sakti:
    ```bash
    php artisan migrate:fresh --seed
    ```
    Seluruh database Anda akan langsung segar kembali dengan data operasional lengkap.

---
*Dokumen ini dirancang oleh Tim Antigravity untuk memastikan portabilitas sistem SIMOD Enterprise antar-perangkat secara instan, aman, dan berstandar industri modern.*

