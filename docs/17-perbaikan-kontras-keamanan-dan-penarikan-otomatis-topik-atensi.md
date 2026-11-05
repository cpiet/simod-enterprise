# 🛡️ 17. Perbaikan Navigasi Tab, Kontras Keamanan, & Otomasi Sinkron Topik Atensi

Dokumen ini mencatat pembaruan teknis terhadap perbaikan antarmuka navigasi tab pemeliharaan terpadu, penyempurnaan kontras tema ganda pada Pusat Keamanan, penghapusan pemaksaan gambar stok pada berita media online, dan sinkronisasi otomatis Google News saat mengadopsi Topik Atensi.

---

## 1. Navigasi Tab Pemeliharaan Terpadu Simetris 2 Baris 5 Kolom

### 1.1. Masalah Teks Terpotong (*Truncation*)
Sebelumnya, komponen [`SecurityMaintenanceTabs.vue`](../resources/js/Components/SecurityMaintenanceTabs.vue) memaksakan 10 sub-modul dalam 1 baris kaku 10 kolom (`xl:grid-cols-10`) disertai class `truncate`. Pada layar desktop standar (1280px–1536px), lebar tiap kolom hanya ~90–110px sehingga judul modul yang panjang (>8 karakter) terpotong:
- `Manajemen Menu` -> `Manajeme...`
- `Pembersihan Berita` -> `Pembersih...`
- `Otomasi Sinkron RSS` -> `Otomasi Si...`
- `Mode Pemeliharaan` -> `Mode Pem...`
- `Konfigurasi Mail` -> `Konfigurasi...`
- `Mesin Intelijen AI` -> `Mesin Inteli...`

### 1.2. Solusi Desain Simetris
- Mengubah struktur layout menjadi **2 baris simetris berisi masing-masing 5 kolom** pada layar desktop (`grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5`).
- Menghapus class `truncate` dan menerapkan `sm:whitespace-nowrap font-bold text-xs`.
- Menambahkan tooltip bawaan `:title="t.name"` pada elemen `<Link>`.
- Seluruh 10 sub-modul kini tampil utuh 100%:
  - **Baris 1**: `🛡️ Keamanan` &bull; `🔑 Hak Akses` &bull; `🎛️ Manajemen Menu` &bull; `🕒 Audit Log` &bull; `🗄️ Backup Data`
  - **Baris 2**: `🧹 Pembersihan Berita` &bull; `📡 Otomasi Sinkron RSS` &bull; `🔧 Mode Pemeliharaan` &bull; `✉️ Konfigurasi Mail` &bull; `🤖 Mesin Intelijen AI`

---

## 2. Penyempurnaan Dual-Theme Engine pada Pusat Keamanan (`/security`)

- **Masalah Teks Gelap di Atas Latar Gelap pada Mode Terang**:
  Header kartu di [`Security.vue`](../resources/js/Pages/Settings/Security.vue) sebelumnya menggunakan `bg-gradient-to-r` gelap permanen tanpa varian `dark:`. Saat beralih ke Mode Terang, teks menjadi hitam `#0f172a` di atas latar gradasi yang tetap gelap.
- **Penyelesaian**:
  - Mengonfigurasi pasangan token `bg-white dark:bg-gradient-to-r dark:from-[#0d182e] ...` dan border `border-slate-200 dark:border-slate-800`.
  - Menambahkan aturan CSS global di [`resources/css/app.css`](../resources/css/app.css) untuk me-reset gradient gelap kartu ke warna putih bersih berteks kontras tinggi saat berada dalam **Mode Terang (Executive Light)**.

---

## 3. Eliminasi Pemaksaan Gambar Stok & Default Placeholder Resmi

### 3.1. Masalah Gambar Stok Berulang
Ketika berita tidak menyertakan gambar thumbnail, sistem sebelumnya menyematkan foto stok Unsplash secara acak (foto ruang sidang, kabel charger, laptop, kode matrix). Akibatnya, banyak berita yang menampilkan foto yang sama dan tidak relevan dengan substansi pemberitaan.

### 3.2. Solusi & Pembersihan Basis Data
- **Pembersihan Basis Data**: Menjalankan pembersihan data pada 270 artikel yang sebelumnya berisi tautan `unsplash.com` menjadi `null`.
- **Penghapusan Generator Gambar Stok**:
  - Menghapus fungsi `getEditorialFallbackImage` di [`GoogleNewsCrawlerService.php`](../app/Services/GoogleNewsCrawlerService.php). Jika Google News tidak menyertakan foto, kolom `image_url` tetap `null`.
  - Menghapus array `defaultPoliceImages` di [`Index.vue`](../resources/js/Pages/MediaIntelligence/Index.vue).
- **Default Placeholder Elegan**:
  - Menampilkan banner placeholder resmi yang berwibawa:
    - Ikon `ImageOff` dalam kotak rounded modern.
    - Tulisan tegas: **`TIDAK ADA GAMBAR DITAMPILKAN`**.
    - Keterangan sumber: `Dokumentasi Berita Teks • [Nama Portal]`.
  - Tampilan grid tetap simetris pada tinggi `h-[435px]` tanpa merusak estetika dashboard Command Center.

---

## 4. Sinkronisasi Otomatis Google News Saat Adopsi Sugesti Isu AI

### 4.1. Alur Kerja Instan 1-Klik (`✓ Pantau`)
Ketika analis atau pimpinan mengklik tombol `✓ Pantau` pada baris **Sugesti Isu Potensial & Tren Media Online (Google News AI)**:
1. **Sanitasi Query Cerdas ([`GoogleNewsCrawlerService.php`](../app/Services/GoogleNewsCrawlerService.php))**:
   - Menghapus tanda pagar (`#`) dan tanda baca pemisah dari kata kunci agar query Google News RSS menghasilkan berita faktual (misal: `#pengamanan #sidang` menjadi `pengamanan sidang`).
2. **Penarikan & Ingesti Simultan ([`MediaIntelligenceController.php`](../app/Http/Controllers/MediaIntelligenceController.php))**:
   - Menarik berita internet secara live dan mengaitkan artikel portal lokal yang relevan di basis data.
   - Mengembalikan array `articles` lengkap dalam respons JSON `POST /media-intelligence/topics/adopt-suggestion`.
3. **Injeksi Langsung ke Feed Tampilan ([`Index.vue`](../resources/js/Pages/MediaIntelligence/Index.vue))**:
   - Memasukkan artikel hasil crawling langsung ke urutan teratas feed (`props.sampleArticles.unshift(...)`).
   - Mengarahkan filter aktif ke topik atensi tersebut dan me-reset filter portal ke *Semua Portal*.
   - Menghubungkan pencocokan kata kunci pada `filteredArticles` sehingga seluruh berita terkait langsung terpampang di layar seketika tanpa perlu memuat ulang halaman secara manual.

---

## 5. Verifikasi & Pengujian Otomatis

- **Browser Automation (Puppeteer)**:
  - Tampilan tab `/security` diverifikasi utuh pada resolusi 1440×900 baik di Dark Mode maupun Light Mode.
  - Alur klik `✓ Pantau` pada isu *Pengamanan Ketat Persidangan Kasus Hukum Berprofil Tinggi* diverifikasi langsung: 8 berita berhasil ditarik dan tampil seketika di feed dengan banner *Tidak Ada Gambar Ditampilkan*.
- **Automated Tests**:
  - `php artisan test`: Seluruh **90 tests (517 assertions) LULUS 100% (PASS)** dalam 10.91 detik *(snapshot historis; baseline terkini: lihat doc 22 §10)*.

> **Catatan:** *Mode Pemeliharaan* dan *Konfigurasi Mail* disebut di atas sudah dihapus karena tidak berfungsi, dan bar sub-modul kini satu baris berisi 8 tab (lihat [doc 24](24-penyederhanaan-isu-tugas-publikasi-dan-administrasi.md)).
