# 📰 03. Modul Media Intelligence & RSS Feeds

## 1. Arsitektur Agregator Paralel (`RssFeedService`)
Modul ini bertugas memantau pemberitaan media massa daring secara real-time untuk mendeteksi narasi yang berkembang di wilayah hukum Satwil.

- **Lokasi Service**: [`app/Services/RssFeedService.php`](file:///d:/Foto/laragon/www/simod-enterprise/app/Services/RssFeedService.php).
- **Mekanisme**: Memanfaatkan **`curl_multi`** sehingga belasan portal berita ditarik secara bersamaan (*non-blocking parallel request*). Waktu agregasi rata-rata hanya membutuhkan 2 hingga 4 detik untuk 400+ berita.
- **Strategi Caching**: Hasil parse XML/Atom disimpan pada Cache Laravel (`Cache::put`) dengan TTL default 300 detik (5 menit). Penarikan berikutnya berlangsung instan (< 50ms).

---

## 2. Daftar 14 Sumber Feed Resmi Wilayah Sulawesi Barat

| No | Nama Portal | URL Feed RSS | Kategori Utama |
|:---|:---|:---|:---|
| 1 | **Detik Sulsel** | `https://www.detik.com/sulsel/rss` | Regional Sulselbar |
| 2 | **Mamuju Pos** | `https://mamujupos.com/feed/` | Regional Sulselbar |
| 3 | **Katinting** | `https://katinting.com/feed/` | Regional Sulselbar |
| 4 | **Kabar Sulbar** | `https://kabarsulbar.com/feed/` | Regional Sulselbar |
| 5 | **Suwarta** | `https://suwarta.id/feed/` | Regional Sulselbar |
| 6 | **Sulbar Terkini** | `https://sulbarterkini.com/feed/` | Regional Sulselbar |
| 7 | **Malaqbi** | `https://malaqbi.com/feed/` | Regional Sulselbar |
| 8 | **Rakyat Sulbar** | `https://www.rakyatsulbar.com/feed/` | Regional Sulselbar |
| 9 | **iNews Mamuju (Google News)** | `https://news.google.com/rss/search?q=inews+mamuju...` | Regional Sulselbar |
| 10 | **Bugis Pos** | `https://bugispos.com/feed/` | Regional Sulselbar |
| 11 | **Google News Polda Sulbar** | `https://news.google.com/rss/search?q=polda+sulbar...` | Harkamtibmas |
| 12 | **Antara News Terkini** | `https://www.antaranews.com/rss/terkini.xml` | Nasional |
| 13 | **CNN Indonesia Nasional** | `https://www.cnnindonesia.com/nasional/rss` | Nasional |
| 14 | **Detikcom Hukum & Kriminal** | `https://news.detik.com/berita/rss` | Hukum & Kriminal |

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
