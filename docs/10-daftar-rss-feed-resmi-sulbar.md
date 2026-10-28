# 📡 10. Daftar RSS Feed Terorganisir (Sulselbar & Nasional)

Dokumentasi ini merangkum 29 sumber RSS feed resmi yang telah dirapikan, dikelompokkan ke dalam kategori terstruktur, dan dibersihkan dari duplikasi untuk sistem **SIMOD Enterprise Media Intelligence** (Polda Sulawesi Barat).

---

## 1. Regional Sulselbar (Sulawesi Selatan & Barat) — 20 Sumber
Fokus pada liputan kamtibmas, peristiwa lokal, kebijakan pemerintah daerah, dan dinamika sosial masyarakat di 6 kabupaten se-Sulawesi Barat dan Sulawesi Selatan.

| No | Nama Portal | URL Feed XML | Status |
|:---|:---|:---|:---:|
| 1 | **Detik Sulsel** | `https://www.detik.com/sulsel/rss` | ● AKTIF |
| 2 | **Tribun Sulbar** | `https://sulbar.tribunnews.com/rss` | ● AKTIF |
| 3 | **ANTARA Makassar (Terkini)** | `https://makassar.antaranews.com/rss/terkini.xml` | ● AKTIF |
| 4 | **ANTARA Makassar (Top News)** | `https://makassar.antaranews.com/rss/top-news.xml` | ● AKTIF |
| 5 | **ANTARA Makassar (Daerah)** | `https://makassar.antaranews.com/rss/daerah.xml` | ● AKTIF |
| 6 | **ANTARA Makassar (Lintas Daerah)** | `https://makassar.antaranews.com/rss/lintas-daerah.xml` | ● AKTIF |
| 7 | **Mesakada.com** | `https://mesakada.com/feed/` | ● AKTIF |
| 8 | **Mekora.id** | `https://mekora.id/feed/` | ● AKTIF |
| 9 | **Globalsulbar** | `https://globalsulbar.com/feed/` | ● AKTIF |
| 10 | **Radar Sulbar** | `https://radarsulbar.fajar.co.id/feed/` | ● AKTIF |
| 11 | **Sulbar Express** | `https://sulbarexpress.fajar.co.id/feed/` | ● AKTIF |
| 12 | **Mamuju Pos** | `https://mamujupos.com/feed/` | ● AKTIF |
| 13 | **Katinting** | `https://katinting.com/feed/` | ● AKTIF |
| 14 | **Kabar Sulbar** | `https://kabarsulbar.com/feed/` | ● AKTIF |
| 15 | **Suwarta** | `https://suwarta.id/feed/` | ● AKTIF |
| 16 | **Sulbar Terkini** | `https://sulbarterkini.com/feed/` | ● AKTIF |
| 17 | **Malaqbi** | `https://malaqbi.com/feed/` | ● AKTIF |
| 18 | **Rakyat Sulbar** | `https://www.rakyatsulbar.com/feed/` | ● AKTIF |
| 19 | **Bugis Pos** | `https://bugispos.com/feed/` | ● AKTIF |
| 20 | **Bacasaja.id** | `https://bacasaja.id/feed/` | ● AKTIF |

---

## 2. Nasional & Umum — 4 Sumber
Memantau isu nasional, kebijakan Mabes Polri, dan peristiwa politik nasional yang berdampak pada stabilitas daerah.

| No | Nama Portal | URL Feed XML | Status |
|:---|:---|:---|:---:|
| 21 | **Antara News Terkini** | `https://www.antaranews.com/rss/terkini.xml` | ● AKTIF |
| 22 | **CNN Indonesia Nasional** | `https://www.cnnindonesia.com/nasional/rss` | ● AKTIF |
| 23 | **Tribunnews (Nasional)** | `https://www.tribunnews.com/rss` | ● AKTIF |
| 24 | **ANTARA Makassar (Politik)** | `https://makassar.antaranews.com/rss/politik.xml` | ● AKTIF |

---

## 3. Hukum & Kriminal — 3 Sumber
Memantau pengungkapan kasus, operasi kepolisian, penindakan kejahatan konvensional, transnasional, maupun peradilan pidana.

| No | Nama Portal | URL Feed XML | Status |
|:---|:---|:---|:---:|
| 25 | **Detikcom Hukum & Kriminal** | `https://news.detik.com/berita/rss` | ● AKTIF |
| 26 | **ANTARA Makassar (Hukum)** | `https://makassar.antaranews.com/rss/hukum.xml` | ● AKTIF |
| 27 | **Polisiku.co.id** | `https://polisiku.co.id/feed/` | ● AKTIF |

---

## 4. Google News RSS — 2 Sumber
Stream pencarian berbasis topik dan domain spesifik untuk menjaring pemberitaan hiperlokal Mamuju dan sekitarnya.

| No | Nama Sumber | URL Feed XML | Status |
|:---|:---|:---|:---:|
| 28 | **iNews Mamuju (Keyword Search)** | `https://news.google.com/rss/search?q=inews+mamuju&hl=id&gl=ID&ceid=ID:id` | ● AKTIF |
| 29 | **Google News - iNews Mamuju (Site Search)** | `https://news.google.com/rss/search?q=site:mamuju.inews.id&hl=id&gl=ID&ceid=ID:id` | ● AKTIF |

---

## 5. Implementasi Basis Data & Kode
- **Seeder Database**: [`database/seeders/SulbarRssFeedsSeeder.php`](file:///d:/Foto/laragon/www/simod-enterprise/database/seeders/SulbarRssFeedsSeeder.php)
- **Eksekusi Seeder**: `php artisan db:seed --class=SulbarRssFeedsSeeder`
- **Agregator Paralel**: [`app/Services/RssFeedService.php`](file:///d:/Foto/laragon/www/simod-enterprise/app/Services/RssFeedService.php)
- **Controller Media Intelligence**: [`app/Http/Controllers/MediaIntelligenceController.php`](file:///d:/Foto/laragon/www/simod-enterprise/app/Http/Controllers/MediaIntelligenceController.php)
