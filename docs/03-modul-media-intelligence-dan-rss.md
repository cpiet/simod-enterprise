# 📰 03. Modul Media Intelligence & RSS Feeds

## 1. Arsitektur Agregator Paralel (`RssFeedService`)
Modul ini bertugas memantau pemberitaan media massa daring secara real-time untuk mendeteksi narasi yang berkembang di wilayah hukum Satwil.

- **Lokasi Service**: [`app/Services/RssFeedService.php`](file:///d:/Foto/laragon/www/simod-enterprise/app/Services/RssFeedService.php).
- **Mekanisme**: Memanfaatkan **`curl_multi`** sehingga belasan portal berita ditarik secara bersamaan (*non-blocking parallel request*). Waktu agregasi rata-rata hanya membutuhkan 2 hingga 4 detik untuk 400+ berita.
- **Strategi Caching**: Hasil parse XML/Atom disimpan pada Cache Laravel (`Cache::put`) dengan TTL default 300 detik (5 menit). Penarikan berikutnya berlangsung instan (< 50ms).

---

## 2. Daftar 29 Sumber Feed Resmi Terorganisir (Sulselbar & Nasional)

### 2.1 Regional Sulselbar (Sulawesi Selatan & Barat) — 20 Portal
| No | Nama Portal | URL Feed XML | Kategori |
|:---|:---|:---|:---|
| 1 | **Detik Sulsel** | `https://www.detik.com/sulsel/rss` | Regional Sulselbar |
| 2 | **Tribun Sulbar** | `https://sulbar.tribunnews.com/rss` | Regional Sulselbar |
| 3 | **ANTARA Makassar (Terkini)** | `https://makassar.antaranews.com/rss/terkini.xml` | Regional Sulselbar |
| 4 | **ANTARA Makassar (Top News)** | `https://makassar.antaranews.com/rss/top-news.xml` | Regional Sulselbar |
| 5 | **ANTARA Makassar (Daerah)** | `https://makassar.antaranews.com/rss/daerah.xml` | Regional Sulselbar |
| 6 | **ANTARA Makassar (Lintas Daerah)** | `https://makassar.antaranews.com/rss/lintas-daerah.xml` | Regional Sulselbar |
| 7 | **Mesakada.com** | `https://mesakada.com/feed/` | Regional Sulselbar |
| 8 | **Mekora.id** | `https://mekora.id/feed/` | Regional Sulselbar |
| 9 | **Globalsulbar** | `https://globalsulbar.com/feed/` | Regional Sulselbar |
| 10 | **Radar Sulbar** | `https://radarsulbar.fajar.co.id/feed/` | Regional Sulselbar |
| 11 | **Sulbar Express** | `https://sulbarexpress.fajar.co.id/feed/` | Regional Sulselbar |
| 12 | **Mamuju Pos** | `https://mamujupos.com/feed/` | Regional Sulselbar |
| 13 | **Katinting** | `https://katinting.com/feed/` | Regional Sulselbar |
| 14 | **Kabar Sulbar** | `https://kabarsulbar.com/feed/` | Regional Sulselbar |
| 15 | **Suwarta** | `https://suwarta.id/feed/` | Regional Sulselbar |
| 16 | **Sulbar Terkini** | `https://sulbarterkini.com/feed/` | Regional Sulselbar |
| 17 | **Malaqbi** | `https://malaqbi.com/feed/` | Regional Sulselbar |
| 18 | **Rakyat Sulbar** | `https://www.rakyatsulbar.com/feed/` | Regional Sulselbar |
| 19 | **Bugis Pos** | `https://bugispos.com/feed/` | Regional Sulselbar |
| 20 | **Bacasaja.id** | `https://bacasaja.id/feed/` | Regional Sulselbar |

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

1. **Tombol `[🔄 Sinkronkan RSS]`**:
   - Berada di samping dropdown kategori.
   - Mengirim request `/?polda=SULBAR&refresh=1` yang memaksa cache di-refresh langsung dari server media.
2. **Bookmark Interaktif (`[🔖 Simpan]` / `[★ Tersimpan]`)**:
   - Status tersimpan langsung di `localStorage` per peramban petugas (`simod_ent_bookmarks`).
   - Filter `⭐ Tersimpan / Bookmark` menampilkan daftar berita yang disimpan untuk dijadikan bahan laporan pimpinan.
3. **Eskalasi Cepat `[+ Buat Isu]`**:
   - Mengklik tombol ini pada kartu berita membuka modal pop-up yang sudah terisi otomatis judul dan ringkasan berita.
   - Petugas tinggal memilih tingkat urgensi (Hijau, Kuning, Merah) dan Satker penanggung jawab, lalu data langsung tercatat di tabel `issues`.
4. **Pencarian Real-Time**:
   - Memfilter kartu berita secara instan berdasarkan kata kunci tanpa me-reload browser.

---

## 4. Fitur Salin Prompt AI, Tempel Ringkasan AI & Kelola Sumber RSS

1. **Salin Prompt AI**:
   - Menghasilkan formula prompt terstruktur resmi Senior Media Intelligence Analyst Polri.
   - Format prompt disesuaikan secara otomatis berdasarkan Polda yang aktif (Polda Sulbar, zona waktu WITA, serta daftar 6 Polres jajaran).
   - Menghasilkan output blok JSON murni yang siap disalin dan ditempelkan ke ChatGPT/Claude/Gemini tanpa biaya API eksternal.

2. **Tempel Ringkasan AI (`POST /media-intelligence/ai-briefing`)**:
   - Membuka modal input teks/JSON hasil analisis dari ChatGPT.
   - Parser otomatis membersihkan pembungkus markdown (````json ... ````), mengekstraksi metrik sentimen, ringkasan eksekutif 200 kata, atensi utama (*top concern*), isu strategis nasional vs daerah, matriks mitigasi risiko EWS, hingga format pesan WhatsApp resmi pimpinan.
   - Data disimpan ke tabel `media_intelligence_reports` dan langsung merefleksikan seluruh tab secara instan.

3. **Pilihan Portal Berita (Portal Selector Pills)**:
   - Deretan tombol pill interaktif berisi seluruh portal media aktif di Sulbar (Mamuju Pos, Katinting, Kabar Sulbar, Suwarta, Sulbar Terkini, Malaqbi, Rakyat Sulbar, Bugis Pos, Detik Sulsel, Antara, CNN, dll).
   - Dilengkapi badge counter jumlah artikel per portal.

4. **Kelola Sumber RSS & Uji Kelayakan URL Feed**:
   - Tombol **"Kelola Sumber RSS"** membuka modal manajemen URL feed.
   - Fitur uji kelayakan realtime via endpoint `POST /media-intelligence/rss-sources/test` yang memeriksa keabsahan XML RSS 2.0 / Atom beserta waktu respon server (latency ms).
   - Kemampuan menambah sumber baru, mematikan/mengaktifkan status, serta menghapus sumber media.


