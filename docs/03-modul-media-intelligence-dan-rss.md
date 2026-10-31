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

## 4. Alur Kerja Intelijen AI & Naskah WhatsApp Pimpinan

1. **Salin Prompt AI**:
   - Merangkum 29 feed berita menjadi formula prompt resmi analis intelijen media Polri.
2. **Tempel Ringkasan AI**:
   - Pengguna menempelkan hasil analisis ChatGPT / Claude / Gemini pada modal *"Tempel Ringkasan AI"*.
   - Data otomatis tersimpan permanen pada tabel **`media_intelligence_reports`**.
3. **Penyajian Terpadu Lintas Tab**:
   - **Tab 02 (Executive Briefing)**: Ringkasan pimpinan 200 kata, Isu Strategis Nasional Polri, Isu Kewilayahan Sulbar, respon cepat 0–2 jam, 2–6 jam, 24 jam.
   - **Tab 03 (Sentimen Berita)**: Polarisasi opini publik Nasional dan Kewilayahan.
   - **Tab 05 (Early Warning System)**: Mitigasi risiko dan peringatan dini.
   - **Tab 06 (Narasi & Rekomendasi)**: Framing narasi dan agenda setting.
   - **Tab 07 (Naskah WA Pimpinan)**: Format naskah WhatsApp resmi siap kirim ke Bapak Kapolda dan Pejabat Utama.
