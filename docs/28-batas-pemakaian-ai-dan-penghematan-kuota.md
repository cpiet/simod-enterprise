# 🧮 28. Batas Pemakaian AI & Penghematan Kuota

Jatah Gemini (terutama paket gratis: Flash Lite 15 RPM / 500 RPD, 3.8 Flash 5 RPM / 20 RPD; jatah berlaku **per project Google**, bukan per API Key) dipakai bersama seluruh pengguna. Dokumen ini mencatat cara aplikasi menjaganya.

## 1. Batas per pengguna per hari
- `ai_settings.daily_user_limit` (default **40**, `0` = tanpa batas), diatur di **Pengaturan Mesin AI → 2. Batas Pemakaian**.
- 1 permintaan = 1 aksi pengguna (analisis media/medsos/topik atau 1 pesan chat SADEWA) yang **berhasil** dijawab. Aksi yang dilewati karena data sama tidak dihitung.
- Ditegakkan di server oleh [`AiQuota`](../app/Services/AiQuota.php) pada `SadewaController::chat`, `MediaIntelligenceController::analyzeGemini` dan `analyzeTopicAi`, serta `SocialIntelligenceController::analyzeAI`. Melewati batas → HTTP 429 dengan pesan jelas.
- SADEWA menampilkan **"Sisa N dari M pertanyaan AI hari ini"**; input dinonaktifkan saat habis.
- Pemakaian dicatat di `ai_usage_logs` (`ok | skipped | quota | error`); ringkasan hari ini + pengguna terbanyak tampil di Pengaturan AI.

## 2. Saat kuota Google habis
Bila semua model membalas 429, `AiQuota` menahan panggilan baru **10 menit** (cache per Polda) sehingga sisa jatah per menit tidak terbuang; pengguna melihat "Jatah AI dari Google sedang habis… reset sekitar tengah malam waktu Pasifik atau aktifkan penagihan". Galat Google diringkas lewat `friendlyAiError()`.

## 3. Hemat token: data sama tidak dianalisis ulang
| Analisis | Kunci "data sama" | Perilaku |
|:---|:---|:---|
| Media Online | sidik jari 30 berita terbaru (judul + tautan) + templat prompt → `media_intelligence_reports.source_hash` | Sama dengan laporan terakhir → pakai laporan lama, **0 panggilan AI** |
| Media Sosial | sidik jari 40 postingan (akun + isi) → `social_intelligence_reports.source_hash` | idem |
| Topik Atensi | tidak ada berita topik yang lebih baru dari `ai_analyzed_at` | idem |

Tombol **Analisis Ulang** (Radar Media Online) memakai `?force=1` untuk memaksa analisis. "Perbarui Briefing" menampilkan jalur yang dilewati sebagai *dilewati* dengan alasan.

## 4. Pengaturan otomasi AI dihapus
Mode penjadwalan (setelah RSS / jam dinas / interval), daftar jam, dan toggle "auto analisis" di Pengaturan AI hanya tersimpan di basis data; **tidak ada proses yang menjalankannya** (analisis AI hanya terpanggil dari klik pengguna). Bagian itu dihapus dan diganti panel batas pemakaian. Kolom lama dibiarkan di basis data (tidak berbahaya). *Catatan:* otomasi sinkron **RSS** (`rss:sync --scheduled`) adalah fitur terpisah dan tetap berfungsi.

## 5. Pengujian
`AiQuotaAndSavingsTest` (9 tes): analisis dilewati bila data sama dan jalan lagi bila ada data baru, `force`, medsos, topik, batas per pengguna + sisa jatah + pengguna lain tak terdampak, `0` = tanpa batas, penahanan saat kuota habis tanpa memanggil Google lagi, halaman Pengaturan AI, dan penyimpanan batas. Total **151 tests (905 assertions)**.

## 6. Belum dikerjakan / temuan
- Pengaturan **Apify** ("Aktifkan Sinkronisasi Background Otomatis", frekuensi) juga tampaknya tidak dijalankan penjadwal mana pun (`routes/console.php` hanya memuat RSS, prune, dan backup). Perlu diputuskan: dibuang atau dibangun.
- Privasi: input di paket gratis Gemini dapat dipakai Google untuk peningkatan produk (periksa ketentuan resmi); untuk data operasional sebaiknya penagihan aktif.
