# 32. Tren Pencarian, SADEWA Cari Internet, dan Pantauan Banyak KOL

Tanggal: 6 November 2026. Lingkup: Radar Media Sosial (tab Tren dan tab KOL), SADEWA AI, penjadwal.

## 1. Tab "Tren Tagar & Kerawanan Siber" dirapikan, data karangan dihapus

Temuan audit sebelum perubahan:

| Bagian | Masalah | Sekarang |
|:---|:---|:---|
| Tagar viral | `supplementFallbackHashtags()` selalu mencampur tagar buatan (#PolriPresisi 148.500, #AntreanSolarMamuju, dll.) ke data asli, tanpa syarat `SIMOD_SOCIAL_SYNTHETIC` | Dihapus. Hanya tagar dari unggahan tersimpan; kosong → "Belum ada aktivitas tagar". |
| Jam puncak | Jendela tetap "11.30–13.30" dan "19.00–22.00", persentase dipaksa 25–45% / 38–65%, bawaan 34%/48%/70% | Dihitung dari jam unggahan nyata (zona waktu tenant). Jendela 2 jam terpadat, saran rilis 1 jam sebelumnya. Minimal 20 unggahan bertanggal; di bawah itu "Belum cukup data". |
| Awan kata | Bila kosong diisi 12 kata buatan | Kosong → "Belum ada data". |
| Tren publik "Apify" | Tombol Refresh tidak pernah memanggil Apify; hanya "belum tersedia" atau 15 tren contoh statis | Diganti **Tren Pencarian Google Indonesia** (nyata). |
| Pemilih "Lensa 01/02" | Dua kartu mirip menu, satu kosong | Dihapus; satu halaman berurutan dengan penjelasan dua sumber. |

### Tren Pencarian Google Indonesia
- Sumber: RSS resmi Google Trends "Sedang Tren" (`https://trends.google.com/trending/rss?geo=ID`), gratis, tanpa kunci API. Data nasional, sama untuk semua tenant.
- `App\Services\PublicTrendsService`: `refresh()` menarik dan menyimpan ke cache `simod.public_trends`; `cached()` dipakai halaman (tidak pernah memanggil jaringan saat halaman dibuka). Bila gagal, data terakhir tetap tampil dengan keterangan galat.
- Per tren: peringkat, perkiraan volume pencarian (`approx_traffic`), waktu mulai naik, hingga 3 berita terkait (hanya tautan http/https), tautan Google Trends.
- **Penanda kamtibmas** = kecocokan kata utuh dari `PublicTrendsService::KAMTIBMAS_KEYWORDS` pada judul tren + judul berita. Bukan penilaian AI; kata yang cocok ditampilkan. Kata yang terlalu umum (mis. "hilang") tidak dipakai karena memicu alarm palsu (contoh nyata: berita sepak bola).
- Aksi: "Cari di Radar", "Jadikan laporan isu" (hanya bila izin `issues_create`), tautan Google Trends.
- Pembaruan: otomatis tiap jam (`social:trends-refresh`) dan tombol "Perbarui sekarang" (izin `social_intelligence_sync`).

### Grafik "Tren Percakapan 7 Hari"
- Angka diverifikasi terhadap data produksi (6 Nov 2026): cocok per hari dan per sentimen. Batang abu-abu = semua unggahan pada hari itu berlabel netral (bukan galat).
- Tanggal kini dikelompokkan per **zona waktu tenant** (WIB/WITA/WIT), bukan UTC. Klik hari (`post_date`) memakai rentang tanggal lokal yang sama sehingga jumlah grafik = jumlah daftar.

## 2. Statistik medsos bergerak sendiri: sinkron lapis gratis terjadwal
- `php artisan social:sync-free [--polda=KODE]` menjalankan `SocialSyndicationService::syncRadarForPolda()` (sindikasi Google News) untuk setiap Polda aktif. **Tidak pernah** memanggil Apify berbayar; Apify tetap manual lewat "Sinkron & Analisis".
- Dijadwalkan tiap 30 menit; dimatikan dengan `SIMOD_SOCIAL_AUTO_SYNC=false`.

## 3. SADEWA mencari di internet bila data SIMOD kosong
Alur (`SadewaController::chat`):
1. SADEWA menjawab dari data SIMOD seperti biasa. Bila data sama sekali tidak memuat jawaban dan pertanyaan menyangkut informasi publik, model membalas penanda `[[CARI_WEB]]` (`GeminiService::webSearchMarker()`).
2. Server lalu memanggil `GeminiService::searchWebSadewa()` dengan alat `google_search` (Gemini grounding).
3. **Kerahasiaan:** panggilan kedua hanya berisi pertanyaan pengguna dan maks. 2 pertanyaan pengguna sebelumnya (untuk rujukan "dia"/"kasus itu"). Data internal (isu, laporan, unggahan tersimpan) tidak pernah dikirim ke pencarian. Diuji di `SadewaWebSearchTest`.
4. Jawaban diberi label "Dari internet, di luar data SIMOD. Periksa sumbernya." Kutipan `[W1]..` disisipkan dari `groundingSupports`, dan daftar sumber berasal dari `groundingChunks` (hanya http/https).
5. Kuota: pencarian internet dicatat sebagai fitur `chat_web` di `ai_usage_logs` dan melewati `AiQuota::denial()`; bila batas harian habis, SADEWA menjelaskan bahwa pencarian tidak dijalankan.
6. Dapat dimatikan admin: kolom `ai_settings.web_search_enabled` (migrasi `2026_11_10_090000`, bawaan aktif), kotak centang di Keamanan & Pemeliharaan → Mesin Intelijen AI.
7. Pertanyaan internal yang datanya kosong tetap dijawab jujur "datanya belum ada" (tidak dicari di internet).

## 4. Pantau Akun Instagram untuk banyak KOL
- Strip ringkasan: akun dipantau, unggahan 7 hari, unggahan menonjol, akun dengan keterlibatan tertinggi.
- Satu baris ringkas per akun (tertutup secara bawaan bila akun > 1): statistik inti, lencana "Baru 24 jam", "N menonjol", "Sinkron gagal". Klik untuk membuka; menonjol tampil di atas, 4 unggahan pertama, lalu "Lihat semua".
- Cari akun, urutkan (terbaru, paling banyak menonjol, keterlibatan, nama), dan saring "Hanya yang menonjol".
- **Unggahan menonjol** (`App\Support\InstagramHighlights`, dihitung di server): keterlibatan (suka + komentar) ≥ 2× median akun itu sendiri (perlu ≥ 4 unggahan bermetrik), atau keterangan unggahan memuat kata kunci kamtibmas (daftar yang sama dengan tren). Alasan penanda ditampilkan pada kartu.

## 5. Penjadwal (bertambah)
| Tugas | Jadwal | Catatan |
|:---|:---|:---|
| `social:sync-free` | tiap 30 menit | lapis gratis; `SIMOD_SOCIAL_AUTO_SYNC` |
| `social:trends-refresh` | tiap jam | Google Trends |

## 6. Pengujian
`PublicTrendsTest`, `SocialFreeSyncCommandTest`, `SadewaWebSearchTest`, `InstagramHighlightsTest`, penyesuaian `SocialIntelligenceTest` dan `SchedulerStatusTest` (jumlah tugas mengikuti `ScheduledTasks::definitions()`).

## 7. Deployment
`php artisan migrate --force` (kolom `web_search_enabled`), `php artisan optimize:clear`, lalu `php artisan social:trends-refresh` sekali agar tren langsung tampil.
