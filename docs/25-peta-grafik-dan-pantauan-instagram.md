# 25. Peta situasi, grafik media sosial, dan pantauan Instagram

Dokumen ini menjelaskan perubahan pada Command Center dan Radar Media Sosial. Kode tetap menjadi rujukan utama perilaku aplikasi.

## Peta situasi

Command Center menggunakan Leaflet dengan tile OpenStreetMap, kontrol geser dan zoom, serta titik perwakilan wilayah berdasarkan data SIMOD. Titik tersebut bukan lokasi kejadian yang telah diverifikasi; sebagian pengelompokan berita menggunakan kata kunci. Data kosong ditampilkan sebagai belum tersedia dan akses mengikuti cakupan tenant.

Tile standar membutuhkan akses internet dan atribusi OpenStreetMap. `VITE_MAP_TILE_URL` dan `VITE_MAP_ATTRIBUTION` dapat diatur pada lingkungan build bila memakai penyedia lain. Variabel Vite masuk ke aset publik saat build: jangan masukkan rahasia. Periksa kebijakan dan kuota penyedia untuk penggunaan produksi. Peta tidak mengunci domain; konfigurasi domain, HTTPS, dan hosting tetap perlu diperiksa saat deployment.

## Grafik percakapan tujuh hari

Grafik mengambil unggahan `social_posts` yang tersimpan dan mengikuti filter daftar, kecuali tanggal drill-down yang hanya membatasi daftar. Hari tanpa catatan bernilai nol, bukan data buatan. Nol berarti tidak ada catatan tersimpan, bukan kepastian tidak ada percakapan. Data yang belum memiliki salah satu dari tiga sentimen ditampilkan sebagai belum diklasifikasi.

Penanda lonjakan dihitung jika jumlah negatif sedikitnya lima dan setidaknya 1,5 kali rata-rata enam hari sebelumnya. Ini adalah ambang operasional, bukan kepastian risiko atau jumlah aduan. Sebagian data simulasi dapat dikenali dari awalan `synthetic_`; angka tersebut bukan inventaris lengkap seluruh data simulasi atau bukti keaslian data lainnya. Hasil klasifikasi dan sumber tetap harus ditinjau.

## Pantau akun Instagram

Pada **Radar Media Sosial → Radar KOL & Akun Kritis → Pantau Akun Instagram**, pengguna dengan izin `social_intelligence_sync` dapat memasukkan username dan melakukan sinkronisasi manual. Fitur ini menggunakan Actor `apify/instagram-scraper` dan token Apify yang tersedia pada pengaturan SIMOD. Hasil maksimal lima unggahan per permintaan dan batas biaya Actor ditetapkan US$0,03 per run; biaya aktual dan ketersediaan bergantung pada Apify. Tidak ada jadwal otomatis.

SIMOD hanya menerima baris yang `ownerUsername`-nya cocok dengan akun yang diminta dan URL unggahannya valid pada domain Instagram. Suka dan komentar yang tidak tersedia tidak direka. Akun dan snapshot unggahan disimpan pada tabel `monitored_instagram_accounts`, terpisah dari `social_posts`, sesuai tenant aktif. Kegagalan sinkronisasi mempertahankan snapshot sukses terakhir dan menampilkan status. Hasil adalah snapshot, bukan jaminan seluruh unggahan terbaru berhasil diambil. Fitur pantauan akun X belum tersedia.

### Deployment

Setelah memperbarui kode pada lingkungan tujuan, jalankan migrasi Laravel yang tertunda (`php artisan migrate --force`) dan build frontend (`npm ci` lalu `npm run build`, atau build pada lingkungan CI sesuai prosedur deployment). Pastikan worker web dapat mengakses API Apify, token tersedia di pengaturan yang digunakan aplikasi, dan kontrol akses tenant/RBAC diuji. Jangan memasukkan token atau file `.env` ke Git.

### Pengujian

Jalankan `php artisan test` dengan SQLite in-memory sesuai `phpunit.xml`, dan `npm.cmd run build` pada Windows Laragon. Tes pantauan Instagram menggunakan respons Apify tiruan untuk memeriksa penyaringan penulis/URL, metrik yang hilang, kegagalan, dan isolasi tenant; pengujian tersebut tidak menjamin layanan eksternal selalu tersedia.

## Addendum 6 Nov 2026: pantauan Instagram dibuat siap produksi

- **Token tidak pernah ke browser.** Props halaman Radar Media Sosial kini hanya memuat `settings.apify_token_set` dan `settings.apify_token_hint` (jumlah token + empat karakter terakhir). Token mentah hanya dikelola administrator di Keamanan & Pemeliharaan → Mesin Intelijen AI (`/settings/ai`). Form pengaturan sosial tidak lagi menulis token (sebelumnya form yang kosong dapat menghapusnya); uji koneksi dan tarik tren memakai token tersimpan di server.
- **Status jujur.** Prop `instagramReady` menampilkan banner "Token Apify belum diatur" dan menonaktifkan tombol sinkron bila token belum ada. Pengguna tanpa izin `social_intelligence_sync` melihat hasil yang sudah ada beserta keterangan izin, bukan formulir yang hilang tanpa penjelasan.
- **Alasan gagal tersimpan** di kolom `monitored_instagram_accounts.last_error` (migrasi `2026_11_09_090000`): token ditolak (401/403), saldo/batas habis (402), dibatasi (429), akun tidak ditemukan/privat, timeout. Snapshot sukses terakhir tetap dipertahankan.
- **Data lebih kaya:** hingga 12 unggahan per sinkron (batas biaya Actor US$0,05), jenis (foto/video/album), tayangan, hashtag, dan thumbnail (hanya dari domain CDN Instagram, dimuat tanpa referrer; bila gagal dimuat ditampilkan ikon). Ringkasan per akun (jumlah unggahan, rata-rata suka/komentar, unggahan terbaru) dihitung dari data tersimpan dan mengabaikan metrik yang tidak tersedia.
- **Alur kerja:** tombol "Pantau akun" pada kartu KOL Instagram mengisi formulir; "Sinkronkan semua" memproses akun satu per satu. Tetap manual dan berbiaya Apify.
- **Kecepatan.** Sembilan query metrik digabung menjadi satu query agregat, dan analitik berat (KOL, tagar, jam puncak, lonjakan, awan kata, tren publik) kini prop lazy sehingga reload sebagian (mis. setelah sinkron akun) tidak menghitung ulang semuanya.
- **Pengujian:** `InstagramAccountMonitoringTest`, `NavigationSweepTest` (menu semua peran terbuka tanpa 403, semua rute GET tanpa parameter tidak 500, token tidak bocor/terhapus).
- **Deployment:** jalankan `php artisan migrate --force` (kolom `last_error`) dan bersihkan cache (`php artisan optimize:clear`).

## Addendum 6 Nov 2026 (2): banyak akun & unggahan menonjol
Tampilan ringkas per akun, cari/urut/saring, dan penanda unggahan menonjol (`App\Support\InstagramHighlights`). Grafik 7 hari kini memakai tanggal zona waktu tenant. Detail: doc 33. Pembedaan platform di tab KOL: doc 34 §5.
