# SIMOD Enterprise (Sistem Monitoring dan Opini Publik Digital)

Platform intelijen media, monitoring opini publik, dan manajemen operasional kehumasan terpadu berbasis multi-tenant untuk Kepolisian Negara Republik Indonesia (Mabes Polri, Polda, dan Polres/Satwil).

---

## 🌟 Fitur Utama

- **Multi-Tenant Architecture**: Pemisahan data dan konteks operasional antar Polda dan jajaran Satwil (Polres/Polresta) di seluruh Indonesia.
- **Media Online Intelligence (Radar 29 RSS)**: Agregasi paralel 29 portal media resmi Sulbar & nasional dengan Fair Quota, Mutex Lock, dan bypass Cloudflare Google News RSS.
- **Radar Media Sosial (Cyber Patrol & Social Listening)**: Pemantauan opini publik di 𝕏 (Twitter), TikTok, Instagram, dan YouTube terintegrasi Apify Multi-API Token Pool dengan auto-failover, pelacakan akun target khusus, dan ekstraksi thumbnail HD video.
- **Early Warning System (EWS)**: Deteksi dini isu viral atau berpotensi krisis dengan klasifikasi urgensi (Tinggi, Sedang, Rendah).
- **Issue & Crisis Management**: Siklus penanganan isu menyeluruh dari eskalasi hingga resolusi lengkap dengan Counter Narasi & Press Release resmi.
- **Antrean Verifikasi (Verification Queue)**: Validasi dan kurasi link rilis, pemberitaan, dan media pendukung oleh operator Humas.
- **Manajemen Personalia & Satker**: Struktur hierarki komando dan distribusi personel Humas yang dinamis.
- **Laporan & Anev (Analisis dan Evaluasi)**: Ekspor dan rekapitulasi performa kehumasan harian, mingguan, dan bulanan.
- **Audit Trails & Security Center**: Pencatatan aktivitas log keamanan, manajemen backup basis data otomatis, dan pembersihan retensi berita.

---

## 🛠️ Tech Stack

- **Backend**: Laravel 12 (PHP 8.2+)
- **Frontend**: Vue 3 (Composition API `<script setup>`), Inertia.js v2, Tailwind CSS v4
- **Database**: MySQL 8.x / MariaDB (Lokal) + SQLite (Automated Tests)
- **Icons**: Lucide Vue Next
- **Crawler & Intelligence**: Apify API Scraper Engine & RSS Multi-Curl
- **Tunneling Live Demo**: Ngrok Multi-Tenant Reverse Proxy (`trustProxies` & HTTPS enforcement)

---

## 🚀 Setup & Instalasi

### 1. Clone Repositori
```bash
git clone https://github.com/cpiet/simod-enterprise.git
cd simod-enterprise
```

### 2. Instalasi Dependency
```bash
composer install
npm install
```

### 3. Konfigurasi Environment
```bash
cp .env.example .env
php artisan key:generate
```
Sesuaikan konfigurasi database di file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simod_enterprise
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Migrasi & Seeding Database
```bash
php artisan migrate --seed
# Atau seeder enterprise lengkap:
php artisan db:seed --class=EnterpriseDatabaseSeeder
```

### 5. Jalankan Server Pengembangan
```bash
# Terminal 1: Vite Dev Server
npm run dev

# Terminal 2: Laravel Server
php artisan serve
```

---

## 📚 Dokumentasi & Panduan Pengembang

Dokumentasi lengkap sistem SIMOD Enterprise terpusat pada direktori [`docs/`](./docs/README.md) dan panduan arsitektur khusus untuk AI Agents:

- **[AGENTS.md](./AGENTS.md)**: Panduan instruksi, arsitektur 4 pilar, dual-theme engine, aturan kuota RSS 29 portal, dan protokol pengembangan untuk AI Coding Agents (Codex, Claude, Gemini, Antigravity).
- **[Katalog Lengkap Modul](./docs/09-katalog-lengkap-19-modul-simod-enterprise.md)**: Daftar seluruh modul operasional, hak akses, dan rute navigasi.
- **[Modul Media Intelligence & RSS](./docs/03-modul-media-intelligence-dan-rss.md)**: Panduan agregasi berita, executive briefing AI, dan naskah WhatsApp pimpinan.
- **[Modul Radar Media Sosial & Apify](./docs/11-modul-radar-media-sosial-apify.md)**: Panduan monitoring 𝕏, TikTok, Instagram, YouTube, Multi-API Token Pool, akun target, dan thumbnail video HD.
- **[Daftar 29 RSS Feed Resmi](./docs/10-daftar-rss-feed-resmi-sulbar.md)**: Inventaris lengkap portal media online Sulbar, endpoint syndication, dan fallback Google News RSS.
- **[Perintah CLI Artisan & Penjadwal](./docs/02-perintah-artisan-dan-cli.md)**: Panduan sinkronisasi otomatis, retensi data, dan CLI automation.

---

## 🔒 Lisensi & Hak Cipta
Hak cipta dilindungi undang-undang. Dikembangkan untuk kebutuhan operasional kehumasan kepolisian.
