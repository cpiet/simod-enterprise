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

### 2.3. Tab 03: Tren Tagar & Isu Viral
- Pemetaan tagar (*hashtags*) yang sedang naik daun (*trending*) di wilayah hukum Polda aktif.
- Volume perkiraan percakapan dan polarisasi sentimen per tagar.

### 2.4. Tab 04: Integrasi Apify & Pengaturan
- **Multi-API Token Pool**: Mendukung input beberapa token Apify (dipisahkan koma atau baris baru).
- **Otomatis Failover**: Sistem merotasi kuota $5/bulan per akun secara otomatis. Jika akun utama terkena rate limit atau kuota bulanan habis, sistem langsung beralih ke token cadangan tanpa memutus proses sinkronisasi.
- **Uji Koneksi Token**: Tombol verifikasi real-time ke API Apify yang menampilkan profil akun, email, paket, dan status koneksi untuk setiap token dalam pool.
- **Akun Target Pantauan Khusus (`accounts_tracked`)**: Kolom input daftar akun media sosial publik spesifik (misal `@poldasulbar`, `@polresta_mamuju_official`, `@tribratanews_sulbar`) agar crawler memprioritaskan penarikan dari akun-akun tersebut.

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
