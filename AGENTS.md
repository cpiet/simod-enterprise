# 🤖 AGENTS.md — Panduan Arsitektur & Instruksi Operasional untuk AI Coding Agents (Codex, Claude, Gemini, Antigravity)

Dokumen ini adalah **panduan rujukan resmi sistem** yang dirancang khusus untuk Agentic AI (OpenAI Codex, Claude 3.5 Sonnet, Google Gemini, DeepSeek, Antigravity, GitHub Copilot) saat membaca, menganalisis, merancang fitur, atau memodifikasi basis kode **SIMOD Enterprise**.

---

## 🏛️ 1. Ikhtisar Sistem & Core Tech Stack

- **Nama Platform**: SIMOD Enterprise (Sistem Monitoring dan Opini Publik Digital POLRI v2.0)
- **Tujuan**: Platform Command Center intelijen media siber, pemantauan opini publik, Early Warning System (EWS), agregasi 29 RSS multi-portal, orkestrasi rilis pers, dan manajemen operasional tugas kehumasan berstandar Divisi Humas Polri & Polda Jajaran.
- **Arsitektur Utama**:
  - **Backend**: Laravel 11 / 12 (PHP 8.2+) dengan arsitektur MVC & Service Repository.
  - **Frontend**: Vue 3 (Composition API dengan `<script setup>`), Inertia.js v2 (tanpa Blade view SPA tradisional).
  - **Styling**: Tailwind CSS v4 (`@tailwindcss/vite` tanpa `tailwind.config.js`). Konfigurasi token tema tersentralisasi di [`resources/css/app.css`](file:///d:/Foto/laragon/www/simod-enterprise/resources/css/app.css).
  - **Basis Data**: MySQL 8.x / MariaDB (produksi lokal Laragon) + SQLite in-memory (khusus automated testing).
  - **Icons**: `lucide-vue-next`.

---

## 🗺️ 2. Peta 4 Pilar Navigasi & Modul Terpadu

Sidebar navigasi di [`EnterpriseLayout.vue`](file:///d:/Foto/laragon/www/simod-enterprise/resources/js/Layouts/EnterpriseLayout.vue) distrukturkan secara tegas ke dalam 4 pilar hierarkis:

```
├── 1. KOMANDO & INTELIJEN
│   ├── Command Center               [/] -> Ringkasan Eksekutif, Peta Situasi & EWS
│   ├── Radar Media Online           [/media-intelligence] -> Radar 29 RSS, Executive Briefing, Naskah WA
│   └── Radar Media Sosial           [/social-intelligence] -> Pantauan 𝕏, TikTok, IG, YT, KOL & Apify
│
├── 2. MANAJEMEN ISU & TUGAS
│   ├── Laporan Polres & Isu         [/issues] -> Pemantauan daftar isu & eskalasi sentimen
│   ├── Input Laporan Baru           [/issues/create] -> Formulir laporan lapangan & bukti foto
│   ├── Antrean Verifikasi           [/verification-queue] -> Validasi fakta & klarifikasi hoaks
│   └── Disposisi Tugas              [/tasks] -> Surat perintah penugasan anggota & rekap hasil
│
├── 3. PUBLIKASI & DOKUMENTASI
│   ├── Publikasi Multi-Kanal        [/publications] -> Tribratanews, Instagram, TikTok, Facebook, X
│   ├── Galeri Media Aman            [/gallery] -> Arsip foto/video bukti terenkripsi
│   └── Laporan & Anev               [/reports] -> Rekapitulasi kinerja kehumasan & ekspor CSV/PDF
│
└── 4. ADMINISTRASI & SISTEM
    ├── Personalia & Petugas         [/personalia] -> Manajemen akun personel & Satker
    ├── Pengaturan Satwil            [/settings/polda] -> Konfigurasi Polda & Polres jajaran
    ├── Keamanan & Pemeliharaan      [/security] -> Pintu masuk 8 sub-modul pemeliharaan terpadu:
    │   ├── Keamanan Siber           [/security]
    │   ├── Hak Akses (RBAC)         [/roles]
    │   ├── Audit Log                [/audit-logs]
    │   ├── Backup Basis Data        [/database/backup]
    │   ├── Pembersihan Berita       [/maintenance/news-cleanup]
    │   ├── Otomasi Sinkron RSS      [/maintenance/rss-sync]
    │   ├── Mode Pemeliharaan        [/maintenance]
    │   └── Konfigurasi Mail (SMTP)  [/settings/mail]
    └── Profil Saya                  [/profile] -> Kredensial dinas & PIN Cepat
```

---

## 🌓 3. Dual-Theme Engine (Mode Gelap & Mode Terang)

Aplikasi memiliki mesin tema ganda bawaan yang terpasang pada tingkat root `<html>`:
- **Mode Gelap (Command Center Dark)**: Menggunakan kelas `.dark` pada `<html>`. Latar belakang `#070d1d`, kartu `#0b1426`, teks slate terang dengan aksen gold/amber `#f59e0b`.
- **Mode Terang (Executive Light)**: Menggunakan kelas `.light` pada `<html>`. Latar belakang `#f1f5f9`, kartu `#ffffff`, border `#e2e8f0`, teks kontras tinggi `#0f172a`.
- **Anti-Flash Script**: Dijalankan secara sinkron di `<head>` pada [`resources/views/app.blade.php`](file:///d:/Foto/laragon/www/simod-enterprise/resources/views/app.blade.php) membaca `localStorage.getItem('simod_theme')` sebelum Vue ter-mount.
- **PENTING UNTUK AGENT**: Jangan membuat class styling baru yang meng-hardcode warna teks hitam pekat pada dark mode. Manfaatkan token Tailwind yang sudah dipetakan di [`resources/css/app.css`](file:///d:/Foto/laragon/www/simod-enterprise/resources/css/app.css).

---

## 📡 4. Mesin Agregasi Media Online (29 RSS Feeds) & Auto-Sync

### 4.1. Daftar 29 Portal Resmi Sulbar
Seluruh 29 portal media dikonfigurasi melalui seeder [`SulbarRssFeedsSeeder.php`](file:///d:/Foto/laragon/www/simod-enterprise/database/seeders/SulbarRssFeedsSeeder.php):
- **Regional Sulselbar (20)**: Detik Sulsel, Tribun Sulbar, ANTARA Makassar (Terkini, Top News, Daerah, Lintas Daerah), Mesakada, Mekora, Globalsulbar, Radar Sulbar, Sulbar Express, Mamuju Pos, Katinting, Kabar Sulbar, Suwarta, Sulbar Terkini, Malaqbi, Rakyat Sulbar, Bugis Pos, Bacasaja.id.
- **Nasional & Umum (4)**: Antara News Terkini, CNN Indonesia Nasional, Tribunnews (Nasional), ANTARA Makassar (Politik).
- **Hukum & Kriminal (3)**: Detikcom Hukum & Kriminal, ANTARA Makassar (Hukum), Polisiku.co.id.
- **Google News RSS (2)**: iNews Mamuju (Keyword Search), Google News - iNews Mamuju (Site Search).

### 4.2. Aturan Kritis Penarikan & Kuota Adil (*Fair Quota*)
> [!IMPORTANT]
> Media nasional (Detik, CNN, Antara) memproduksi ratusan berita harian. **DILARANG** melakukan `array_slice()` global setelah pengurutan timestamp!
- **Wajib menggunakan pengelompokan per sumber (*per-source grouping*)**: Setiap feed diberi kuota simpan adil (default 25–30 berita per feed). Hal ini menjamin portal daerah (Katinting, Malaqbi, Antara Hukum/Politik) tidak terdesak menjadi 0 berita.
- **Bypass Cloudflare & Permalinks Rusak**: Portal dengan proteksi Cloudflare (seperti `Bacasaja.id`) atau struktur tautan bermasalah (`Malaqbi.com`) disindikasikan secara aman melalui Google News RSS (`site:domain.com`).

### 4.3. Fitur Keamanan & Kestabilan di Balik Layar
- **Mutex Atomic Lock**: `Cache::lock('rss_sync_engine_lock', 600)`. Jika proses sinkronisasi sedang berlangsung, cron berikutnya akan dilewati (*skipped_locked*) untuk mencegah *server overload*.
- **Auto-Capping Berimbang**: Jika kapasitas berita non-bookmark melebihi ambang batas (`max_retained_articles_per_polda`), pemangkasan dilakukan berimbang per-sumber (`autoCapArticles` di `RssSyncService`), bukan menghapus portal daerah yang terbit berkala.
- **Bookmark Permanen**: Kolom `is_bookmarked` pada tabel `media_articles` bersifat kebal dari pemangkasan otomatis (*never pruned*).

### 4.4. Radar Media Sosial & Social Listening (𝕏, TikTok, Instagram, YouTube)
- **Tabel Basis Data**: `social_posts` (data postingan, metrik views/likes/shares/comments, virality_score 0-100, is_kol, sentiment) dan `social_settings` (konfigurasi Apify token, kata kunci pantau, frekuensi sinkronisasi per Polda).
- **Tab Navigasi**: Tab 01 (Radar Multi-Platform), Tab 02 (Tokoh Kritis & KOL Tracker), Tab 03 (Tren Tagar & Isu Viral), Tab 04 (Integrasi Apify & Pengaturan).
- **Direct One-Click Escalation**: Tombol `🚨 Eskalasi Jadi Isu` pada setiap kartu postingan medsos otomatis mengarahkan ke formulir `/issues/create` dengan pra-isi judul, kategori (`📱 Isu Medsos & Opini Publik`), URL sumber, dan ringkasan.

### 4.5. Radar Media Online Divhumas Polri (22 Feed Nasional Terverifikasi 100% Gambar)
- **Tenant Scope**: Kode `DIVHUMAS` (ID `10`) atau query parameter `?polda=ALL`.
- **Daftar 22 Feed Resmi Aktif**:
  1. ANTARA News Terkini (`https://www.antaranews.com/rss/terkini.xml`)
  2. ANTARA Top News (`https://www.antaranews.com/rss/top-news.xml`)
  3. ANTARA Politik & Pemerintahan (`https://www.antaranews.com/rss/politik.xml`)
  4. ANTARA Hukum & Peradilan (`https://www.antaranews.com/rss/hukum.xml`)
  5. ANTARA Metro Kriminalitas (`https://www.antaranews.com/rss/metro-kriminalitas.xml`)
  6. ANTARA Metro Ibukota (`https://www.antaranews.com/rss/metro.xml`)
  7. ANTARA Ekonomi & Bisnis (`https://www.antaranews.com/rss/ekonomi.xml`)
  8. Detikcom Berita Nasional (`https://news.detik.com/berita/rss`)
  9. Detikcom Finance (`https://finance.detik.com/rss`)
  10. Detikcom Inet & Siber (`https://inet.detik.com/rss`)
  11. CNN Indonesia Nasional (`https://www.cnnindonesia.com/nasional/rss`)
  12. CNN Indonesia Ekonomi (`https://www.cnnindonesia.com/ekonomi/rss`)
  13. CNN Indonesia Teknologi & Siber (`https://www.cnnindonesia.com/teknologi/rss`)
  14. CNBC Indonesia News (`https://www.cnbcindonesia.com/news/rss`)
  15. CNBC Indonesia Market & Finansial (`https://www.cnbcindonesia.com/market/rss`)
  16. Republika Terkini (`https://www.republika.co.id/rss`)
  17. Liputan6 News & Peristiwa (`https://feed.liputan6.com/rss/news`)
  18. SINDOnews Nasional (`https://nasional.sindonews.com/rss`)
  19. SINDOnews Daerah & Satwil (`https://daerah.sindonews.com/rss`)
  20. JPNN Berita Terkini (`https://www.jpnn.com/rss`)
  21. Kumparan News (`https://lapi.kumparan.com/v2.0/rss/`)
  22. BBC News Indonesia (`https://feeds.bbci.co.uk/indonesia/rss.xml`)
- **Verifikasi Kualitas Gambar**: 100% artikel memiliki thumbnail/cover image resolusi tinggi dari CDN resmi media yang diekstrak melalui parser `RssFeedService` (`enclosure`, `media:content`, `media:thumbnail`, `img src`).
- **Sinkronisasi CLI**: `php artisan rss:sync --polda=ALL` otomatis memetakan ke tenant Divhumas Polri.

---

## 🤖 5. Alur Analisis Intelijen AI (Otomatis Gemini Pro & Manual Prompt)

### 5.1. Analisis Otomatis 1-Klik (Google Gemini Pro Engine - Default)
1. **Tombol Utama `⚡ Analisis Otomatis (Gemini Pro)`**: Terletak pada top-command bar di Radar Media Online. Memanggil API backend `POST /media-intelligence/analyze-gemini`.
2. **Mesin Cerdas `GeminiService`**:
   - Menarik 30 berita terkini sesuai tenant aktif (Divhumas Polri / Polda).
   - Menginjeksikan konteks berita ke dalam formula prompt Senior Media Analyst Mabes Polri.
   - Memanggil endpoint Google Gemini (`gemini-2.5-pro` dengan fallback bertingkat ke `gemini-1.5-pro` dan `gemini-2.5-flash`).
   - Menerima keluaran terstruktur JSON murni (`responseMimeType: application/json`).
3. **Penyimpanan Permanen Basis Data**:
   - Menyimpan hasil analisis ke tabel `media_intelligence_reports` dan memperbarui stempel `ai_settings`.
   - Mengisi seketika seluruh tab: **Tab 02** (Executive Briefing), **Tab 03** (Polarisasi Sentimen), **Tab 05** (EWS), **Tab 06** (Counter Narasi & Rekomendasi), dan **Tab 07** (Naskah WhatsApp Siap Kirim).
4. **Modal Pengaturan Gemini AI (`showGeminiSettingsModal`)**:
   - Memungkinkan personel memasukkan API Key dari Google AI Studio (`aistudio.google.com`).
   - Menyediakan fitur *"Uji Koneksi API"* realtime beserta indikator latensi milidetik.
   - Pilihan model (`gemini-2.5-pro`, `gemini-1.5-pro`, `gemini-2.5-flash`, `gemini-1.5-flash`).

### 5.2. Opsi Manual (Salin Formula & Tempel Ringkasan AI)
- **Tetap Dipertahankan Berdampingan**: Tombol *"Salin Prompt AI"*, *"Lihat Prompt"*, dan *"Tempel Ringkasan AI"* tetap aktif dan dapat digunakan kapan saja jika pengguna ingin menganalisis menggunakan antarmuka eksternal ChatGPT Plus (GPT-4o), Claude 3.5 Sonnet, atau akun web Gemini secara manual.

---

## ⌨️ 6. Perintah CLI & Otomasi Scheduler

```bash
# Sinkronisasi berita RSS secara aman (didukung Mutex Lock)
php artisan rss:sync --polda=SULBAR

# Sinkronisasi seluruh 34 Polda (Konsolidasi Nasional)
php artisan rss:sync

# Pemangkasan retensi berita lama (menjaga bookmark)
php artisan media:prune --days=90 --polda=SULBAR

# Pencadangan basis data snapshot terkompresi & pembersihan retensi berkas
php artisan db:backup --force
php artisan db:backup --type=scheduled

# Menjalankan seluruh test suite otomatis (26 tests, 128 assertions)
php artisan test

# Kompilasi aset Vite frontend
npm run build

# Menjalankan tunnel Ngrok publik untuk live demo & presentasi pimpinan
ngrok http --domain=humped-obvious-blatantly.ngrok-free.dev --host-header=simod-enterprise.test 80
```

---

## ⚠️ 7. Protokol Pengembangan untuk AI Agents

Saat Anda (Agent) diminta memodifikasi kode ini:
1. **Verifikasi Database Testing**: File `phpunit.xml` telah dikonfigurasi menggunakan SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Jangan mengganti konfigurasi ini agar database MySQL pengembangan lokal tidak ter-wipe saat testing.
2. **Jalankan Testing Sebelum Selesai**: Selalu jalankan `php artisan test` dan `npm run build` sebelum menyelesaikan tugas.
3. **Multi-Tenant Scoping**: Setiap query data operasional (`MediaArticle`, `Issue`, `Task`, `EarlyWarning`) **wajib** menyertakan filter `polda_id`.
4. **Preserve Docstrings**: Pertahankan semua dokumentasi, komentar PHPDoc, dan struktur kode yang ada.
5. **Otomasi Live Demo Tunnel (Ngrok)**:
   - Jika pengguna meminta *"jalankan ngrok"*, *"start ngrok"*, atau *"buka tunnel demo"*:
     Jalankan perintah berikut sebagai background process daemon:
     `ngrok http --domain=humped-obvious-blatantly.ngrok-free.dev --host-header=simod-enterprise.test 80`
   - Tunnel ini mengarahkan domain publik tetap `https://humped-obvious-blatantly.ngrok-free.dev` ke vhost Laragon Apache `simod-enterprise.test:80`.
   - Konfigurasi `trustProxies` dan `URL::forceScheme('https')` di Laravel menjamin seluruh CSS/JS Vite dan form Inertia bekerja secara mulus di internet publik tanpa mixed-content block.

