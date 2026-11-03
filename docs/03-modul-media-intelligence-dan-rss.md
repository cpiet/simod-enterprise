# 📰 03. Modul Media Intelligence & RSS Feeds

## 1. Arsitektur Agregator Paralel (`RssFeedService`) & Auto-Sync
Modul ini bertugas memantau pemberitaan media massa daring secara real-time untuk mendeteksi narasi yang berkembang di wilayah hukum Satwil.

- **Lokasi Service**:
  - [`app/Services/RssFeedService.php`](file:///d:/Foto/laragon/www/simod-enterprise/app/Services/RssFeedService.php) — Parser XML/Atom paralel via `curl_multi`.
  - [`app/Services/RssSyncService.php`](file:///d:/Foto/laragon/www/simod-enterprise/app/Services/RssSyncService.php) — Orkestrasi jadwal otomatis, Mutex Atomic Lock, kuota adil antar-sumber (*fair per-feed quota*), dan Auto-Capping Multi-Polda.
- **Mekanisme**: Memanfaatkan **`curl_multi`** sehingga seluruh 29 portal berita ditarik secara bersamaan (*non-blocking parallel request*). Waktu agregasi rata-rata hanya membutuhkan 4 hingga 8 detik untuk ratusan berita.
- **Strategi Caching**: Hasil parse XML/Atom disimpan pada Cache Laravel (`Cache::put`) dengan TTL default 300 detik (5 menit). Penarikan berikutnya berlangsung instan (< 50ms).

---

## 2. Daftar 29 Sumber Feed Resmi Terorganisir (Sulselbar & Nasional)

### 2.1 Regional Sulselbar (Sulawesi Selatan & Barat) — 20 Portal
| No | Nama Portal | URL Feed XML | Kategori | Catatan Teknis |
|:---|:---|:---|:---|:---|
| 1 | **Detik Sulsel** | `https://www.detik.com/sulsel/rss` | Regional Sulselbar | Feed aktif |
| 2 | **Tribun Sulbar** | `https://sulbar.tribunnews.com/rss` | Regional Sulselbar | Feed aktif |
| 3 | **ANTARA Makassar (Terkini)** | `https://makassar.antaranews.com/rss/terkini.xml` | Regional Sulselbar | Feed aktif |
| 4 | **ANTARA Makassar (Top News)** | `https://makassar.antaranews.com/rss/top-news.xml` | Regional Sulselbar | Feed aktif |
| 5 | **ANTARA Makassar (Daerah)** | `https://makassar.antaranews.com/rss/daerah.xml` | Regional Sulselbar | Feed aktif |
| 6 | **ANTARA Makassar (Lintas Daerah)** | `https://makassar.antaranews.com/rss/lintas-daerah.xml` | Regional Sulselbar | Feed aktif |
| 7 | **Mesakada.com** | `https://mesakada.com/feed/` | Regional Sulselbar | Feed aktif |
| 8 | **Mekora.id** | `https://mekora.id/feed/` | Regional Sulselbar | Feed aktif |
| 9 | **Globalsulbar** | `https://globalsulbar.com/feed/` | Regional Sulselbar | Feed aktif |
| 10 | **Radar Sulbar** | `https://radarsulbar.fajar.co.id/feed/` | Regional Sulselbar | Feed aktif |
| 11 | **Sulbar Express** | `https://sulbarexpress.fajar.co.id/feed/` | Regional Sulselbar | Feed aktif |
| 12 | **Mamuju Pos** | `https://mamujupos.com/feed/` | Regional Sulselbar | Feed aktif |
| 13 | **Katinting** | `https://katinting.com/feed/` | Regional Sulselbar | Feed aktif |
| 14 | **Kabar Sulbar** | `https://kabarsulbar.com/feed/` | Regional Sulselbar | Feed aktif |
| 15 | **Suwarta** | `https://suwarta.id/feed/` | Regional Sulselbar | Feed aktif |
| 16 | **Sulbar Terkini** | `https://sulbarterkini.com/feed/` | Regional Sulselbar | Feed aktif |
| 17 | **Malaqbi** | `https://news.google.com/rss/search?q=site:malaqbi.com&hl=id&gl=ID&ceid=ID:id` | Regional Sulselbar | Disindikasikan via Google News RSS |
| 18 | **Rakyat Sulbar** | `https://www.rakyatsulbar.com/feed/` | Regional Sulselbar | Feed aktif |
| 19 | **Bugis Pos** | `https://bugispos.com/feed/` | Regional Sulselbar | Feed aktif |
| 20 | **Bacasaja.id** | `https://news.google.com/rss/search?q=site:bacasaja.id&hl=id&gl=ID&ceid=ID:id` | Regional Sulselbar | Bypass Cloudflare via Google News RSS |

### 2.2 Nasional & Umum — 4 Portal
| No | Nama Portal | URL Feed XML | Kategori |
|:---|:---|:---|:---|
| 21 | **Antara News Terkini** | `https://www.antaranews.com/rss/terkini.xml` | Nasional & Umum |
| 22 | **CNN Indonesia Nasional** | `https://www.cnnindonesia.com/nasional/rss` | Nasional & Umum |
| 23 | **Tribunnews (Nasional)** | `https://www.tribunnews.com/rss` | Nasional & Umum |
| 24 | **ANTARA Makassar (Politik)** | `https://makassar.antaranews.com/rss/politik.xml` | Nasional & Umum |

### 2.3 Hukum & Kriminal — 3 Portal
| No | Nama Portal | URL Feed XML | Kategori |
|:---|:---|:---|:---|
| 25 | **Detikcom Hukum & Kriminal** | `https://news.detik.com/berita/rss` | Hukum & Kriminal |
| 26 | **ANTARA Makassar (Hukum)** | `https://makassar.antaranews.com/rss/hukum.xml` | Hukum & Kriminal |
| 27 | **Polisiku.co.id** | `https://polisiku.co.id/feed/` | Hukum & Kriminal |

### 2.4 Google News RSS — 2 Stream
| No | Nama Portal | URL Feed XML | Kategori |
|:---|:---|:---|:---|
| 28 | **iNews Mamuju (Keyword Search)** | `https://news.google.com/rss/search?q=inews+mamuju&hl=id&gl=ID&ceid=ID:id` | Google News RSS |
| 29 | **Google News - iNews Mamuju (Site Search)** | `https://news.google.com/rss/search?q=site:mamuju.inews.id&hl=id&gl=ID&ceid=ID:id` | Google News RSS |

---

## 3. Fitur Interaktif pada Antarmuka Pengguna

1. **Tombol `[🔄 Sinkron Berita]`**:
   - Memaksa sinkronisasi real-time langsung dari server media (`/media-intelligence?polda=SULBAR&refresh=1`).
2. **Penomoran Halaman Interaktif (Numbered Pagination)**:
   - Deretan tombol angka halaman langsung `[ 1 ] [ 2 ] [ 3 ] ... [ 9 ]` yang dilengkapi indikator aktif berwarna emas/amber menyala (`bg-amber-400 text-slate-950 font-black`).
   - Dilengkapi *smooth scroll* otomatis ke puncak kontainer berita saat berpindah halaman.
3. **Bookmark Server-Side (`[🔖 Bookmark]`)**:
   - Status tersimpan secara permanen pada basis data MySQL (kolom `is_bookmarked` pada tabel `media_articles`).
   - Berita yang di-bookmark **kebal dari pembersihan otomatis (*never pruned*)**.
4. **Distribusi Kuota Adil (*Fair Quota*)**:
   - Pengelompokan penyimpanan berita per-portal menjamin media lokal tidak terdesak menjadi 0 berita oleh volume tinggi media nasional.
5. **Eskalasi Cepat `[+ Buat Isu]`**:
   - Mengisi otomatis judul dan cuplikan berita ke modal pembuatan laporan isu Humas.

---

## 4. Alur Kerja Intelijen AI (Pipeline Seamless 1-Klik) & Naskah WhatsApp Pimpinan

1. **Pipeline 2-in-1 Seamless (`⚡ Sinkron & Analisis Otomatis`)**:
   - Operator cukup mengklik 1 tombol utama pada top bar.
   - Sistem secara otomatis menarik berita terbaru dari 22/29 portal media (`curl_multi`), menyimpannya ke database dengan fair quota, dan seketika mengumpankannya ke mesin Google Gemini AI tanpa perlu reload halaman berulang kali.
   - Data otomatis tersimpan permanen pada tabel **`media_intelligence_reports`**.
2. **Penyajian Terpadu Lintas Tab**:
   - **Tab 02 (Executive Briefing)**: Ringkasan pimpinan 200 kata, Isu Strategis Nasional Polri, Isu Kewilayahan Sulbar, respon cepat 0–2 jam, 2–6 jam, 24 jam.
   - **Tab 03 (Sentimen Berita)**: Polarisasi opini publik Nasional dan Kewilayahan.
   - **Tab 05 (Early Warning System)**: Mitigasi risiko dan peringatan dini.
   - **Tab 06 (Narasi & Rekomendasi)**: Framing narasi dan agenda setting.
   - **Tab 07 (Naskah WA Pimpinan)**: Format naskah WhatsApp resmi siap kirim ke Bapak Kapolda dan Pejabat Utama.
3. **Pemisahan Otoritas & Integrasi RBAC (`/roles`)**:
   - Modul `media_intelligence_ai`: Memberikan hak eksekusi sinkronisasi dan analisis AI otomatis.
   - Modul `media_intelligence_manage`: Khusus untuk administrator mengelola master URL feed RSS pada menu Keamanan & Pemeliharaan (`/maintenance/rss-sync`).
   - Tombol manual prompt serta tombol kelola RSS telah dihilangkan dari antarmuka operasional radar berita demi menjaga kebersihan dashboard dan mencegah perubahan tidak berwenang.

---

## 5. Smart Dossier & Manajemen Topik Atensi Khusus Pimpinan

Smart Dossier adalah instrumen penyaringan kasus sensitif atau atensi pimpinan berbasis kata kunci multi-tag (`#keyword`) dan tagging manual. Terletak pada baris teratas Radar Media Online, fitur ini memungkinkan analis Humas mengelompokkan pemberitaan dalam 1 klik.

### 5.1. Caching Analisis Intelijen AI (Irit Token & Performa Tinggi)
- **Struktur Penyimpanan**: Hasil telaah Google Gemini Pro pada topik disimpan ke kolom `ai_analysis` (JSON terstruktur), `ai_analyzed_at`, dan `ai_articles_count` di tabel `media_topics`.
- **Akses Instan Tanpa Delay**: Saat analis atau pimpinan mengklik tombol **`👁️ Lihat Hasil Analisis AI`**, modal analisis lengkap (Ringkasan Eksekutif, Potensi Risiko, Polarisasi Sentimen, Talking Points, Counter Narasi, dan Naskah WhatsApp) langsung dimuat dari database tanpa menunggu jeda pemanggilan AI eksternal dan tanpa mengonsumsi kuota token API Gemini.

### 5.2. Detektor Kebaruan Dinamis (*Topic Freshness Detector*)
- Sistem secara otomatis membandingkan jumlah artikel terkini yang cocok dengan kata kunci topik terhadap `ai_articles_count`.
- **Indikator Dinamis**:
  - Jika ada artikel baru masuk ke sistem sejak analisis terakhir: Menampilkan badge peringatan amber **`⚠️ Ada +X Berita Baru Sejak Analisis Terakhir`** serta tombol aksi **`🔄 Perbarui Analisis (+X Baru)`**.
  - Jika seluruh data telah teranalisis: Menampilkan badge hijau **`✓ Data Terkini (Up-to-Date)`**.

---

## 6. Mesin Ingesti Berita Internet Live On-Demand (Google News Crawler)

Ketika topik baru dibuat (misalnya *Peringatan HUT ke-75 Humas Polri*) dan belum ada dalam 22–29 feed lokal yang tersimpan, sistem menyediakan mesin penarikan liputan internet secara seketika (*on-demand crawler*).

### 6.1. Alur Kerja `GoogleNewsCrawlerService`
1. **Penarikan Live**: Memanggil Google News RSS endpoint (`https://news.google.com/rss/search?q=...&hl=id&gl=ID&ceid=ID:id`).
2. **Ekstraksi Sumber Nyata**: Mengekstrak tag `<source>` resmi (contoh: *Tribratanews Polda Sumsel*, *Tribrata News Bengkulu*, *Antara News*) agar identitas portal akurat.
3. **Penyimpanan Permanen**: Artikel disimpan permanen ke tabel `media_articles` dengan `polda_id`, waktu terbit, ringkasan bersih, sentimen otomatis, dan ditautkan ke topik terkait.
4. **Klasifikasi Kategori & Portal**: Otomatis dipetakan ke kategori kepolisian (Harkamtibmas, Gakkum, Humas Polri) dan dikelompokkan pada opsi filter portal **`🌐 SEBARAN INTERNET (GOOGLE NEWS)`**.
5. **Otomasi Pembuatan Topik**: Kotak centang *“Tarik berita internet sekarang (Google News Live)”* pada modal buat topik memungkinkan ingesti 25–100 berita pertama secara simultan.

### 6.2. Standar Visual Grid Simetris & Kurasi Gambar Editorial
- **Kerapian Grid**: Setiap kartu berita dikunci pada ketinggian presisi `h-[435px]` dengan *two-line text clamp* untuk judul (`h-10 md:h-11`) dan ringkasan (`h-9`), memastikan tampilan dashboard tidak berantakan (*ragged cards*).
- **Kurasi Gambar Editorial**: Berita internet yang tidak menyertakan foto thumbnail otomatis disematkan gambar editorial kepolisian beresolusi tinggi (*police fallback photos*) yang relevan, menjamin estetika profesional berstandar Command Center.
