# 📋 10. Daftar Lengkap 29 Sumber RSS Feed Resmi Sulbar & Nasional

Dokumen ini memuat daftar resmi dan terstandarisasi 29 URL Feed RSS yang digunakan oleh Bidang Humas Polda Sulawesi Barat dalam modul **Media Online** (`/media-intelligence`) pada SIMOD Enterprise.

---

## 1. Kategori: Regional Sulselbar (20 Portal)

| No | Nama Portal | URL Feed RSS / XML | Status & Mekanisme Sindikasi |
|---|---|---|---|
| 1 | **Detik Sulsel** | `https://www.detik.com/sulsel/rss` | Langsung (RSS 2.0) |
| 2 | **Tribun Sulbar** | `https://sulbar.tribunnews.com/rss` | Langsung (RSS 2.0) |
| 3 | **ANTARA Makassar (Terkini)** | `https://makassar.antaranews.com/rss/terkini.xml` | Langsung (XML Antara) |
| 4 | **ANTARA Makassar (Top News)** | `https://makassar.antaranews.com/rss/top-news.xml` | Langsung (XML Antara) |
| 5 | **ANTARA Makassar (Daerah)** | `https://makassar.antaranews.com/rss/daerah.xml` | Langsung (XML Antara) |
| 6 | **ANTARA Makassar (Lintas Daerah)** | `https://makassar.antaranews.com/rss/lintas-daerah.xml` | Langsung (XML Antara) |
| 7 | **Mesakada.com** | `https://mesakada.com/feed/` | Langsung (WordPress RSS) |
| 8 | **Mekora.id** | `https://mekora.id/feed/` | Langsung (WordPress RSS) |
| 9 | **Globalsulbar** | `https://globalsulbar.com/feed/` | Langsung (WordPress RSS) |
| 10 | **Radar Sulbar** | `https://radarsulbar.fajar.co.id/feed/` | Langsung (Fajar Group) |
| 11 | **Sulbar Express** | `https://sulbarexpress.fajar.co.id/feed/` | Langsung (Fajar Group) |
| 12 | **Mamuju Pos** | `https://mamujupos.com/feed/` | Langsung (WordPress RSS) |
| 13 | **Katinting** | `https://katinting.com/feed/` | Langsung (WordPress RSS) |
| 14 | **Kabar Sulbar** | `https://kabarsulbar.com/feed/` | Langsung (WordPress RSS) |
| 15 | **Suwarta** | `https://suwarta.id/feed/` | Langsung (WordPress RSS) |
| 16 | **Sulbar Terkini** | `https://sulbarterkini.com/feed/` | Langsung (WordPress RSS) |
| 17 | **Malaqbi** | `https://news.google.com/rss/search?q=site:malaqbi.com&hl=id&gl=ID&ceid=ID:id` | Google News RSS Proxy (Stabil & Bebas 404) |
| 18 | **Rakyat Sulbar** | `https://www.rakyatsulbar.com/feed/` | Langsung (WordPress RSS) |
| 19 | **Bugis Pos** | `https://bugispos.com/feed/` | Langsung (WordPress RSS) |
| 20 | **Bacasaja.id** | `https://news.google.com/rss/search?q=site:bacasaja.id&hl=id&gl=ID&ceid=ID:id` | Google News RSS Proxy (Bypass Cloudflare 403) |

---

## 2. Kategori: Nasional & Umum (4 Portal)

| No | Nama Portal | URL Feed RSS / XML | Status & Mekanisme Sindikasi |
|---|---|---|---|
| 21 | **Antara News Terkini** | `https://www.antaranews.com/rss/terkini.xml` | Langsung (LKBN Antara) |
| 22 | **CNN Indonesia Nasional** | `https://www.cnnindonesia.com/nasional/rss` | Langsung (Transmedia) |
| 23 | **Tribunnews (Nasional)** | `https://www.tribunnews.com/rss` | Langsung (Kompas Gramedia) |
| 24 | **ANTARA Makassar (Politik)** | `https://makassar.antaranews.com/rss/politik.xml` | Langsung (LKBN Antara) |

---

## 3. Kategori: Hukum & Kriminal (3 Portal)

| No | Nama Portal | URL Feed RSS / XML | Status & Mekanisme Sindikasi |
|---|---|---|---|
| 25 | **Detikcom Hukum & Kriminal** | `https://news.detik.com/berita/rss` | Langsung (Detik Network) |
| 26 | **ANTARA Makassar (Hukum)** | `https://makassar.antaranews.com/rss/hukum.xml` | Langsung (LKBN Antara) |
| 27 | **Polisiku.co.id** | `https://polisiku.co.id/feed/` | Langsung (Siber Polri Media) |

---

## 4. Kategori: Google News RSS (2 Stream Khusus)

| No | Nama Portal | URL Feed RSS / XML | Target Pantauan |
|---|---|---|---|
| 28 | **iNews Mamuju (Keyword Search)** | `https://news.google.com/rss/search?q=inews+mamuju&hl=id&gl=ID&ceid=ID:id` | Keyword query `inews mamuju` |
| 29 | **Google News - iNews Mamuju (Site Search)** | `https://news.google.com/rss/search?q=site:mamuju.inews.id&hl=id&gl=ID&ceid=ID:id` | Domain spesifik `mamuju.inews.id` |

---

## 5. Algoritma Fair Quota & Auto-Cap Kestabilan

Agar seluruh 29 portal media selalu terisi berita (tidak ada yang kosong atau 0 berita):
1. **Penyimpanan Berimbang**: Tiap portal diberi kuota simpan maksimum 25–30 artikel per batch penarikan.
2. **Auto-Cap Per-Portal**: Pemangkasan artikel non-bookmark tertua dilakukan per-sumber jika kuota melebihi kapasitas yang ditentukan di menu *Otomasi Sinkron RSS*.
3. **Proteksi Bookmark Permanen**: Artikel yang ditandai bookmark (`is_bookmarked = true`) **kebal** dari segala jenis pemangkasan atau pembersihan data otomatis.
