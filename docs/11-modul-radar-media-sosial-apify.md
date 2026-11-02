# 11 — Modul Radar Media Sosial (Cyber Patrol & Social Listening) Terintegrasi Apify

Modul **Radar Media Sosial** (`/social-intelligence`) adalah subsistem intelijen siber terpadu pada **SIMOD Enterprise** yang bertugas memantau percakapan publik, sentimen netizen, tren tagar, dan radar tokoh kritis di 4 platform utama: **𝕏 (Twitter), TikTok, Instagram, dan YouTube**.

---

## 🏛️ 1. Arsitektur & Spesifikasi Modul

- **Rute URL**: `/social-intelligence`
- **Controller**: [`SocialIntelligenceController`](../app/Http/Controllers/SocialIntelligenceController.php)
- **Service Engine**: [`ApifySyncService`](../app/Services/ApifySyncService.php)
- **Tampilan Antarmuka**: [`resources/js/Pages/SocialIntelligence/Index.vue`](../resources/js/Pages/SocialIntelligence/Index.vue)
- **Tabel Basis Data**:
  - `social_posts`: Menyimpan riwayat postingan medsos, metrik views, likes, shares, comments, virality score (0-100), sentimen, status KOL, thumbnail URL, dan bookmark.
  - `social_settings`: Menyimpan token Apify (Multi-API Pool), kata kunci pelacakan (`keywords_tracked`), target akun publik khusus (`accounts_tracked`), frekuensi sinkronisasi, dan status otomasi background.

---

## 📡 2. Empat Pilar Tab Navigasi

### 2.1. Tab 01: Radar Multi-Platform (Live Feeds)
- Feed kartu percakapan publik lintas platform (𝕏, TikTok, Instagram, YouTube).
- **Ekstraksi Media & Video**:
  - **YouTube**: Thumbnail HD resmi dari YouTube CDN (`https://img.youtube.com/vi/{VIDEO_ID}/hqdefault.jpg`) dengan tombol overlay Play merah.
  - **TikTok, Instagram, 𝕏**: Kartu multimedia beresolusi tinggi dengan identitas author dan link sumber langsung.
- **Filter Fleksibel**: Filter berdasarkan platform, sentimen (Positif, Netral, Negatif), filter khusus KOL/Tokoh Kritis, dan pengurutan (Terkini, Paling Viral, Interaksi Terbanyak, Views Terbanyak).
- **🚨 Eskalasi Jadi Isu (1-Klik)**: Tombol instan pada setiap kartu postingan untuk langsung membuat laporan isu baru di `/issues/create` dengan data judul, kategori (*📱 Isu Medsos & Opini Publik*), tautan sumber, dan ringkasan yang terisi otomatis.

### 2.2. Tab 02: Tokoh Kritis & KOL Tracker
- Pemantauan khusus akun-akun berpengaruh (*Key Opinion Leaders*) di wilayah Polda Sulbar dan jajaran: jurnalis, aktivis, LSM/LBH, pengamat, dan tokoh publik.
- Analisis sentimen dominan per tokoh dan riwayat pernyataan publik.

### 2.3. Tab 03: Tren Tagar & Isu Viral (Dual-Mode Architecture)

Tab 03 mengusung konsep **Dual-Mode Lens** yang membagi fokus pemantauan ke dalam 2 mode komando:

#### A. Mode 01: Lensa Kamtibmas & Humas Polri (Fokus Kedinasan)
- **Leaderboard Tagar Dinamis**: Memetakan tagar operasional (*hashtags*) kepolisian dari basis data lokal maupun konsolidasi nasional: `#PolriPresisi`, `#PolresMamujuAman`, `#OperasiKeselamatan2026`, `#DivhumasPolri`, dll.
- **Filter Platform Tagar**: Pengelompokan tagar dinamis khusus platform (Semua, Instagram, TikTok, 𝕏, YouTube).
- **Jam Puncak Obrolan Netizen (*Peak Hours Intelligence*)**:
  - Menghitung kurva waktu aktivitas warganet harian:
    - **Puncak Siang (11.30 – 13.30 WITA/WIB)**: Didominasi keluhan layanan publik, kemacetan, dan aduan cepat.
    - **Puncak Malam (19.00 – 22.00 WITA/WIB)**: Didominasi viralisasi video TikTok, Reels, dan perdebatan opini 𝕏.
  - Rekomendasi otomatis jam penerbitan siaran pers atau infografis klarifikasi (Pukul 12.00 dan 19.30) agar menjangkau lebih dari 70% audiens aktif.

#### B. Mode 02: Lensa Tren Publik Medsos Indonesia (Realtime Multi-Platform)
Masing-masing media sosial memiliki karakteristik format tren yang berbeda:
- **𝕏 (Twitter)**: Menggunakan *Trending Topics/Hashtags* berdasarkan negara/kota (WOEID: 23424846 / Indonesia). Sebagian besar berupa frasa teks tanpa tagar (*un-hashed phrases* seperti `Kompak Kunci Keberhasilan`, `PLN 50rb`, `prabowo perkuat ekonomi`), bukan hanya tagar `#...`.
- **YouTube**: Berbasis tab Trending resmi (Umum, Berita/Politik, Musik) yang mengukur percepatan tayangan video (*views velocity*).
- **Instagram**: Tidak memiliki daftar tren teks global seperti 𝕏; tren di IG diekstrak dari **Reels viral dengan engagement rate tertinggi**, audio latar musik yang sedang tren, dan interaksi tagar.
- **Facebook**: Berbasis postingan publik dengan interaksi tertinggi (reaksi, komentar, share) pada halaman berita lokal dan grup komunitas warga.
- **TikTok**: Berbasis video FYP (*For You Page*) trending dan lagu/sound viral.

**Fitur Unggulan Lensa Tren Publik:**
1. **15 Tren Teratas 𝕏 Indonesia Realtime**:
   Menyajikan 15 topik teratas persis dengan tangkapan layar warganet: `#TOKEN50RBCUMA40RB`, `Kompak Kunci Keberhasilan`, `#2026MAMAVOTE`, `Kepercayaan Pengusaha Nasional`, `PLN 50rb`, `#Hearts2Hearts`, `Halloween`, `#YouManiacSeriesFinalEP`, `pesan perdamaian dunia`, `#GRATISNAIKTRANSUM`, `MANIAC FINAL MOMENT`, `Senin`, `SURVIVE TONIGHT BE MINE`, `JOONGDUNK OASIS MEMORIES`, `prabowo perkuat ekonomi`.
2. **Deteksi Kerawanan Siber & Kamtibmas (*Security Relevance Detection*)**:
   Topik tren yang bersinggungan dengan keamanan publik otomatis diberi penanda merah `🚨 ATENSI KAMTIBMAS & SIBER` beserta catatan intelijen:
   - Modus penipuan phishing / file APK bodong catut nama PLN (`#TOKEN50RBCUMA40RB` & `PLN 50rb`).
   - Atensi kerumunan pesta malam akhir pekan & rekayasa lalin (`Halloween`).
   - Kepadatan halte angkutan massal gratis & pengawalan Ditlantas (`#GRATISNAIKTRANSUM`).
   - Penipuan QRIS tempel palsu di rumah ibadah & debat politik pilkada.
3. **Filter Cepat `🚨 Relevan Kamtibmas Saja`**:
   Menyaring seketika daftar tren publik sehingga hanya memunculkan isu-isu yang membutuhkan atensi kepolisian dan menyembunyikan obrolan pop culture murni.
4. **Tindakan Terpadu 1-Klik**:
   - `🚨 Eskalasi Jadi Isu`: Langsung mengarahkan ke formulir `/issues/create` dengan judul, kategori, deskripsi, sumber, dan tingkat prioritas yang terisi otomatis.
   - `🔍 Pantau di Radar`: Membuka Tab 01 Radar Medsos untuk melacak postingan terkait.
   - `🔗 Buka Sumber`: Membuka tautan langsung ke halaman pencarian resmi platform terkait.
   - `⚡ Refresh Tren Publik (Apify)`: Mengambil data pembaruan tren publik terkini via endpoint `POST /social-intelligence/sync-trends`.

### 2.4. Tab 04: Integrasi Apify & Pengaturan

- **Multi-API Token Pool**: Mendukung input beberapa token Apify (dipisahkan koma atau baris baru).
- **Otomatis Failover**: Sistem merotasi kuota $5/bulan per akun secara otomatis. Jika akun utama terkena rate limit atau kuota bulanan habis, sistem langsung beralih ke token cadangan tanpa memutus proses sinkronisasi.
- **Uji Koneksi Token**: Tombol verifikasi real-time ke API Apify yang menampilkan profil akun, email, paket, dan status koneksi untuk setiap token dalam pool.
- **Akun Target Pantauan Khusus (`accounts_tracked`)**: Kolom input daftar akun media sosial publik spesifik (misal `@poldasulbar`, `@polresta_mamuju_official`, `@tribratanews_sulbar`) agar crawler memprioritaskan penarikan dari akun-akun tersebut.

#### Katalog Format Tren & Rekomendasi Actor Apify per Platform

| Platform | Format Tren yang Didukung | Rekomendasi Actor di Apify | Output Data yang Dihasilkan |
| :--- | :--- | :--- | :--- |
| **𝕏 (Twitter)** | Trending Topics / Hashtag real-time berdasarkan negara atau kota (WOEID: 23424846 / Indonesia). | • `easyapi/twitter-trending-topics-scraper`<br>• `apidojo/tweet-scraper` | Nama topik/hashtag, rank tren, volume tweet, kategori tren, lokasi WOEID. |
| **YouTube** | Tab Trending resmi (Umum, Berita/Politik, Musik, Gaming) per negara (ID / Indonesia). | • `streamers/youtube-scraper`<br>• `bernardo/youtube-trending-scraper` | Judul video, tautan URL, nama channel, view count, likes, peringkat trending. |
| **Instagram** | Top/viral posts, reels, audio/musik tren, dan hashtag populer. | • `yugenox/instagram-trends-scraper`<br>• `apify/instagram-scraper`<br>• `coderx/instagram-hashtag-scraper` | Post URL, caption teks, view/play count, like count, engagement rate, top hashtags. |
| **Facebook** | Postingan publik dengan engagement / interaksi tertinggi pada topik atau halaman berita/satwil tertentu. | • `apify/facebook-posts-scraper`<br>• `apify/facebook-pages-scraper` | Teks postingan, shares, reaksi/likes, komentar, nama halaman/grup sumber. |
| **TikTok** | Top FYP videos, video sound/audio viral, dan hashtag trending Indonesia. | • `clockworks/free-tiktok-scraper`<br>• `apidojo/tiktok-scraper` | Video URL, creator username, sound name, play count, likes, shares, hashtag list. |

---

## ⚡ 3. Cara Menjalankan Sinkronisasi Medsos

### A. Melalui Tombol Antarmuka Web
1. Buka menu **Radar Media Sosial** (`http://simod-enterprise.test/social-intelligence?polda=SULBAR`).
2. Klik tombol **`Sinkronisasi Radar`** di pojok kanan atas, atau tombol **`⚡ Tarik Data Live Apify Sekarang`** di Tab 04.

### B. Melalui Artisan Tinker / CLI
```php
// Sinkronisasi data medsos Polda Sulbar (ID: 5)
(new App\Services\ApifySyncService())->syncPolda(5);
```

---

## 🌐 4. Kompatibilitas Live Demo Publik (Ngrok)

Modul ini telah diuji dan kompatibel penuh dengan tunnel Ngrok untuk kebutuhan presentasi pimpinan dari jarak jauh:
```bash
# Perintah tunnel resmi SIMOD Enterprise
ngrok http --domain=humped-obvious-blatantly.ngrok-free.dev --host-header=simod-enterprise.test 80
```
- Konfigurasi `trustProxies` pada `bootstrap/app.php` dan `URL::forceScheme('https')` pada `AppServiceProvider.php` menjamin seluruh CSS/JS Vite dan formulir Inertia berjalan mulus tanpa masalah *mixed content*.

---

## 🏛️ 5. Konvergensi Intelijen Terpadu di Command Center (`/`)

Data dari **Radar Media Sosial** terintegrasi secara *real-time* ke **Executive Command Center Presisi** ([`Dashboard.vue`](../resources/js/Pages/Dashboard.vue) & [`DashboardController`](../app/Http/Controllers/DashboardController.php)):

1. **5 Kartu Eksekutif KPI Utama**:
   - Menampilkan kartu khusus **Radar Media Sosial** bersanding dengan **Media Online (29 RSS)**, **Isu Prioritas Atensi**, **Laporan Dumas Lapangan**, dan **Tindak Lanjut Tugas**.
   - Menyajikan jumlah total postingan aktif, status breakdown 4 platform (YouTube, TikTok, Instagram, 𝕏), serta shortcut langsung ke modul Radar Medsos.
2. **Indeks Citra Publik Terpadu & Tren Sentimen 7 Hari**:
   - Menggabungkan perhitungan polarisasi sentimen (*favorability ratio*) dari pemberitaan media online, postingan medsos, dan laporan dumas.
3. **Tab Switcher Tren: 🔥 Top Viral Medsos vs 🚨 Isu Kamtibmas**:
   - Pimpinan dapat beralih cepat dalam satu blok ringkas untuk melihat postingan paling viral di media sosial (dilengkapi persentase virality score, views, likes, dan link sumber) atau daftar isu kamtibmas prioritas.
4. **Feed Intelijen Real-Time Terpadu (Dual-Tab Stream)**:
   - **Tab 1 — Radar Medsos Live**: Menampilkan kartu postingan terkini lengkap dengan badge platform, thumbnail video HD (dengan icon play overlay untuk YouTube/TikTok), handle akun, ringkasan konten, dan sentimen.
   - **Tab 2 — Radar Berita 29 RSS**: Menampilkan headline berita terkini dari 29 portal media online resmi.
5. **Bar Analisis Sentimen Medsos**:
   - Di bawah Matriks 6 Polres, terdapat visualisasi bar multi-warna untuk persentase Positif, Netral, dan Negatif beserta akumulasi interaksi publik (views & likes).

---

## 🇮🇩 6. Mode Skala Nasional & Orkestrasi Divhumas Polri (`polda=ALL`)

Ketika pengguna memilih wilayah **Divhumas Polri** (parameter URL `polda=ALL`), modul Radar Media Sosial secara otomatis beradaptasi menjadi **Komando Cyber Patrol & Social Listening Nasional**:

1. **Agregasi Metrik Nasional**:
   - Menghitung seluruh postingan dari seluruh Polda dan Satker Mabes tanpa pembatasan `polda_id`.
   - Menampilkan akumulasi jangkauan publik nasional (jutaan views, ratusan ribu likes, komentar, dan virality index nasional).
2. **Filter Dinamis Satwil / Polda Jajaran**:
   - Operator Divhumas dapat memilih opsi **`🌐 Seluruh 34 Polda`** untuk pemantauan arus opini agregat se-Indonesia, atau melakukan *drill-down* ke Polda tertentu (misal **Polda Sulbar**) melalui parameter `satwil`.
3. **Badge Asal Satwil di Kartu Postingan**:
   - Setiap kartu postingan di Tab 01 menampilkan badge asal wilayah (contoh: `🏛️ SULBAR`) sehingga analis Mabes Polri dapat langsung mengidentifikasi locus opini publik.
4. **Radar Tokoh Kritis & KOL Skala Nasional (Tab 02)**:
   - Menampilkan entitas pengawas dan opini publik tingkat nasional:
     - **Kompolnas RI** (`@kompolnas_ri`) — Lembaga Pengawas Kepolisian Nasional
     - **Indonesia Police Watch (IPW)** (`@ipw_watch`) — Lembaga Pemantau Kinerja & Reformasi Polri
     - **Dewan Pers RI** (`@dewanpers_ri`) — Lembaga Pengawas Etika Pers & Komunikasi Massa
     - **KontraS** (`@kontras_update`) — Pemantau HAM & Akuntabilitas Aparat
     - **Najwa Shihab / Narasi Newsroom** (`@narasinewsroom`) — Media Investigasi & Tokoh Opini Publik Kritis
     - **ISESS (Institute for Security and Strategic Studies)** (`@isess_official`) — Pengamat Keamanan & Kepolisian Nasional
5. **Tren Tagar & Isu Viral Nasional (Tab 03)**:
   - Menampilkan tagar bertaraf nasional dengan estimasi volume puluhan hingga ratusan ribu interaksi: `#PolriPresisi`, `#DivhumasPolri`, `#HumasPolri`, `#Kapolri`, `#PilkadaSerentak2026`, `#SiberPolri`, `#KamtibmasKondusif`, dll.
6. **Integrasi Apify & Akun Target Nasional (Tab 04)**:
   - Menargetkan akun-akun resmi pimpinan dan korps Mabes Polri (`@divisihumaspri`, `@humaspolri`, `@polripresisi`, `@halo_polisi`, `@ccicpolri`, `@bareskrim.polri`) dan kata kunci pemantauan skala makro.



