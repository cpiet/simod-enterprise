# SIMOD Enterprise (Sistem Monitoring dan Opini Publik Digital)

Platform intelijen media, monitoring opini publik, dan manajemen operasional kehumasan terpadu berbasis multi-tenant untuk Kepolisian Negara Republik Indonesia (Mabes Polri, Polda, dan Polres/Satwil).

---

## 🌟 Fitur Utama

- **Multi-Tenant Architecture**: Pemisahan data dan konteks operasional antar Polda dan jajaran Satwil (Polres/Polresta) di seluruh Indonesia.
- **Radar Media Online (29 Portal RSS Feeds + Smart Dossier & Arsitektur 4 Pilar Terpadu)**: Agregasi paralel 29 portal media resmi Sulbar & 33 media nasional Divhumas Polri dengan Fair Quota, Mutex Lock, dan bypass Cloudflare. Dilengkapi fitur **Smart Dossier (Topik Atensi Pimpinan)** dengan layout 2-kolom responsif, sistem caching analisa Gemini AI (hemat token & pembacaan instan), baki rekomendasi tren Google News AI yang dapat dilipat (*collapsible*), **Mesin Ingesti Berita Internet Live On-Demand (Google News Crawler)**, serta **Arsitektur 4 Pilar Terpadu Zero-Redundancy** (01. Berita Online, 02. Executive Briefing & Sentimen + Format WA Resmi, 03. Early Warning / EWS, 04. Strategi Humas & Aksi) dan **SADEWA AI Copilot** (*Sistem Analisis Digital & Evaluasi Wacana Aktual*).
- **Radar Media Sosial (Cyber Patrol & Social Listening)**: Pemantauan opini publik di 𝕏 (Twitter), TikTok, Instagram, Facebook, dan YouTube terintegrasi Apify Multi-API Token Pool dengan auto-failover, pelacakan akun target khusus, ekstraksi thumbnail HD video, arsitektur **Dual-Mode Tren** (*Lensa Kamtibmas Polri* & *Lensa Tren Publik Medsos Indonesia*) dengan **Deteksi Kerawanan Siber & 1-Click Escalation**, serta analisis komprehensif: **Multi-Line Spike Chart Sentimen 7 Hari (Deteksi Anomali Krisis)**, **Visual Word Cloud Semantik**, dan **On-Demand Keyword Tracker**.
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

### 4. Migrasi & Seeding Database (Arsitektur Modern Laravel)
> [!TIP]
> **Tidak membutuhkan file `.sql` mentah!** Seluruh 24 tabel, 51 feed RSS, 103 postingan patroli siber aktual, akun pimpinan, menu navigasi, dan peran RBAC dibangun secara otomatis & instan melalui migrasi dan seeder resmi Laravel:

```bash
php artisan migrate:fresh --seed
```

### 5. Jalankan Server Pengembangan
```bash
# Terminal 1: Kompilasi aset Vite
npm run build
# (atau 'npm run dev' untuk live reload saat pengembangan)

# Terminal 2: Laravel Server (jika tidak menggunakan vhost Laragon)
php artisan serve
```

---

## 🔑 Kredensial Akun Login Bawaan

| Peran | Username / Email | Password Dinas | Cakupan Otoritas |
|:---|:---|:---|:---|
| **Super Admin Mabes** | `superadmin` / `superadmin@polri.go.id` | `MabesPolri#2026` | Akses penuh 34 Polda, Keamanan, Backup, AI & Audit Log |
| **Kadivhumas Polri** | `kadivhumas` / `kadivhumas@polri.go.id` | `DivhumasPolri#2026` | Pucuk Pimpinan Tertinggi, Command Center Nasional |
| **Akun Developer (Tech)** | `cpiet` / `alumnidarus@gmail.com` | `T0p1s@y@bund@r` | Superadmin Mabes & Pengembang Sistem |
| **Pimpinan Polda Sulbar** | `kabid_sulbar` / `kabid.sulbar@polri.go.id` | `SulbarHumas#2026` | Komando Kehumasan Wilayah Sulawesi Barat |

---

## 📚 Dokumentasi & Panduan Pengembang

Dokumentasi lengkap sistem SIMOD Enterprise terpusat pada direktori [`docs/`](./docs/README.md) dan panduan arsitektur khusus untuk AI Agents:

- **[🏠 PANDUAN LENGKAP SETUP DI LAPTOP / KOMPUTER RUMAH](./docs/PANDUAN_SETUP_KOMPUTER_RUMAH.md)**: Panduan langkah demi langkah menjalankan proyek di komputer rumah/laptop lain tanpa file `.sql` mentah, lengkap dengan panduan VirtualHost Laragon & troubleshooting.
- **[AGENTS.md](./AGENTS.md)**: Panduan instruksi, arsitektur 4 pilar, dual-theme engine, aturan kuota RSS 29 portal, dan protokol pengembangan untuk AI Coding Agents (Codex, Claude, Gemini, Antigravity).
- **[Katalog Lengkap Modul](./docs/09-katalog-lengkap-19-modul-simod-enterprise.md)**: Daftar seluruh modul operasional, hak akses, dan rute navigasi.
- **[Peta Situasi Kamtibmas Nusantara & Integritas KPI](./docs/16-peta-nusantara-dan-integritas-kpi-command-center.md)**: Peta Indonesia interaktif berbasis berita database (11 sentra), dual-scope map toggle, dan eliminasi KPI *gimmick*.
- **[Perbaikan Navigasi Tab, Kontras Keamanan & Otomasi Sinkron Topik Atensi](./docs/17-perbaikan-kontras-keamanan-dan-penarikan-otomatis-topik-atensi.md)**: Redesain tab 2 baris 5 kolom anti-truncate, pembersihan gambar stok Unsplash, default placeholder teks berita, dan otomasi sinkron Google News saat adopsi Topik Atensi.
- **[Optimalisasi UX Dossier & Arsitektur 4 Pilar Media Intelligence](./docs/18-optimalisasi-ux-dossier-dan-arsitektur-4-pilar-media-intelligence.md)**: Eliminasi redundansi tab kuno, layout 2-kolom Smart Dossier, baki collapsible rekomendasi tren AI, command bar 1-baris, dan asisten intelijen SADEWA AI Copilot.
- **[Optimalisasi Radar Medsos & Sintesis AI SADEWA](./docs/19-optimalisasi-radar-media-sosial-dan-sintesis-ai-sadewa.md)**: Multi-Tier Autonomous Ingestion Engine (zero-cost syndication), SADEWA Social AI Synthesis, restrukturisasi 4 pilar tab, dan sentralisasi token Apify di admin.
- **[Audit, Refactoring, AppSec & Portabilitas Multi-Domain](./docs/20-audit-refactoring-appsec-dan-pembersihan-kode.md)**: Pembersihan kode mati, kanonikalisasi rute anti-tampering, sistem notifikasi toast reaktif global, proteksi IDOR, dan panduan multi-hosting.
- **[Modul Media Intelligence & RSS](./docs/03-modul-media-intelligence-dan-rss.md)**: Panduan agregasi berita, executive briefing AI, dan naskah WhatsApp pimpinan.
- **[Modul Radar Media Sosial & Apify](./docs/11-modul-radar-media-sosial-apify.md)**: Panduan monitoring 𝕏, TikTok, Instagram, YouTube, Multi-API Token Pool, akun target, dan thumbnail video HD.
- **[Daftar 29 RSS Feed Resmi](./docs/10-daftar-rss-feed-resmi-sulbar.md)**: Inventaris lengkap portal media online Sulbar, endpoint syndication, dan fallback Google News RSS.
- **[Perintah CLI Artisan & Penjadwal](./docs/02-perintah-artisan-dan-cli.md)**: Panduan sinkronisasi otomatis, retensi data, dan CLI automation.

---

## 🔒 Lisensi & Hak Cipta
Hak cipta dilindungi undang-undang. Dikembangkan untuk kebutuhan operasional kehumasan kepolisian.
