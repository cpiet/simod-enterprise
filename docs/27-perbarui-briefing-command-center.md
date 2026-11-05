# 🔄 27. Perbarui Briefing Command Center

Sebelumnya "Sinkron & Analisis" hanya ada di halaman Radar Media Online dan Radar Media Sosial dan berjalan sendiri-sendiri; Command Center hanya menampilkan laporan terakhir. Kini Command Center punya satu aksi **Perbarui Briefing** pada kartu *Briefing Intelijen Pimpinan*.

## 1. Perilaku
Komponen [`BriefingRefresh.vue`](../resources/js/Components/BriefingRefresh.vue) menampilkan keterangan kesegaran per sumber (*Media Online: 12 mnt lalu · Media Sosial: 3 jam lalu*; kuning bila > 6 jam atau belum ada) dan tombol aksi. Dua jalur berjalan **paralel** dan masing-masing independen:

| Jalur | Langkah (endpoint yang sudah ada) | Izin modul |
|:---|:---|:---|
| Media Online | `POST /media-intelligence/analyze-gemini?sync=1` (tarik RSS lalu analisis) | `media_intelligence_ai` |
| Media Sosial | `POST /social-intelligence/sync` (`economy=1`) lalu `POST /social-intelligence/analyze-ai` | `social_intelligence_sync` |

- **Progres per jalur**: berjalan, selesai, dilewati, atau gagal beserta pesannya. Kegagalan satu jalur (mis. kuota Apify habis) tidak membatalkan jalur lain.
- **Sesuai hak akses**: jalur tanpa izin tidak ditampilkan dan tidak dijalankan; pengguna tanpa izin apa pun hanya melihat keterangan kesegaran (tanpa tombol).
- **Anti-pemborosan**: jalur yang laporannya diperbarui < 5 menit dilewati; bila semua jalur segar, tombol nonaktif ("Baru diperbarui"). Endpoint sinkron/analisis diberi `throttle:10,1`.
- Setelah selesai, halaman memuat ulang data (`router.reload`), ringkasan hasil tetap tampil sampai ditutup.
- Tombol "Sinkron & Analisis" di halaman Radar Media Online/Sosial **tetap ada** untuk memperbarui satu sumber.

## 2. Mode hemat Apify
`SocialIntelligenceController::sync` menerima `economy=1`: Apify berbayar hanya dipanggil bila sindikasi publik gratis tidak menghasilkan postingan **dan** data medsos terbaru wilayah itu sudah lebih tua dari 60 menit. Pemanggilan dari halaman Radar Media Sosial (tanpa `economy`) berperilaku seperti semula. Endpoint ini kini juga membalas JSON (`success`, `new_posts`, `apify_used`, `message`) bila diminta `Accept: application/json`; tanpa itu tetap redirect + flash.

## 3. Pembersihan teks bawaan
Dashboard tidak lagi menampilkan kalimat karangan saat laporan kosong ("terpantau kondusif…", "29/33 portal", "SITUASI KONDUSIF & TERKENDALI"). Diganti pesan jujur: *Belum ada analisis… Tekan "Perbarui Briefing"*.

## 4. Galat kuota Gemini (429)
Saat dicoba, Perbarui Briefing menampilkan galat panjang "gagal pada seluruh pool API Key… Quota exceeded… limit: 0". Penyebabnya: model terpilih (`gemini-3.1-pro`) tidak tersedia di paket gratis, dan pada 429 kode lama langsung meninggalkan kunci tanpa mencoba model lain yang sebenarnya masih diizinkan. Perbaikan di `GeminiService` (analisis media dan medsos): **429 = coba model berikutnya pada kunci yang sama**; hanya 403 (kunci ditolak) yang langsung pindah kunci. Bila semua model tetap terbatas, pesan diringkas lewat `friendlyAiError()` (detail teknis hanya di log). Saran operasional: pilih model ringan (mis. flash-lite) di Pengaturan Mesin AI atau pakai API Key dengan penagihan aktif.

### 4.1. Audit Pengaturan Mesin AI (`/settings/ai`)
Diverifikasi dengan tes: **model pilihan memang menjadi panggilan pertama** (media, medsos, chat SADEWA). Temuan dan perbaikan:
| Temuan | Perbaikan |
|:---|:---|
| Slider **suhu** tersimpan tetapi tidak pernah dipakai (angka tetap 0.1/0.2/0.4 di kode). | `GeminiService::temperatureFor()` membaca suhu per Polda (lalu global); tugas berformat JSON dibatasi maks. **0.4**, chat memakai nilai slider. |
| Daftar cadangan 8 model (termasuk Pro, `1.5`, `2.0` yang tak tersedia/ tanpa jatah gratis) memboroskan jatah per menit dan memunculkan galat Pro. | `modelChain()`: model pilihan → `gemini-3.5-flash-lite` → `gemini-3.8-flash`. Pro hanya dipakai bila dipilih admin. |
| Galat 503 (server Google sibuk) langsung pindah model. | Dicoba ulang 1x pada model yang sama (jeda 2 detik). |
| Dropdown menyesatkan ("Kuota Bebas", Pro tanpa peringatan). | Label jujur: 3.8 Flash ≈ 20 permintaan/hari di paket gratis; Pro **wajib penagihan aktif**; keterangan fallback diperbarui. |

Catatan paket gratis (dibaca dari AI Studio): 3.5 Flash Lite 15 RPM / 500 RPD, 3.8 Flash 5 RPM / 20 RPD, model Pro tidak punya jatah. Langganan Google AI Pro (aplikasi Gemini) bukan penagihan API.

## 5. Pengujian
`CommandCenterRefreshTest` (5 tes) dan `AiModelSelectionTest` (6 tes: model pilihan, suhu, rantai cadangan, retry 503) dan `GeminiQuotaFallbackTest` (2 tes: fallback model saat 429, pesan ringkas bila semua terbatas). Rinciannya `CommandCenterRefreshTest`: mode hemat melewati Apify saat data segar, memakai Apify saat data basi, sinkron biasa tetap memakai Apify, sinkron butuh izin, dan Dashboard memuat waktu laporan. Total **151 tests (905 assertions)**.

## 6. Belum dikerjakan
- Penjadwalan otomatis (RSS otomatis + sinkron medsos otomatis + analisis AI setelahnya) agar Command Center selalu segar tanpa klik.
- Pengecekan visual di browser belum dilakukan.
