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
    ├── Keamanan & Pemeliharaan      [/security] -> Pintu masuk 9 sub-modul pemeliharaan terpadu:
    │   ├── Keamanan Siber           [/security]
    │   ├── Hak Akses (RBAC)         [/roles]
    │   ├── Audit Log                [/audit-logs]
    │   ├── Backup Basis Data        [/database/backup]
    │   ├── Pembersihan Berita       [/maintenance/news-cleanup]
    │   ├── Otomasi Sinkron RSS      [/maintenance/rss-sync]
    │   ├── Mode Pemeliharaan        [/maintenance]
    │   ├── Konfigurasi Mail (SMTP)  [/settings/mail]
    │   └── Mesin Intelijen AI (LLM) [/settings/ai]
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

### 4.4. Radar Media Sosial & Social Listening (𝕏, TikTok, Instagram, YouTube, Facebook)
- **Tabel Basis Data**: `social_posts` (data postingan, metrik views/likes/shares/comments, virality_score 0-100, is_kol, sentiment, `case_id`), `social_investigation_cases` (berkas kasus atensi pimpinan, keywords, platforms, posts_count, sentiment_breakdown, status active/archived), dan `social_settings` (konfigurasi Apify token, kata kunci pantau, frekuensi sinkronisasi per Polda).
- **Multi-Tier Autonomous Ingestion Engine**:
  - *Tier 1 (Zero-Cost Syndication)*: Penarikan otomatis live dari internet menggunakan sindikasi Google News Social RSS (`site:x.com`, `site:tiktok.com`, `site:instagram.com`, dll.) tanpa kuota API pihak ketiga via `SocialSyndicationService`.
  - *Tier 2 (Apify Cloud Fallback)*: Beroperasi otomatis jika kuota dan token Apify tersedia.
  - *Tier 3 (Smart Synthetic Fallback)*: Pola interaksi realistis warganet daerah untuk menjamin ketersediaan sistem 24/7 saat offline.
- **SADEWA Social AI Synthesis (`POST /social-intelligence/analyze-ai`)**:
  - Memproses percakapan warganet menggunakan `GeminiService::analyzeSocialPosts()`.
  - Menghasilkan: Executive Brief Situasi Medsos, Isu Kritis Prioritas Pimpinan (*Top Concern*), Komposisi & Stabilitas Sentimen, Deteksi Polarisasi Buzzer vs Netizen Organik, Akun Kritis Terdeteksi, Rekomendasi Cooling System Humas, dan Naskah WhatsApp Komando Pimpinan (siap salin 1-klik).
- **Tab Navigasi Utama (4 Pilar Terpadu)**:
  - *Tab 01 (Radar Percakapan)*: Smart Dossier Topik Atensi, feed multi-platform, Spike Timeline 7 Hari, Semantic Word Cloud, dan tombol `🚨 Eskalasi Jadi Isu`.
  - *Tab 02 (Executive Briefing & Naskah WA)*: Command bar sintesis 1-klik SADEWA AI, visualisasi polarisasi buzzer, matriks cooling system, dan naskah dinas WA pimpinan.
  - *Tab 03 (Radar KOL & Akun Kritis)*: Direktori tokoh berpengaruh, pengamat hukum, LSM/LBH, dan riwayat postingan.
  - *Tab 04 (Tren Tagar & Kerawanan Siber)*: Dual-mode lens (Lensa Kamtibmas Polri & Lensa Tren Publik Medsos Indonesia WOEID 23424846).
- **Sentralisasi Kredensial Apify**:
  - Pengaturan API Token Apify dan pool failover telah dipindahkan dari operator radar ke panel Administrator: **Keamanan & Pemeliharaan &rarr; Mesin Intelijen AI (`/settings/ai`) &rarr; Tab Mesin Scraper Patroli Siber (Apify Cloud)**.
- **Direct One-Click Escalation**: Tombol `🚨 Eskalasi Jadi Isu` pada setiap kartu postingan medsos dan kartu tren publik otomatis mengarahkan ke formulir `/issues/create` dengan pra-isi judul, kategori (`📱 Isu Medsos & Opini Publik`), URL sumber, tingkat prioritas, dan ringkasan.

### 4.5. Radar Media Online Divhumas Polri (33 Feed Nasional Terverifikasi & Sindikasi Google News)
- **Tenant Scope**: Kode `DIVHUMAS` (ID `10`) atau query parameter `?polda=ALL`.
- **Daftar 33 Feed Resmi Aktif**:
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
  23. Tempo.co Utama (`https://rss.tempo.co/`)
  24. Tempo.co Nasional (`https://rss.tempo.co/nasional`)
  25. CNN Indonesia Utama (`https://www.cnnindonesia.com/rss`)
  26. CNBC Indonesia Terkini (`https://www.cnbcindonesia.com/rss`)
  27. Google News - Berita Utama (`https://news.google.com/rss?hl=id&gl=ID&ceid=ID:id`)
  28. Google News - Bisnis & Ekonomi (`https://news.google.com/rss/headlines/section/topic/BUSINESS?hl=id&gl=ID&ceid=ID:id`)
  29. Google News - Teknologi & Siber (`https://news.google.com/rss/headlines/section/topic/TECHNOLOGY?hl=id&gl=ID&ceid=ID:id`)
  30. Google News - Hiburan & Budaya (`https://news.google.com/rss/headlines/section/topic/ENTERTAINMENT?hl=id&gl=ID&ceid=ID:id`)
  31. Google News - Olahraga (`https://news.google.com/rss/headlines/section/topic/SPORTS?hl=id&gl=ID&ceid=ID:id`)
  32. Google News - Sains & Lingkungan (`https://news.google.com/rss/headlines/section/topic/SCIENCE?hl=id&gl=ID&ceid=ID:id`)
  33. Google News - Kesehatan (`https://news.google.com/rss/headlines/section/topic/HEALTH?hl=id&gl=ID&ceid=ID:id`)
- **Verifikasi Kualitas Feed**: Portal media arus utama membawa gambar dan konten lengkap, sementara Google News RSS menyediakan agregasi sebaran topik publik yang luas.
- **Sinkronisasi CLI**: `php artisan rss:sync --polda=ALL` otomatis memetakan ke tenant Divhumas Polri.

### 4.6. Smart Dossier Topik Atensi, Caching AI & Mesin Ingesti Live Google News
- **Smart Dossier & Caching Analisis AI**:
  - Kolom basis data `media_topics`: `ai_analysis` (JSON terstruktur hasil Gemini AI), `ai_analyzed_at` (timestamp analisis terakhir), dan `ai_articles_count` (jumlah total artikel saat analisis dijalankan).
  - **Efisiensi Token & Akses Instan**: Hasil analisis tersimpan permanen. Pengguna dapat membuka kembali modal analisis (`👁️ Lihat Hasil Analisis AI`) kapan saja tanpa memanggil API Gemini ulang.
  - **Detektor Kebaruan Dinamis (*Freshness Detector*)**: Membandingkan jumlah artikel terkini yang cocok dengan kata kunci topik terhadap `ai_articles_count`. Jika ada berita baru masuk, badge amber `⚠️ Ada +X Berita Baru Sejak Analisis Terakhir` muncul dengan tombol `🔄 Perbarui Analisis`. Jika data sinkron, tampil badge hijau `✓ Data Terkini (Up-to-Date)`.
- **Mesin Ingesti Live On-Demand (`GoogleNewsCrawlerService`)**:
  - **Endpoint Backend**: `POST /media-intelligence/topics/{id}/crawl-live` memanggil `GoogleNewsCrawlerService::crawlForTopic()`.
  - **Penarikan Cepat Lintas Internet**: Mengunduh Google News RSS (`https://news.google.com/rss/search?q=...`) untuk topik baru atau saat pimpinan membutuhkan cakupan sebaran nasional yang lebih luas.
  - **Ekstraksi & Penyimpanan Permanen**: Mengekstrak nama sumber asli dari tag `<source>` (contoh: *Tribratanews Polda Sumsel*, *Antara News*), mengklasifikasikan kategori kepolisian (*Harkamtibmas*, *Gakkum*, dll.), mendeteksi sentimen, dan menyimpannya secara permanen ke tabel `media_articles` terhubung ke topik.
  - **Pengelompokan Portal**: Berita hasil crawling internet otomatis dikelompokkan dalam filter portal `🌐 SEBARAN INTERNET (GOOGLE NEWS)`.
  - **Standar Visual Card Grid Simetris & Default Placeholder Resmi**: Grid kartu berita dikunci pada tinggi seragam `h-[435px]` dengan batas 2 baris untuk judul (`h-10 md:h-11`) dan ringkasan (`h-9`). Jika artikel berita tidak membawa foto asli atau gambar gagal dimuat, sistem menampilkan placeholder default resmi yang elegan `Tidak Ada Gambar Ditampilkan` disertai keterangan dokumentasi berita teks dan nama portal, **tanpa memaksakan gambar stok berulang** yang tidak relevan dengan isu.
  - **Ergonomi Smart Dossier 4-Tier**: Kotak detail topik atensi aktif ditata ulang dalam 4 tingkat hierarkis simetris:
    1. *Header Bar*: Judul kasus, tingkat urgensi (`🚨 SANGAT MENDESAK` / `⭐ PRIORITAS TINGGI`), badge hitungan berita terkait, dan tombol utilitas (`Edit Kata Kunci`, `Hapus Topik`, `Tutup`).
    2. *Directive & Keywords Cloud*: Kutipan arahan pimpinan dan awan tag kata kunci pantau (#tags) yang membentang horizontal secara penuh (*full-width flex-wrap*).
    3. *Command Toolbar*: Pengelompokan seimbang tombol AI (`👁️ Lihat Hasil Analisis AI`, `🔄 Perbarui Analisis +X Baru`) dan Ingesti Media (`🌐 Tarik Berita Internet`, `📱 Salin Naskah WA`).
    4. *AI Summary & Polarisasi Sentimen*: Ringkasan eksekutif intelijen, deteksi akurat persentase sentimen (`positif`, `netral`, `negatif`), dan *visual multi-color proportional progress bar*.
  - **Adopsi Sugesti Tren & Sinkronisasi Berita Instan**: Tombol 1-klik `✓ Pantau` pada baris Sugesti AI seketika mengeksekusi penarikan berita langsung dari Google News dengan sanitasi kata kunci otomatis (membersihkan tanda `#` dan tanda baca), mengaitkan artikel portal lokal yang relevan di basis data, dan langsung menyisipkan seluruh berita terkait di baris teratas feed artikel tanpa jeda reload.

---

## 🤖 5. Alur Analisis Intelijen AI (Pipeline Seamless 1-Klik)

### 5.1. Pipeline 2-in-1: Sinkron Berita & Analisis Otomatis
1. **Tombol Utama `⚡ Sinkron & Analisis Otomatis`**: Terletak pada top-command bar di Radar Media Online. Memanggil API backend `POST /media-intelligence/analyze-gemini?sync=1`.
2. **Eksekusi 2-in-1 Simultan**:
   - **Tahap 1 (Penarikan Berita Terkini)**: Menarik feed RSS dari 22/29 portal media secara paralel (`curl_multi`) dan menyimpan artikel terbaru secara adil (*fair quota* 25 berita/portal).
   - **Tahap 2 (Analisis Intelijen AI)**: 30 artikel paling segar seketika diumpankan ke mesin AI (`gemini-2.5-pro` dengan fallback otomatis ke `gemini-1.5-pro`).
   - Menerima keluaran JSON terstruktur dan menyimpannya permanen ke tabel `media_intelligence_reports`.
3. **Penyajian Cepat Tanpa Klik Ganda (Arsitektur 4 Pilar Terpadu)**:
   - Mengisi seketika 4 pilar analisis komprehensif tanpa redundansi: **Tab 01** (Radar Berita Online & Smart Dossier), **Tab 02** (Executive Briefing & Sentimen Terpadu + Format WA Resmi), **Tab 03** (Early Warning System / EWS), dan **Tab 04** (Strategi Humas, Kontra-Opini & Orkestrasi Satwil). Menghilangkan duplikasi kuno naskah WA dan pemisahan sentimen yang terfragmentasi.
4. **Sentralisasi Admin & Kebijakan Hak Akses (/roles)**:
   - Pengaturan sumber RSS XML dipusatkan di menu Admin **Keamanan & Pemeliharaan -> Otomasi Sinkron RSS** (`/maintenance/rss-sync`). Dilengkapi tab khusus **Daftar & Manajemen Sumber Feed RSS** (22 portal nasional / 29 portal daerah) dengan fitur penambahan feed baru, uji validasi XML realtime (latensi & artikel terdeteksi), toggle on/off, dan hapus portal.
   - Tombol manual kuno (*Salin Prompt*, *Lihat Prompt*, *Tempel Ringkasan AI*) serta tombol *Kelola Sumber RSS* telah dibersihkan dari antarmuka operasional radar.
   - Hak akses operasional `media_intelligence_ai` di `/roles` terdefinisi sebagai *Sinkronisasi Berita & Analisis Otomatis AI*.
   - **Sentralisasi Kredensial Apify**: Pengaturan Personal API Token Apify dipindahkan dari tampilan operator `/social-intelligence` ke panel admin **Keamanan & Pemeliharaan -> Mesin Intelijen AI** (`/settings/ai`) di bawah tab *Mesin Scraper Patroli Siber (Apify Cloud)* dengan dukungan Multi-Token Failover Pool dan proteksi dari akses langsung operator.
   - **Studio Editor Template Prompt AI Dinamis**: Pengaturan naskah prompt intelijen AI dipusatkan di panel admin `/settings/ai` pada tab **Template Prompt Intelijen (Editor Dinamis)**. Administrator dapat meninjau, mengedit naskah prompt secara mandiri tanpa menyentuh kode PHP, menyisipkan variabel dinamis (`[Tempelkan teks berita...]`, `{COMMAND_NAME}`, `{LEADER_TITLE}`, `{TIMEZONE}`, `{POLRES_LIST}`), mereset kembali ke template standar resmi Polri dalam 1-klik (`POST /settings/ai/reset-prompt`), dan menerapkan template kustom secara nasional ke 34 Polda Jajaran. Sistem otomatis memprioritaskan template kustom di tabel `ai_settings` (`custom_prompt_template`) di atas default bawaan sistem.

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
6. **Keamanan URL Tenant (Anti-Tampering)**:
   - Dilarang membuat link atau tombol yang mengoper parameter query plaintext `?polda=...`.
   - Selalu gunakan endpoint POST terotentikasi `POST /tenant/switch` dengan payload `polda_id` untuk isolasi sesi yang aman dan kebal manipulasi URL.
7. **Integritas Personalia & Proteksi Akun Master**:
   - Akun master sistem (`id: 1`, `kadivhumas`, `superadmin`, `cpiet`) berstatus kebal dari penghapusan (`deleteUser`) dan deaktivasi (`toggleUserStatus`).
   - Sesi pengguna aktif dilarang menghapus akunnya sendiri (*anti-self deletion*) atau mencabut status aktifnya sendiri (*anti-self lockout*).
   - Otoritas personel terhubung erat ke matriks 17 modul pada 4 pilar di `/roles`.
8. **Smart Dossier / Topik Atensi Pimpinan, Ingesti Live & Sugesti Tren Cerdas**:
   - Fitur atensi khusus kasus sensitif pimpinan di `/media-intelligence` untuk pemantauan instan dan konteks laporan khusus pada mesin Gemini AI.
   - Dilengkapi sistem caching analisis AI permanen (`ai_analysis`, `ai_analyzed_at`, `ai_articles_count`) untuk menghemat kuota token AI dan akses modal tanpa jeda.
   - Didukung mesin penarikan berita langsung `GoogleNewsCrawlerService` untuk menangkap liputan internet secara real-time dan disimpan permanen pada basis data.
   - **Sugesti Tren Potensial AI (Google News)**: Mesin AI mendeteksi dan mengembalikan array `suggested_topics` (title, priority, velocity, keywords, rationale) dari pemberitaan terkini. Ditampilkan pada baris interaktif tepat di dalam kotak Smart Dossier dengan tombol 1-klik `✓ Pantau` (`POST /media-intelligence/topics/adopt-suggestion`) yang otomatis mengadopsinya menjadi Topik Atensi, menarik sebaran berita Google News instan, serta tombol `✕` (`POST /media-intelligence/topics/dismiss-suggestion`) untuk mengabaikan.
9. **Arsitektur 4 Pilar Terpadu, SADEWA AI Copilot & Desain Anti-Gimmick Media Intelligence**:
   - Tab radar media online dikonsolidasikan secara ketat ke dalam **4 Pilar**:
     1. `01. Berita Online (Live RSS)`: Feed real-time, filter Zona Merah, dan Smart Dossier Atensi Pimpinan.
     2. `02. Executive Briefing & Sentimen`: Mengintegrasikan briefing eksekutif, barometer sentimen (Nasional & Wilayah), top concern, matriks respons taktis bertahap (0-2h, 2-6h, 24h), isu strategis, dan terminal format naskah WhatsApp pimpinan siap kirim (menghilangkan duplikasi Tab 07 lama).
     3. `03. Early Warning System (EWS)`: Deteksi kerawanan kritis (Level Merah/Kuning), unmitigated risk 6-24 jam, dan protokol darurat 3-tahap.
     4. `04. Strategi Humas & Aksi`: Ide konten kreatif medsos (Tribratanews, IG, TikTok, X), rekomendasi komunikasi publik juru bicara, dan orkestrasi 34 Polda / Polres jajaran.
   - **SADEWA AI Copilot** (*Sistem Analisis Digital & Evaluasi Wacana Aktual*): Asisten AI percakapan cerdas bertenaga LLM yang mampu menganalisis arsip pemberitaan 24 jam secara real-time, menjawab pertanyaan pimpinan, dan terhubung melalui event dispatcher global `open-sadewa-copilot`.
   - **Tata Letak Ergonomis (2-Kolom Dossier & Collapsible AI Tray)**: Dossier detail kasus atensi menggunakan layout 2-kolom responsif (menghemat ruang vertikal 45%), baki rekomendasi tren Google News AI dapat dilipat (*collapsible*), dan tombol status cepat (Semua Berita, Zona Merah, Tersimpan) terintegrasi pada Command Bar 1-baris.


10. **Presisi Command Intelligence Hub di Command Center Utama (`/`)**:
    - **Anti-Gimmick & 100% Dinamis**: Menghapus kartu ringkasan statis pimpinan dan menggantinya dengan **Presisi Command Intelligence Hub** 3-tab terpadu tepat di atas peta situasi nusantara pada [`Dashboard.vue`](file:///d:/Foto/laragon/www/simod-enterprise/resources/js/Pages/Dashboard.vue):
      1. `📰 Media Online`: Membaca langsung laporan intelijen AI terkini (`latestMediaReport` dari `media_intelligence_reports`), menyajikan *Top Concern* pimpinan, *Executive Briefing 24 Jam*, *Arahan Inti Komando (Core Decision)*, proporsi sentimen media online (positif/netral/negatif), dan daftar isu strategis terdeteksi dengan tombol 1-klik menuju radar media.
      2. `📱 Media Sosial`: Membaca laporan intelijen AI medsos (`latestSocialReport` dari `social_intelligence_reports`), menyajikan *Top Concern Percakapan Warganet*, *Executive Social Briefing*, analisis polarisasi *Buzzer vs Netizen Organik* beserta tingkat risikonya, komposisi sentimen warganet, dan daftar *Akun Kritis / KOL* berisiko tinggi.
      3. `🛡️ Respon & Cooling System`: Matriks taktis penyejuk suasana Polri berjenjang (0-2 Jam, 2-6 Jam, 24 Jam) lengkap dengan Satker Pelaksana (PIC), Kanal Distribusi publikasi, dan aksi kehumasan terarah.
    - **Sinkronisasi Dropdown & Payload Berita**: Kuota payload berita radar ditingkatkan ke `->limit(3000)` dan daftar kuota portal `$portalsList` dihitung langsung dari array `$articles` yang dikirim ke frontend, menjamin sinkronisasi 100% angka dropdown dengan jumlah kartu artikel yang ditampilkan (mencegah isu *Menampilkan 0 dari 0*).

11. **AppSec Hardening, Rute Kanonikal Anti-Tampering, & Global Toast Feedback Engine**:
    - **100% Rute Kanonikal (Zero `?polda=...` URL Leaks)**: Seluruh tautan navigasi internal (`/issues`, `/issues/create`, `/issues/{id}`, `/reports/export/csv`, dll.) dibersihkan dari parameter query plaintext. Isolasi tenant multi-satwil sepenuhnya dikendalikan oleh session server-side dan endpoint otentikasi `POST /tenant/switch`.
    - **Proteksi IDOR & Parameter Tampering**: Seluruh aksi mutasi data (`POST /issues`, `POST /tasks`, `POST /publications`, `POST /gallery`) secara ketat menegakkan `$validated['polda_id'] = $request->user()->polda_id` bagi personel wilayah, mencegah pemalsuan `polda_id` via payload request.
    - **Global Floating Toast Feedback**: Komponen floating toast reaktif terpasang global di [`EnterpriseLayout.vue`](file:///d:/Foto/laragon/www/simod-enterprise/resources/js/Layouts/EnterpriseLayout.vue) memantau `page.props.flash` backend dengan auto-dismiss 4.5 detik dan event listener `window.addEventListener('simod-toast')`.
    - **Session Hardening & Logout**: Aksi logout menghapus cookie sesi, recaller cookie, menginvalider session token, dan disokong header HTTP `PreventBackHistory` (`no-cache, no-store, max-age=0, must-revalidate`).
