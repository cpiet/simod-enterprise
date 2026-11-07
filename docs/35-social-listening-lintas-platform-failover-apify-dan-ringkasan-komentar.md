# 35. Social Listening Lintas Platform, Failover Apify & Ringkasan Komentar

Tanggal: 7 November 2026. Lingkup: Radar Media Sosial → tab Radar KOL & Akun Kritis.

## 1. Failover token Apify (`App\Services\ApifyClient`)
- Token diambil dari pengaturan Polda → pengaturan mana pun yang berisi token → `APIFY_API_TOKEN`; beberapa token dipisah baris/koma (diisi admin di Mesin Intelijen AI).
- Token dicoba **berurutan**. Ditolak (401/403), saldo/batas habis (402 atau pesan "usage … limit"), atau dibatasi (429) → token berikutnya. Token yang saldonya habis **diistirahatkan 60 menit** sehingga tidak dicoba terus.
- Galat Actor/input (400/404/5xx) tidak di-failover (token lain tidak menolong).
- **Rem biaya**: maksimum `SIMOD_APIFY_DAILY_RUNS` run per hari (bawaan 60; 0 = tanpa batas). Setiap run memakai `maxItems` dan `maxTotalChargeUsd=0.5`. Beberapa Actor mensyaratkan minimal US$0,50 per run; biaya nyata untuk 12 item sekitar US$0,01–0,05.
- Temuan sebelumnya: pantauan Instagram hanya memakai token pertama (tidak ada failover). Kini semua pantau akun & ringkasan komentar lewat `ApifyClient`.
- Verifikasi produksi 7 Nov 2026: 3 token valid (3 akun Apify FREE berbeda, masing-masing US$5/bulan).

## 2. Pantau Akun lintas platform (`SocialAccountMonitoringService`)
Tabel `monitored_instagram_accounts` diganti nama menjadi `monitored_social_accounts` + kolom `platform` & `profile` (migrasi `2026_11_12_090000`). Unik: `(polda_id, platform, username)`. Model `MonitoredSocialAccount`.

| Platform | Actor | Yang disimpan | Catatan dari uji nyata |
|:---|:---|:---|:---|
| Instagram | `apify/instagram-scraper` | suka, komentar, tayang (video), tagar, thumbnail | seperti sebelumnya |
| TikTok | `clockworks/tiktok-scraper` | tayang, suka, komentar, bagikan, tagar, sampul, pengikut | akun privat/kosong → item `note`; pesan jujur |
| X | `apidojo/tweet-scraper` | suka, balasan, retweet, tayang, media, pengikut | retweet tidak dihitung; uji dengan @DivHumas_Polri berhasil |
| YouTube | `streamers/youtube-scraper` | tayang, suka, jumlah komentar, thumbnail, pelanggan | input = handle kanal; Shorts & siaran langsung tidak diambil |
| Facebook | `apify/facebook-posts-scraper` | suka, bagikan, tayang video, thumbnail | **hanya Halaman publik**; dibatasi → `not_available`, pesan jujur |

- Hanya unggahan **milik akun yang diminta** (pencocokan username pemilik) dengan URL https host resmi platform yang disimpan. Gambar hanya dari CDN resmi. Metrik yang tidak disediakan = `null` ("—").
- Username divalidasi per platform; tautan profil utuh boleh ditempel dan dinormalkan.
- UI: pilih platform + username, saringan platform pada daftar akun, label platform & pengikut per akun, tautan profil sesuai platform. Tombol "Pantau akun" di Direktori KOL kini untuk semua platform yang didukung.
- Rute: `POST /social-intelligence/accounts`, `POST …/accounts/{id}/sync`, `DELETE …/accounts/{id}` (`module:social_intelligence_sync`, throttle).

## 3. Ringkasan Komentar (`CommentDigestService`, tabel `social_comment_digests`)
- Tombol "Ringkasan komentar" pada tiap unggahan akun pantauan mengambil hingga **50 komentar asli**:
  Instagram `apify/instagram-comment-scraper`, TikTok `clockworks/tiktok-comments-scraper`, YouTube `streamers/youtube-comments-scraper`, X balasan via `apidojo/tweet-scraper` (`conversation_id:<id>`, tweet asli dikecualikan, @mention di awal dibuang), Facebook `apify/facebook-comments-scraper`.
- Hanya URL yang ada di snapshot akun pantauan tenant aktif yang dapat diambil (mencegah pengambilan sembarang URL berbayar).
- Setiap komentar diberi sentimen dengan aturan tunggal `NewsSentiment::explainSocial` (alasan disimpan); ditampilkan batang proporsi positif/netral/negatif, kata yang sering disebut (≥ 2 kali), dan daftar komentar (negatif di atas).
- **Ringkasan opini SADEWA** (`GeminiService::summarizeComments`) atas permintaan: ringkasan opini, isu/keluhan dengan rujukan [k3], potensi kerawanan (misinformasi/provokasi/ajakan aksi; "Tidak terlihat" bila tidak ada), dan saran respons kehumasan. Melewati `AiQuota` (fitur `comment_summary`); tidak diulang bila komentar belum diambil ulang (kecuali "Ringkas ulang"). Teks AI dirender lewat `resources/js/utils/safeMarkdown.js` (HTML mentah dilepas), dipakai bersama SADEWA.
- Rute: `POST /social-intelligence/comments` (`module:social_intelligence_sync`), `POST /social-intelligence/comments/{id}/summarize` (`module:social_intelligence_view`).

## 4. Pengujian
`SocialListeningTest` (failover & istirahat token, rem harian, normalisasi 4 platform dengan data berformat keluaran Actor nyata, akun privat/halaman tidak publik, validasi username & tautan, komentar X tanpa tweet asli, tolak URL asing, ringkasan AI lewat kuota & tidak diulang), `InstagramAccountMonitoringTest`, `InstagramHighlightsTest`, `NavigationSweepTest`.

## 5. Catatan migrasi
MySQL menolak menghapus indeks unik lama selama dipakai foreign key `polda_id`; migrasi membuat indeks baru lebih dulu dan setiap langkah dijaga agar aman dijalankan ulang. SQLite (test) tidak menangkap masalah ini, sehingga migrasi diuji juga di MySQL lokal.
