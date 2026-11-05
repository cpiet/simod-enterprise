# 🧭 29. Radar Media Online: Pencarian Server, Kesegaran Data, dan Command Center Berbasis Data Nyata

Dokumen ini mencatat perubahan 5–6 November 2026: perbaikan galat Gemini 503, penataan ulang Radar Media Online (Paket A–D), penegakan tenant pada aksi berita/topik, dan perombakan Command Center agar **tidak ada angka karangan**. Baseline setelah perubahan: **195 tests, 1287 assertions**, 120 rute, 39 tabel.

---

## 1. Galat Gemini "high demand" (HTTP 503)

Pesan *"This model is currently experiencing high demand"* adalah galat **server Google sibuk (503)**, bukan token salah (403) dan bukan kuota habis (429). Perbaikan di [`GeminiService`](../app/Services/GeminiService.php):

| Perbaikan | Rincian |
|:---|:---|
| Pesan jelas | `friendlyAiError()` menjelaskan "server Google sedang sibuk, bukan masalah API Key atau kuota; coba 1–5 menit lagi". |
| Tercatat di log | `logHttpFailure()` mencatat model dan status untuk respons selain 403/429 (sebelumnya 503 tidak tercatat sama sekali). |
| Kunci tidak bocor | API key kini dikirim lewat header `x-goog-api-key`, bukan `?key=` di URL. Sebelumnya pesan galat timeout memuat URL lengkap beserta kunci. `redactSecrets()` menyamarkan pola `AIza...` pada pesan galat/log. |
| Coba ulang bertahap | `client()` mencoba ulang 503 dengan jeda `services.gemini.retry_delays` (bawaan 3 dtk lalu 6 dtk). Di test jeda dinolkan oleh `tests/TestCase`. |

Test: `GeminiBusyServerTest`.

---

## 2. Paket A — Data yang Bisa Dipercaya

### 2.1. Penanda kesegaran (menggantikan label "Live")
Header Radar Media Online menampilkan waktu sinkron terakhir: **hijau** < 1 jam, **kuning** < 6 jam, **merah** lebih lama atau belum pernah. Sumbernya `rss_sync_logs` (sinkron berhasil, termasuk sinkron manual dari antarmuka yang kini dicatat sebagai `manual_ui`) dan waktu ingest artikel terbaru. Badge tab 01 mengikuti: `LIVE` / `TERTUNDA` / `BASI`.

### 2.2. Sentimen berbasis konteks Polri
[`NewsSentiment`](../app/Support/NewsSentiment.php) (aturan, tanpa AI, tanpa biaya) menilai dari sudut **citra dan kewaspadaan Polri**:

| Contoh judul | Hasil |
|:---|:---|
| "Polisi tangkap pengedar sabu" | positif (kinerja, bukan negatif seperti aturan lama) |
| "Oknum polisi diduga peras warga" / "Jenderal ... ditangkap" | negatif |
| "Bentrok antarwarga", "tawuran", "teror" | negatif (gangguan kamtibmas) |
| "Polisi ungkap kasus pembunuhan" | netral (kinerja menyeimbangkan peristiwa) |
| "Kapolri tegaskan reformasi Polri" | netral (hanya "desak/tuntut reformasi" yang negatif) |

Judul dinilai lebih dulu; ringkasan hanya dipakai bila judul netral. Dipakai oleh `RssFeedService`, `GoogleNewsCrawlerService`, dan API ingest. Sebelumnya sinkron RSS selalu mengisi `netral` (96% berita netral). Data lama dihitung ulang dengan:

```bash
php artisan media:reindex                  # sentimen + sidik judul, semua Polda
php artisan media:reindex --polda=SULBAR   # satu Polda
php artisan media:reindex --only=sentiment # atau --only=hash
```
Aturan dapat disetel di konstanta `NewsSentiment`; bukan penilaian resmi, hanya alat bantu pemantauan.

### 2.3. Berita ganda digabung
Kolom `media_articles.title_hash` (migrasi `2026_11_06_090000`) menyimpan sidik judul ternormalisasi (huruf kecil, tanpa sufiks " - Detikcom", tanpa tanda baca). Model `MediaArticle` mengisinya otomatis pada setiap simpan. Daftar menampilkan satu kartu per cerita dengan lencana **+N portal**; portal lain terlihat di menu ⋯ kartu atau baris daftar. Dapat dimatikan lewat opsi "Gabungkan berita ganda" di panel Filter (`group=0`).

### 2.4. Menjalankan penjadwal di Windows / Laragon
Sinkron RSS otomatis (`rss:sync --scheduled`, tiap menit) hanya berjalan bila penjadwal Laravel dipanggil. Di lokal Windows, buat tugas Task Scheduler yang menjalankan setiap menit:

```powershell
schtasks /Create /SC MINUTE /MO 1 /TN "SIMOD Scheduler" /TR "\"<laragon>\bin\php\php-8.2.x\php.exe\" \"<folder-proyek>\artisan\" schedule:run"
```
Di server Linux: `* * * * * cd /path/simod && php artisan schedule:run >> /dev/null 2>&1`. Tanpa ini penanda kesegaran akan merah.

---

## 3. Paket B — Pencarian Berita di Server

Seluruh pencarian, penyaringan, penggabungan, dan paginasi kini di server ([`MediaArticleListing`](../app/Services/MediaArticleListing.php)); browser tidak lagi menerima 3.000 artikel sekaligus. URL halaman menyimpan filter, sehingga tautan dan refresh memulihkan tampilan.

| Fitur | Parameter query | Keterangan |
|:---|:---|:---|
| Pencarian | `q` | `kata kunci` (semua kata harus ada), `"frasa persis"`, `-kecualikan`. Mencari judul, ringkasan, nama portal; kata yang cocok disorot (tanpa `v-html`). |
| Periode | `period=today\|24h\|7d\|30d\|custom`, `from`, `to` | Zona waktu "Hari ini" mengikuti Polda aktif. |
| Urutan | `sort=newest\|relevance` | Relevansi: judul bobot 3, ringkasan bobot 1. |
| Filter | `portal`, `category`, `sentiment`, `bookmarks`, `topic` | Portal "Sebaran Internet" = hasil Google News. |
| Gabung ganda | `group` (bawaan 1) | Lihat 2.3. |
| Paginasi | `page`, `per_page` (6–60) | Halaman di luar jangkauan dipangkas, bukan galat. |

**Paritas angka:** setiap facet (portal, kategori, sentimen, wilayah, tersimpan) dihitung dengan seluruh filter aktif **kecuali dirinya sendiri**, sehingga angka di dropdown sama dengan jumlah kartu saat opsi itu dipilih. Daftar portal hanya memuat portal terdaftar (selalu tampil walau 0 berita) plus opsi Sebaran Internet.

Props halaman baru: `sampleArticles` (halaman ini saja), `articlesMeta`, `searchTerms`, `filters`, `facets`, `stats`, `freshness`, `situation`, `isNational`, `myArea`. Props lama `portalsList`/`categoriesList` dihapus.

Tombol "Simpan pencarian jadi Topik Atensi" membuka formulir topik terisi dari kata pencarian (hanya untuk izin `media_intelligence_manage`).

Kinerja pada data nyata (2.628 artikel): daftar 39 ms, pencarian 41 ms, facet 71 ms.

---

## 4. Paket C — Tampilan Menurut Peran

| Peran | Perilaku |
|:---|:---|
| Pimpinan (`kadiv_humas`, `polda_leader`) | Mendarat di tab Briefing; di atasnya kartu **Situasi 24 Jam Terakhir** ([`MediaSituationCard.vue`](../resources/js/Components/MediaSituationCard.vue)): berita masuk dan Zona Merah vs 24 jam sebelumnya, portal paling aktif, tiga berita sensitif terbaru (tautan asli), "perhatian utama" dari analisis AI terakhir. Semua angka dari basis data. |
| Analis | Tombol tampilan **Kartu / Daftar** (daftar ringkas, 30 judul per layar), tersimpan di peramban. |
| Operator | Hanya tab **Berita** (tab analisis AI disembunyikan bila tanpa izin `media_intelligence_ai`). Blok Smart Dossier disembunyikan bila belum ada topik dan tidak berhak mengelola. Pintasan **Wilayah Saya: \<nama Polres\>** mencari berita yang menyebut wilayahnya (`Polres::placeKeyword()`). |

---

## 5. Paket D — Lingkup Nasional

Pada mode **Nasional (`ALL`)**, daftar berita mencakup seluruh Polda aktif (bukan hanya 33 feed Divhumas). Kartu menampilkan lencana wilayah, dan filter **Wilayah (Polda)** tersedia (`wilayah=<id>`, hanya dihormati pada mode nasional; di mode Polda diabaikan). Mode ini hanya dapat dicapai Mabes/Super Admin (`resolveActiveTenant`). Analisis AI nasional tetap atas berita tenant Divhumas.

---

## 6. Command Center: Tanpa Angka Karangan

Audit menemukan banyak nilai bawaan palsu. Semuanya dihapus; bila data belum ada, UI menampilkan "belum ada data".

| Sebelumnya | Sekarang |
|:---|:---|
| Selisih indeks citra `+6%` tetap | Selisih **poin** terhadap 7 hari sebelumnya (`publicIndex.change`), null bila salah satu periode kosong. |
| Indeks citra `78%` bila kosong | `null` ("—"). Rumus: positif ÷ (positif + negatif) atas berita, postingan medsos, dan laporan isu 7 hari. |
| Hari tanpa data diisi 76/18/6% | Hari kosong tampil "Tidak ada data"; hari berisi menampilkan jumlah data. |
| Tingkat penyelesaian tugas `100%` tanpa tugas | `null` ("Belum ada tugas"). |
| Barometer sentimen media/medsos bawaan 80/15/5 dan 65/20/15; nilai 0 asli ikut diganti angka bawaan | Nilai dari analisis AI terakhir atau hitungan nyata; bila belum ada: "Belum ada analisis". |
| `29 Portal`, `4 Platform`, `6 Polres`, `3 Isu` tertulis tetap | Dihitung dari data (`active_feeds_count`, `platform_count`, `polres_count`, `reportIssues.length`). |
| "SITUASI KONDUSIF & TERKENDALI" tanpa data | `BELUM ADA LAPORAN LAPANGAN` bila belum ada laporan; `SEMUA LAPORAN TELAH DITANGANI` bila semua selesai. |
| Laporan berstatus draf/arsip dihitung aktif | Aktif = kelompok `pending`, `active`, `clarified` (`Issue::STATUS_GROUPS`), termasuk nama status lama. |

Tren sentimen kini 3 kueri agregat (sebelumnya 21 kueri yang memuat seluruh baris).

### 6.1. Matriks Kondusifitas Satwil & Jajaran Polda
**Berfungsi dan sepenuhnya dari data nyata**, dengan perbaikan berikut. Masalah sebelumnya: kosong total pada mode Nasional (mencari Polres milik tenant Divhumas yang tidak punya Polres); klik baris memanggil fungsi yang tidak ada; sel kosong untuk satwil tanpa data; "Skor Opini" sebenarnya persentase laporan bersentimen positif sehingga menyesatkan; judul menyebut "6 Polres" tetap.

Cara kerja ([`DashboardController::buildSatwilMatrix`](../app/Http/Controllers/DashboardController.php)):
- **Mode Nasional:** satu baris per Polda aktif (Divhumas = "Mabes"). Klik baris membuka Command Center Polda itu lewat `POST /tenant/switch`.
- **Mode Polda:** satu baris per Polres aktif, ditambah baris "Tingkat Polda (tanpa Satwil)" bila ada laporan tanpa Polres.
- **Status** hanya dari **laporan isu aktif** satwil itu:

| Status | Syarat |
|:---|:---|
| `ATENSI` | ada laporan aktif berprioritas tinggi/kritis |
| `WASPADA` | ada laporan aktif (prioritas lain) |
| `KONDUSIF` | ada laporan dan seluruhnya selesai/arsip |
| `BELUM ADA DATA` (label "BELUM ADA LAPORAN") | belum ada laporan; **bukan berarti aman** |

- **Berita 7 hari** (jumlah dan yang negatif) hanya informasi pendamping, tidak memengaruhi status. Mode Polda mencocokkan nama wilayah Polres pada judul/ringkasan (perkiraan); nama kurang dari 4 huruf tidak dicocokkan (tampil "—").
- Baris diurutkan ATENSI → WASPADA → KONDUSIF → BELUM ADA DATA. Tampilan memuat kotak "Cara membaca matriks ini" dan pesan kosong yang menjelaskan sebabnya.
- Keterbatasan: Polda yang dinonaktifkan tidak tampil di matriks, walau laporannya masih ikut KPI "Laporan Lapangan" nasional.

---

## 7. Penegakan tenant pada aksi berita & topik (temuan keamanan)

`MediaIntelligenceController` tidak memeriksa tenant pada bookmark, ubah/hapus topik, tag artikel, analisis topik AI, dan crawl topik: personel Polda A dapat mengubah data Polda B dengan menebak ID. Kini semua memanggil `authorizeTenantRecord()`; tag artikel mewajibkan artikel dan topik milik wilayah yang sama; impor bookmark mengabaikan ID asing. Test: `MediaIntelligenceTenantIsolationTest`.

---

## 8. Pengujian
`php artisan test` → **195 passed (1287 assertions)**; `npm.cmd run build` bersih. Berkas baru: `GeminiBusyServerTest`, `MediaIntelligenceListingTest`, `MediaIntelligenceTenantIsolationTest`, `CommandCenterRealDataTest`, `tests/Unit/NewsSentimentAndSearchTest`. Halaman diuji juga di Chrome headless (peran pimpinan, operator, super admin; mode Nasional dan Polda): tanpa galat JavaScript.

## 9. Belum dikerjakan
- Pencarian teks penuh (FULLTEXT) bila arsip melampaui puluhan ribu artikel; saat ini `LIKE` dengan indeks `(polda_id, pub_date)`.
- Peta Command Center masih memakai `polda_id` tetap (5–10) dan pencocokan kata kunci wilayah; perlu dibuat dinamis.
- Menjalankan penjadwal otomatis untuk analisis AI dan Apify (lihat doc 27 dan 28).
