# 36. Peringatan Otomatis, Pantau Berkala Akun & Pengamanan Rahasia AI

Tanggal: 7 November 2026.

## 1. Peringatan otomatis (`SocialAlertService`, tabel `social_alerts`)
Dibuat dari data nyata akun pantauan (doc 35), satu peringatan per (Polda, tipe, unggahan), sehingga tidak berulang saat sinkron ulang.

| Tipe | Pemicu | Tingkat |
|:---|:---|:---|
| `kamtibmas` | Unggahan **baru** (terbit ≤ 72 jam, belum ada di snapshot sebelumnya) memuat kata kunci kamtibmas/Polri (`PublicTrendsService::KAMTIBMAS_KEYWORDS`) | tinggi |
| `topik` | Unggahan baru memuat kata kunci **Topik Atensi aktif** Polda itu | tinggi |
| `lonjakan` | Keterlibatan unggahan baru ≥ 2× median akun itu (`InstagramHighlights`) | sedang |
| `komentar_negatif` | Ringkasan komentar ≥ 10 komentar asli dan ≥ 40% bernada negatif | tinggi |

- Unggahan lama yang baru pertama kali diambil tidak memicu peringatan.
- **Lonceng notifikasi** di header kini berupa panel: antrean verifikasi laporan + peringatan media sosial tenant aktif (hanya bagi pemilik `social_intelligence_view`). Status baca per pengguna (`users.alerts_seen_at`, "Tandai sudah dibaca").
- Rute: `GET /social-intelligence/alerts`, `POST /social-intelligence/alerts/seen` (`module:social_intelligence_view`).

## 2. Pantau berkala akun prioritas
- Sakelar **"Pantau otomatis"** per akun di tab Radar KOL (`POST /social-intelligence/accounts/{id}/auto`, `module:social_intelligence_sync`, tercatat di Audit Log `social.auto_monitor`).
- Perintah `social:accounts-refresh` (dijadwalkan tiap jam) menyinkronkan akun berpantau otomatis yang terakhir disinkronkan ≥ `SIMOD_SOCIAL_AUTO_INTERVAL_HOURS` jam (bawaan 6), yang paling lama lebih dulu. Untuk satu unggahan baru yang menonjol per akun, komentar ikut diambil agar peringatan komentar negatif berjalan.
- **Batas biaya**: maksimal `SIMOD_APIFY_AUTO_RUNS` run otomatis per hari (bawaan 20), bagian dari rem harian total `SIMOD_APIFY_DAILY_RUNS` (60), sehingga selalu tersisa jatah untuk pemakaian manual.

## 3. Pengamanan rahasia di Mesin Intelijen AI
Temuan: halaman `/settings/ai` mengirim **token Apify dan kunci Gemini utuh** ke browser (`raw_api_keys_text`, `api_keys_pool`, `keys_detail[].raw`, `apify_api_token`), melanggar AGENTS §3.3.6. Selain itu, menyimpan form Apify dengan kotak token kosong **menghapus semua token**.

Sekarang:
- Browser hanya menerima jumlah dan bentuk tersamar. Kotak isian dipakai untuk kunci/token **baru** dengan pilihan **Tambahkan ke daftar** atau **Ganti seluruh daftar**. Token Apify dihapus hanya lewat centang eksplisit "Hapus semua token tersimpan".
- "Uji Koneksi Token" tanpa isian menguji token tersimpan di server (`ApifyClient::health`): akun, paket, pemakaian bulan ini, status istirahat, dengan token tersamar.
- Pengaturan "Otomasi Aktif di Latar Belakang" dan "Frekuensi crawl" Apify **dihapus** karena tidak pernah ditegakkan penjadwal. Penggantinya adalah pantau otomatis per akun (§2). Kartu ringkasan kini menampilkan pengambilan hari ini terhadap rem harian.
- Daftar akun bawaan berisi tebakan (`@divisihumaspri`, …) dihapus.

## 4. Pengujian
`SocialAlertsAndSecretsTest`: peringatan unggahan baru (kamtibmas/topik/lonjakan) sekali saja dan tidak untuk arsip lama; ambang komentar negatif; hitungan belum dibaca per pengguna & tenant; pantau otomatis jatuh tempo + batas run otomatis; sakelar terkunci tenant; halaman AI tanpa kunci/token mentah; token Apify tetap/ditambah/dihapus; mode tambah kunci Gemini.
