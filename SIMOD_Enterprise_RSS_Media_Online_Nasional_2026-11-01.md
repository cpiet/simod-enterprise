---
document_id: SIMOD-RSS-INDONESIA-2026-11-01
document_type: source_registry_and_ingestion_spec
project: SIMOD Enterprise
locale: id-ID
timezone: Asia/Jakarta
tested_on: 2026-11-01
scope: Indonesia_national_and_provinces
status: initial_live_endpoint_validation
schema_version: 1.0.0
---
# SIMOD Enterprise — Katalog RSS Media Online Nasional & Google News

**Penggunaan:** dokumen sumber data awal untuk agent/developer penyusun ingestion pipeline media online SIMOD Enterprise. **Tanggal pemeriksaan: 1 November 2026 (WIB).** Fokus tahap 1 = artikel media online, **bukan** posting media sosial.

## 0. Arti hasil uji (WAJIB DIBACA AGENT)

- `LIVE_OK`: URL **benar-benar diminta pada 1 November 2026** dengan fetch web eksternal; isi yang dikembalikan adalah XML/RSS/Atom atau hasil ekstraksi atas sumber dengan MIME XML, terdapat materi berita. Bukan janji uptime selanjutnya. Pemeriksaan ini **bukan** pengujian HTTP dan parser dari server produksi SIMOD.
- `GOOGLE_LIVE_OK`: URL Google News **spesifik** diuji dan menghasilkan dokumen `<rss>` serta `<item>`. Ini **bukan feed resmi** dari penerbit yang disebut di query.
- `GOOGLE_TEMPLATE`: URL dibuat dari pola Google News yang telah diuji, tetapi **setiap varian yang dihasilkan belum diuji satu per satu**; wajib lakukan uji di worker sebelum enable.
- `FAILED`: pada percobaan uji, sumber mengembalikan 404, diblokir bot, salah jenis konten, atau tidak terjangkau. Jangan aktifkan tanpa pemeriksaan ulang dari server aplikasi.
- `READY`: siap dicoba sebagai sumber awal / seed, **tetap validasi sebelum ditandai HEALTHY di deployment**: status 2xx, dokumen XML valid, `<channel>` atau `<feed>`, minimal 1 item relevan, tanggal dan URL berita benar.
- Frekuensi polling berikut adalah **rekomendasi operasional**, bukan SLA/izin dari pihak media. Cek `robots.txt`, syarat RSS, `Retry-After`, batas akses, dan ketentuan lisensi. Jangan menyalin teks penuh atau foto untuk redistribusi tanpa izin.

## 1. RSS langsung penerbit — Nasional (semuanya LIVE_OK saat diuji)

| ID | Media | Kanal | RSS URL (langsung) | Kategori | Prioritas | Poll (menit) | Status |
|---|---|---|---|---|---|---:|---|
| N001 | ANTARA | Terkini | `https://www.antaranews.com/rss/terkini.xml` | NASIONAL | P0 | 10 | LIVE_OK |
| N002 | ANTARA | Top News | `https://www.antaranews.com/rss/top-news.xml` | NASIONAL | P1 | 15 | LIVE_OK |
| N003 | ANTARA | Politik | `https://www.antaranews.com/rss/politik.xml` | POLITIK | P1 | 15 | LIVE_OK |
| N004 | ANTARA | Hukum | `https://www.antaranews.com/rss/hukum.xml` | HUKUM | P0 | 10 | LIVE_OK |
| N005 | ANTARA | Metro Kriminalitas | `https://www.antaranews.com/rss/metro-kriminalitas.xml` | KRIMINAL | P0 | 10 | LIVE_OK |
| N006 | ANTARA | Metro | `https://www.antaranews.com/rss/metro.xml` | METRO | P1 | 15 | LIVE_OK |
| N007 | ANTARA | Ekonomi | `https://www.antaranews.com/rss/ekonomi.xml` | EKONOMI | P2 | 30 | LIVE_OK |
| N008 | Detik | Berita | `https://news.detik.com/berita/rss` | NASIONAL | P0 | 10 | LIVE_OK |
| N009 | Detik | Finance | `https://finance.detik.com/rss` | EKONOMI | P2 | 30 | LIVE_OK |
| N010 | Detik | Inet | `https://inet.detik.com/rss` | TEKNOLOGI | P2 | 30 | LIVE_OK |
| N011 | CNN Indonesia | Nasional | `https://www.cnnindonesia.com/nasional/rss` | NASIONAL | P0 | 10 | LIVE_OK |
| N012 | CNN Indonesia | Ekonomi | `https://www.cnnindonesia.com/ekonomi/rss` | EKONOMI | P2 | 30 | LIVE_OK |
| N013 | CNBC Indonesia | News | `https://www.cnbcindonesia.com/news/rss` | NASIONAL | P1 | 15 | LIVE_OK |
| N014 | CNBC Indonesia | Market | `https://www.cnbcindonesia.com/market/rss` | EKONOMI | P2 | 30 | LIVE_OK |
| N015 | Tempo | Semua berita | `https://rss.tempo.co/` | NASIONAL | P1 | 15 | LIVE_OK |
| N016 | Republika | Semua berita | `https://www.republika.co.id/rss` | NASIONAL | P1 | 15 | LIVE_OK |
| N017 | Liputan6 | News/Peristiwa | `https://feed.liputan6.com/rss/news` | NASIONAL | P1 | 15 | LIVE_OK |
| N018 | Media Indonesia | Semua berita | `https://mediaindonesia.com/feed` | NASIONAL | P1 | 15 | LIVE_OK |
| N019 | SINDOnews | Nasional | `https://nasional.sindonews.com/rss` | NASIONAL | P1 | 15 | LIVE_OK |
| N020 | Tribunnews | Semua berita | `https://www.tribunnews.com/rss` | NASIONAL | P1 | 15 | LIVE_OK |
| N021 | JPNN | Semua berita | `https://www.jpnn.com/rss` | NASIONAL | P1 | 15 | LIVE_OK |
| N022 | BBC News Indonesia | Semua berita | `https://feeds.bbci.co.uk/indonesia/rss.xml` | NASIONAL | P2 | 30 | LIVE_OK |
| N023 | CNA Indonesia | Latest News | `https://www.cna.id/api/v1/rss-outbound-feed?_format=xml` | NASIONAL | P2 | 30 | LIVE_OK |
| N024 | VOI | News | `https://www.voi.id/rss` | NASIONAL | P1 | 15 | LIVE_OK |
| N025 | Kumparan | Semua berita | `https://lapi.kumparan.com/v2.0/rss/` | NASIONAL | P1 | 15 | LIVE_OK |

**Catatan khusus:** RSS `https://www.okezone.com/rss` memang berhasil di-fetch, tetapi saat diuji judul kanal yang muncul adalah **bola.okezone.com**, sehingga **tidak** disarankan sebagai sumber agregat berita nasional tanpa audit pemetaan kanal. RSS Kumparan mengembalikan konten melalui endpoint bertipe `application/xml`; cek parser asli pada worker SIMOD. RSS Tempo utama dapat mencampur konten nasional, lifestyle, dan internasional. **CNA Indonesia menyatakan layanan RSS mereka untuk penggunaan pribadi dan non-komersial; jangan aktifkan sumber CNA untuk aplikasi enterprise/organisasi tanpa meninjau ketentuan dan memperoleh izin yang diperlukan** (lihat https://www.cna.id/rss).

## 2. RSS langsung penerbit — ANTARA Daerah (LIVE_OK)

Untuk mendukung *coverage* Indonesia hingga wilayah Polda/Satwil. Feed ANTARA wilayah **tidak otomatis menjangkau semua kabupaten/provinsi**; tambahkan Google News regional untuk mengisi celah.

| ID | Wilayah liputan | RSS URL langsung | Status |
|---|---|---|---|
| A01 | Aceh | `https://aceh.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A02 | Sumatera Utara | `https://sumut.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A03 | Sumatera Barat | `https://sumbar.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A04 | Riau | `https://riau.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A05 | Kepulauan Riau | `https://kepri.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A06 | Jambi | `https://jambi.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A07 | Kepulauan Bangka Belitung | `https://babel.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A08 | Sumatera Selatan | `https://sumsel.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A09 | Lampung | `https://lampung.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A10 | Banten | `https://banten.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A11 | Jawa Barat | `https://jabar.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A12 | Jawa Tengah | `https://jateng.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A13 | DI Yogyakarta | `https://jogja.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A14 | Jawa Timur | `https://jatim.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A15 | Bali | `https://bali.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A16 | Nusa Tenggara Barat | `https://mataram.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A17 | Kalimantan Barat | `https://kalbar.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A18 | Kalimantan Tengah | `https://kalteng.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A19 | Kalimantan Selatan | `https://kalsel.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A20 | Kalimantan Timur | `https://kaltim.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A21 | Sulawesi Selatan | `https://makassar.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A22 | Sulawesi Tengah | `https://sulteng.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A23 | Sulawesi Tenggara | `https://sultra.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A24 | Gorontalo | `https://gorontalo.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A25 | Maluku | `https://ambon.antaranews.com/rss/terkini.xml` | LIVE_OK |
| A26 | Papua | `https://papua.antaranews.com/rss/terkini.xml` | LIVE_OK |

**Batasan:** domain ANTARA daerah tidak selalu identik dengan nama provinsi (contoh NTB=`mataram`, Sulsel=`makassar`, Maluku=`ambon`). **Jangan** menganggap pola `https://{kodeprovinsi}.antaranews.com/rss/...` universal. Endpoint contoh yang gagal: `sulbar.antaranews.com`, `ntt.antaranews.com`, `sulut.antaranews.com`, dan `malut.antaranews.com` (invalid/unavailable dalam pemeriksaan); gunakan pencarian Google News sebagai pelengkap, bukan menebak URL.

## 3. RSS langsung Tribun daerah (LIVE_OK)

| ID | Wilayah | RSS URL langsung | Status |
|---|---|---|---|
| T01 | Aceh / Serambi | `https://aceh.tribunnews.com/rss` | LIVE_OK |
| T02 | Sumatera Utara / Medan | `https://medan.tribunnews.com/rss` | LIVE_OK |
| T03 | Jawa Barat | `https://jabar.tribunnews.com/rss` | LIVE_OK |
| T04 | DI Yogyakarta | `https://jogja.tribunnews.com/rss` | LIVE_OK |
| T05 | Jawa Timur | `https://jatim.tribunnews.com/rss` | LIVE_OK |
| T06 | Sumatera Selatan | `https://sumsel.tribunnews.com/rss` | LIVE_OK |
| T07 | Banten | `https://banten.tribunnews.com/rss` | LIVE_OK |

## 4. Google News Indonesia — feed yang sudah diuji LIVE

**Beranda/Headline Indonesia:** `https://news.google.com/rss?hl=id&gl=ID&ceid=ID%3Aid` — `GOOGLE_LIVE_OK` (berita headline, bukan indeks seluruh berita Indonesia).

| ID | Fungsi | RSS URL | Status |
|---|---|---|---|
| G001 | Polri + Polda + Polres / 24 jam | `https://news.google.com/rss/search?q=%28Polri%20OR%20Polda%20OR%20Polres%29%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| G002 | Kata kunci Polri / 24 jam | `https://news.google.com/rss/search?q=Polri%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| G003 | Polda Sulbar / 7 hari | `https://news.google.com/rss/search?q=Polda%20Sulbar%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| G004 | Polda Gorontalo / 7 hari | `https://news.google.com/rss/search?q=%22Polda%20Gorontalo%22%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| G005 | Sulawesi Barat / 24 jam | `https://news.google.com/rss/search?q=%22Sulawesi%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| G006 | Konten Tribratanews / 7 hari | `https://news.google.com/rss/search?q=site%3Atribratanews.polri.go.id%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| G007 | CNN Indonesia yang menyebut polisi / 7 hari | `https://news.google.com/rss/search?q=site%3Acnnindonesia.com%20%22polisi%22%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |

**Penting:** feed Google News berisi berita terpilih/diindeks oleh Google, tidak menjamin hasil lengkap. Pada beberapa pencarian luas hasil teramati berhenti sekitar 100 item; pecah keyword menjadi kanal dan wilayah agar tidak kehilangan berita. Waktu artikel di Google News bukan selalu waktu terbit di situs asli; simpan waktu asli bila tersedia. `when:1d` / `when:7d` adalah operator pencarian, **tetap filter batas waktu dalam aplikasi**.

## 5. Google News per-domain media — backup yang sudah diuji LIVE

Feed ini mengambil **hasil Google News untuk domain**; bukan RSS resmi dari pemilik domain. Cocok untuk media tanpa feed publik yang bekerja di percobaan ini. Sebagian domain di bawah juga punya RSS langsung; utamakan RSS langsung untuk mengurangi keterlambatan dan duplikasi.

| ID | Media | Domain target | Google News RSS URL | Status |
|---|---|---|---|---|
| D01 | Kompas.com | `kompas.com` | `https://news.google.com/rss/search?q=site%3Akompas.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D02 | Tribunnews.com | `tribunnews.com` | `https://news.google.com/rss/search?q=site%3Atribunnews.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D03 | Suara.com | `suara.com` | `https://news.google.com/rss/search?q=site%3Asuara.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D04 | Liputan6.com | `liputan6.com` | `https://news.google.com/rss/search?q=site%3Aliputan6.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D05 | Tempo.co | `tempo.co` | `https://news.google.com/rss/search?q=site%3Atempo.co%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D06 | Kumparan.com | `kumparan.com` | `https://news.google.com/rss/search?q=site%3Akumparan.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D07 | Tirto.id | `tirto.id` | `https://news.google.com/rss/search?q=site%3Atirto.id%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D08 | Bisnis.com | `bisnis.com` | `https://news.google.com/rss/search?q=site%3Abisnis.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D09 | Katadata.co.id | `katadata.co.id` | `https://news.google.com/rss/search?q=site%3Akatadata.co.id%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D10 | BeritaSatu.com | `beritasatu.com` | `https://news.google.com/rss/search?q=site%3Aberitasatu.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D11 | VIVA.co.id | `viva.co.id` | `https://news.google.com/rss/search?q=site%3Aviva.co.id%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D12 | iNews.id | `inews.id` | `https://news.google.com/rss/search?q=site%3Ainews.id%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D13 | IDN Times | `idntimes.com` | `https://news.google.com/rss/search?q=site%3Aidntimes.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |
| D14 | CNN Indonesia | `cnnindonesia.com` | `https://news.google.com/rss/search?q=site%3Acnnindonesia.com%20when%3A7d&hl=id&gl=ID&ceid=ID%3Aid` | GOOGLE_LIVE_OK |

## 6. Coverage seluruh 38 provinsi — Google News template per wilayah

**Status semua baris tabel di bawah: `GOOGLE_TEMPLATE` (dibuat berdasarkan pola yang berjalan; endpoint masing-masing belum di-fetch satu per satu).** Query menggunakan nama provinsi + `when:1d`. Gunakan alias kecamatan/kabupaten, nama Polda/Polres, dan geo-tags terpisah untuk memperkaya cakupan. Nama provinsi **bukan bukti lokasi kejadian**; klasifikasikan provinsi melalui NER + verifikasi konten.

| Provinsi | Query | RSS URL hasil generator |
|---|---|---|
| Aceh | `"Aceh" when:1d` | `https://news.google.com/rss/search?q=%22Aceh%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sumatera Utara | `"Sumatera Utara" when:1d` | `https://news.google.com/rss/search?q=%22Sumatera%20Utara%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sumatera Barat | `"Sumatera Barat" when:1d` | `https://news.google.com/rss/search?q=%22Sumatera%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Riau | `"Riau" when:1d` | `https://news.google.com/rss/search?q=%22Riau%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kepulauan Riau | `"Kepulauan Riau" when:1d` | `https://news.google.com/rss/search?q=%22Kepulauan%20Riau%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Jambi | `"Jambi" when:1d` | `https://news.google.com/rss/search?q=%22Jambi%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sumatera Selatan | `"Sumatera Selatan" when:1d` | `https://news.google.com/rss/search?q=%22Sumatera%20Selatan%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kepulauan Bangka Belitung | `"Bangka Belitung" when:1d` | `https://news.google.com/rss/search?q=%22Bangka%20Belitung%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Bengkulu | `"Bengkulu" when:1d` | `https://news.google.com/rss/search?q=%22Bengkulu%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Lampung | `"Lampung" when:1d` | `https://news.google.com/rss/search?q=%22Lampung%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| DKI Jakarta | `"Jakarta" when:1d` | `https://news.google.com/rss/search?q=%22Jakarta%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Banten | `"Banten" when:1d` | `https://news.google.com/rss/search?q=%22Banten%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Jawa Barat | `"Jawa Barat" when:1d` | `https://news.google.com/rss/search?q=%22Jawa%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Jawa Tengah | `"Jawa Tengah" when:1d` | `https://news.google.com/rss/search?q=%22Jawa%20Tengah%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| DI Yogyakarta | `"Yogyakarta" when:1d` | `https://news.google.com/rss/search?q=%22Yogyakarta%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Jawa Timur | `"Jawa Timur" when:1d` | `https://news.google.com/rss/search?q=%22Jawa%20Timur%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Bali | `"Bali" when:1d` | `https://news.google.com/rss/search?q=%22Bali%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Nusa Tenggara Barat | `"Nusa Tenggara Barat" when:1d` | `https://news.google.com/rss/search?q=%22Nusa%20Tenggara%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Nusa Tenggara Timur | `"Nusa Tenggara Timur" when:1d` | `https://news.google.com/rss/search?q=%22Nusa%20Tenggara%20Timur%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kalimantan Barat | `"Kalimantan Barat" when:1d` | `https://news.google.com/rss/search?q=%22Kalimantan%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kalimantan Tengah | `"Kalimantan Tengah" when:1d` | `https://news.google.com/rss/search?q=%22Kalimantan%20Tengah%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kalimantan Selatan | `"Kalimantan Selatan" when:1d` | `https://news.google.com/rss/search?q=%22Kalimantan%20Selatan%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kalimantan Timur | `"Kalimantan Timur" when:1d` | `https://news.google.com/rss/search?q=%22Kalimantan%20Timur%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Kalimantan Utara | `"Kalimantan Utara" when:1d` | `https://news.google.com/rss/search?q=%22Kalimantan%20Utara%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sulawesi Utara | `"Sulawesi Utara" when:1d` | `https://news.google.com/rss/search?q=%22Sulawesi%20Utara%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Gorontalo | `"Gorontalo" when:1d` | `https://news.google.com/rss/search?q=%22Gorontalo%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sulawesi Tengah | `"Sulawesi Tengah" when:1d` | `https://news.google.com/rss/search?q=%22Sulawesi%20Tengah%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sulawesi Barat | `"Sulawesi Barat" when:1d` | `https://news.google.com/rss/search?q=%22Sulawesi%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sulawesi Selatan | `"Sulawesi Selatan" when:1d` | `https://news.google.com/rss/search?q=%22Sulawesi%20Selatan%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Sulawesi Tenggara | `"Sulawesi Tenggara" when:1d` | `https://news.google.com/rss/search?q=%22Sulawesi%20Tenggara%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Maluku | `"Maluku" when:1d` | `https://news.google.com/rss/search?q=%22Maluku%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Maluku Utara | `"Maluku Utara" when:1d` | `https://news.google.com/rss/search?q=%22Maluku%20Utara%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Papua | `"Papua" when:1d` | `https://news.google.com/rss/search?q=%22Papua%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Papua Barat | `"Papua Barat" when:1d` | `https://news.google.com/rss/search?q=%22Papua%20Barat%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Papua Barat Daya | `"Papua Barat Daya" when:1d` | `https://news.google.com/rss/search?q=%22Papua%20Barat%20Daya%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Papua Tengah | `"Papua Tengah" when:1d` | `https://news.google.com/rss/search?q=%22Papua%20Tengah%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Papua Pegunungan | `"Papua Pegunungan" when:1d` | `https://news.google.com/rss/search?q=%22Papua%20Pegunungan%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |
| Papua Selatan | `"Papua Selatan" when:1d` | `https://news.google.com/rss/search?q=%22Papua%20Selatan%22%20when%3A1d&hl=id&gl=ID&ceid=ID%3Aid` |

### 6.1 Alias POLDA / isu hukum yang direkomendasikan

Konfigurasi `watch_keywords` (dikelola admin, bukan regex permanen): `Polri`, `Kapolri`, `Divhumas Polri`, `Polda`, `Kapolda`, `Polres`, `Polresta`, `Polrestabes`, `Polsek`, `Propam`, `Brimob`, `Bareskrim`, `Polda Metro Jaya`, `unjuk rasa`, `demo`, `bentrok`, `kecelakaan`, `narkoba`, `pungli`, `kriminal`, `korupsi`, `pelayanan polisi`, `apresiasi polisi`, `kritik polisi`. Perlu kata kunci negatif dan review manusia untuk menghindari false positive.

Query prioritas khusus wilayah yang sudah teruji: `Polda Sulbar when:7d`, `"Polda Gorontalo" when:7d`; satwil Sulbar: Mamuju, Majene, Polewali Mandar, Mamasa, Pasangkayu, Mamuju Tengah; Gorontalo: Kota Gorontalo, Bone Bolango, Kabupaten Gorontalo, Gorontalo Utara, Boalemo, Pohuwato. Buat satu feed **per kombinasi** wilayah+isu, bukan satu query nasional raksasa.

## 7. Alamat yang gagal / jangan otomatis dimasukkan ke seed ACTIVE

| Alamat yang dicoba | Hasil uji 2026-11-01 | Alternatif |
|---|---|---|
| `https://www.suara.com/rss/news` | 404 | Google News: `site:suara.com` |
| `https://www.suara.com/rss` | 404 | Google News: `site:suara.com` |
| `https://www.viva.co.id/rss` | 404 | Google News: `site:viva.co.id` |
| `https://www.beritasatu.com/rss` | 404 | Google News: `site:beritasatu.com` |
| `https://www.idntimes.com/rss` | 404 | Google News: `site:idntimes.com` |
| `https://www.bisnis.com/rss` | 404 | Google News: `site:bisnis.com` |
| `https://www.kompas.com/rss` | 404 | Google News: `site:kompas.com` |
| `https://www.kompas.tv/rss` | 404 | Google News: `site:kompas.tv` (belum diuji khusus) |
| `https://www.jawapos.com/nasional/rss` | Konten halaman HTML / tidak terverifikasi RSS | Google News: `site:jawapos.com` (belum diuji khusus) |
| `https://rss.kontan.co.id/news/all` | Target tidak terjangkau pada jalur fetch ini | Uji ulang di server; Google News: `site:kontan.co.id` (belum diuji khusus) |
| `https://www.rri.co.id/rss` | Diblokir di jalur pengujian (bot_blocked) | Uji akses resmi atau Google News |
| `https://rmol.id/feed` | Diblokir di jalur pengujian (bot_blocked) | Uji akses resmi atau Google News |
| `https://www.inews.id/rss` | Diblokir di jalur pengujian (bot_blocked) | Google News: `site:inews.id` |

`bot_blocked` atau `target_unreachable` **bukan kepastian situs tidak memiliki RSS**; itu hanya kegagalan jalur uji saat ini. Jangan melewati pembatasan akses. Gunakan API/izin resmi bila diperlukan.

## 8. Kontrak ingestion untuk agent / developer

### 8.1 Skema `feed_sources` yang disarankan

```yaml
id: N001
publisher: ANTARA
source_type: publisher_rss  # publisher_rss | google_news_rss
channel: Terkini
scope: national         # national | province | issue | domain
province: null
url: https://www.antaranews.com/rss/terkini.xml
validation: LIVE_OK
enable_after_worker_check: true
enabled: false         # default FALSE sampai lulus uji dari worker produksi
priority: P0
poll_minutes: 10
last_checked_at: null
last_status_code: null
last_success_at: null
last_item_at: null
consecutive_failures: 0
etag: null
last_modified: null
```

### 8.2 Perilaku worker

1. Ambil **hanya alamat yang telah di-whitelist oleh admin**; larang URL yang mengarah ke jaringan private/loopback (SSRF), batasi redirect dan ukuran unduhan. Gunakan HTTPS dan timeout 10–20 detik; ukuran XML misalnya maksimal 5 MB (dapat dikonfigurasi).
2. Request memakai User-Agent deskriptif + alamat kontak, `Accept: application/rss+xml, application/atom+xml, application/xml, text/xml`, conditional GET `If-None-Match`/`If-Modified-Since`; patuhi HTTP `304`, `429`, dan `Retry-After`. Jangan menyamar atau menghindari proteksi anti-bot.
3. Parse dengan **XML parser aman** (nonaktifkan DTD/XXE dan external entities); dukung RSS 2.0/Atom 1.0. Jangan mengambil JSON/HTML dan memberi label RSS tanpa pemeriksaan MIME/struktur.
4. Normalisasi: `source_id`, `source_type`, `source_domain`, `source_name`, `title`, `summary`, `link_as_received`, `canonical_url`, `guid`, `published_at_utc`, `published_at_wib`, `fetched_at_utc`, `image_url`, `language`, `province_candidates`, `topic_tags`, `raw_metadata` (hanya data yang diizinkan). Simpan waktu WITA/WIT di tampilan sesuai kebutuhan, tetapi penyimpanan utama UTC.
5. Google News: simpan `google_news_url`, judul, publisher/source dan GUID. URL Google News **tidak selalu tautan asli penerbit**. Bila tidak ada URL asli yang sah/tersedia, isi `canonical_url=null` dan gunakan URL Google; jangan mengarang link atau bypass mekanisme Google.
6. Sanitasi HTML di `description/content:encoded` agar tidak terjadi XSS. Jangan menampilkan HTML mentah dalam dashboard; data yang berhak cipta cukup *headline*, ringkasan pendek, sumber, waktu, dan tautan ke asal.
7. Deduplikasi berlapis: (a) `source_id + guid`, (b) URL canonical ternormalisasi tanpa `utm_*`/tracking, (c) hash judul+domain+tanggal, (d) similaritas judul/isi pendek untuk mengelompokkan berita se-isu lintas media. Jangan menyatukan artikel berbeda hanya karena topik sama.
8. Filter zona waktu dan ketepatan: klasifikasi Polda/Satwil harus memakai lokasi peristiwa atau yurisdiksi yang dipastikan, **bukan** semata lokasi media atau lokasi tokoh. Jika ragu, status `needs_review`. Sentimen/netralitas tidak boleh disimpulkan hanya dari headline.
9. Buat health monitor: `HTTP_OK`, `VALID_RSS`, `HAS_ITEMS`, `FRESH`, `STALE`, `RATE_LIMITED`, `BLOCKED`, `BROKEN`, `NEEDS_REVIEW`. RSS bisa valid tetapi artikel terakhir telah lama; deteksi kedua aspek secara terpisah.
10. Jalankan koneksi terbatas dan *jitter*: P0 10–15 menit, P1 15–30 menit, P2 30–60 menit, Google News 20–40 menit atau sesuai kebijakan situs; backoff eksponensial bila gagal. Ini nilai awal yang harus dituning sesuai kondisi server & izin akses.

### 8.3 Pseudocode validasi feed pada deploy (Python 3 / feedparser)

```python
import requests, feedparser
from datetime import datetime, timezone

def validate_feed(url: str) -> dict:
    # HARUS dipanggil hanya untuk URL HTTPS yang telah di-whitelist operator
    r = requests.get(url, headers={
        "User-Agent": "SIMOD-Enterprise-RSS/1.0 (admin-contact@example.org)",
        "Accept": "application/rss+xml, application/atom+xml, application/xml, text/xml",
    }, timeout=15, allow_redirects=False)
    r.raise_for_status()
    if len(r.content) > 5_000_000: raise ValueError("feed too large")
    feed = feedparser.parse(r.content)
    if not (feed.get("version") and (feed.feed or feed.entries)):
        raise ValueError("not a recognized RSS/Atom feed")
    if feed.get("bozo") and not feed.entries: raise ValueError("malformed feed")
    return {"url":url, "http":r.status_code, "format":feed.version,
            "items":len(feed.entries), "first_title":feed.entries[0].get("title") if feed.entries else None,
            "checked_at":datetime.now(timezone.utc).isoformat()}
```

**Keamanan kode contoh:** kode di atas adalah contoh minimum. Implementasi produksi perlu validasi DNS/IP SSRF sebelum dan sesudah redirect, pemeriksaan TLS, nonaktifkan DTD/XXE dalam parser yang dipakai, penyimpanan kredensial yang aman, kebijakan 304/429, dan rate limiting. Jangan memasukkan `example.org` sebagai kontak nyata sebelum diganti.

### 8.4 Generator Google News (Python, implementasi dapat dipindah ke Laravel/PHP)

```python
from urllib.parse import quote
def google_news_rss(query, days=1):
    q = f"{query} when:{days}d"
    return "https://news.google.com/rss/search?q=" + quote(q, safe="") + "&hl=id&gl=ID&ceid=ID%3Aid"

print(google_news_rss("Polri OR Polda OR Polres"))
print(google_news_rss("site:kompas.com"))
print(google_news_rss("\"Polda Sulbar\""))
```

### 8.5 Rancangan integrasi Laravel / Redis / PostgreSQL

**Tabel**: `feed_sources`, `feed_fetch_runs`, `news_articles`, `news_article_sources`, `news_clusters`, `watch_queries`, `source_health_events`, `geo_aliases`, `classification_reviews`. **Job**: `PollFeedSource` (Redis queue), `NormalizeFeedItem`, `ResolvePublisherLink` (hanya jika legal dan layak), `DeduplicateNewsArticle`, `ClassifyNewsGeography`, `AssignIssueCluster`, `UpdateSourceHealth`. **Scheduler**: pilih sumber yang `next_poll_at <= now`, enqueue secara bertahap; hindari semua feed diminta pada detik sama.

**MVP acceptance criteria:** minimal 20 feed langsung berstatus HEALTHY dari server SIMOD; minimal 5 feed Google News berjalan; 38 wilayah tersedia dalam konfigurasi query meski tak semua aktif; masing-masing berita punya judul, link valid, sumber, published_at, fetched_at; duplikasi punya cluster; kegagalan 404/403/429 bisa dipantau; UI membedakan artikel asli, agregasi Google News, dan sumber rilis resmi.

## 9. Dokumen referensi / asal pola endpoint

- ANTARA Nasional, direktori RSS: https://www.antaranews.com/rss
- ANTARA Makassar, direktori RSS: https://makassar.antaranews.com/rss/
- ANTARA Gorontalo, direktori RSS: https://gorontalo.antaranews.com/rss/
- ANTARA Papua, direktori RSS: https://papua.antaranews.com/rss/
- ANTARA Jabar, direktori RSS: https://jabar.antaranews.com/rss/
- CNA Indonesia, direktori RSS dan ketentuan penggunaan: https://www.cna.id/rss
- Google News RSS/pencarian, referensi teknis eksternal: https://feeder.co/id/knowledge-base/rss-feed-creation/google-news-rss-feeds/
- Contoh feed yang dipantau oleh layanan pembaca RSS: https://feeder.co/discover/66e8bd1883/news-detik-com-berita dan https://feeder.co/discover/ea2bf9e1c3/cnnindonesia-com-nasional

## 10. Ringkasan import

- RSS langsung nasional `LIVE_OK`: **25**
- RSS langsung ANTARA daerah `LIVE_OK`: **26**
- RSS langsung Tribun daerah `LIVE_OK`: **7**
- Total RSS langsung yang tercatat `LIVE_OK`: **58**
- Google News per-domain `GOOGLE_LIVE_OK`: **14**
- Google News query khusus `GOOGLE_LIVE_OK`: **7**, ditambah homepage Indonesia (1)
- Provinsi yang tersedia lewat generator query `GOOGLE_TEMPLATE`: **38**; tidak diklaim sudah diuji individu.

**Urutan import:** (1) seed semua RSS langsung dengan `enabled=false`, (2) worker melakukan pemeriksaan nyata, (3) enable yang HEALTHY, (4) tambah Google News keyword/domain, (5) enable provinsi bertahap, (6) periksa kualitas cakupan nasional dan sesuaikan kuota/interval. **Jangan menandai LIVE_OK sebagai jaminan koneksi dari lingkungan produksi.**
