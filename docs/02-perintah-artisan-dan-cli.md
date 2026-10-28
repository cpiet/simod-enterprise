# 🛠️ 02. Daftar Perintah Artisan & CLI

Kumpulan perintah CLI resmi untuk pemeliharaan, pengembangan, pengujian, dan operasional SIMOD Enterprise di Laragon.

---

## 1. Perintah Basis Data & Migrasi

### Menjalankan Seluruh Migrasi dari Nol
```powershell
php artisan migrate:fresh
```

### Menjalankan Seeding Komplit (Polda, Polres, Pengguna, EWS, Feed, Isu, Tugas)
```powershell
php artisan db:seed
```

### Menjalankan Seeder Tertentu Secara Terpisah
```powershell
# Seed data awal multi-tenant (Polda Sulbar, Gorontalo, Polres, Akun Dinas)
php artisan db:seed --class=EnterpriseDatabaseSeeder

# Seed data isu kamtibmas & siaran pers
php artisan db:seed --class=IssueAndReleaseSeeder

# Seed 14 sumber portal RSS resmi Sulbar & Nasional
php artisan db:seed --class=SulbarRssFeedsSeeder

# Seed publikasi media sosial & disposisi tugas operasional
php artisan db:seed --class=PublicationsAndTasksSeeder
```

---

## 2. Perintah Operasional SIMOD CLI

### Menjalankan Penarikan Berita RSS Satwil (Scheduled Scraper)
Menarik berita secara paralel dari 14 portal media Satwil dan menyimpannya ke basis data:
```powershell
# Tarik untuk Polda Sulawesi Barat
php artisan simod:scrape-rss --polda=SULBAR

# Tarik untuk Polda Gorontalo
php artisan simod:scrape-rss --polda=GORONTALO

# Tarik untuk seluruh Polda terdaftar secara otomatis
php artisan simod:scrape-rss
```

---

## 3. Perintah Frontend & Aset Vite

### Kompilasi Aset untuk Produksi (Wajib setelah mengubah file Vue/Tailwind)
```powershell
npm run build
```

### Menjalankan Vite Dev Server (Hot Module Replacement)
```powershell
npm run dev
```

---

## 4. Perintah Server & Cache

### Menjalankan Server Lokal Port 8000
```powershell
php artisan serve --port=8000
```

### Membersihkan Cache Aplikasi, Rute, dan Konfigurasi
```powershell
php artisan optimize:clear
```

### Verifikasi Rute yang Terdaftar
```powershell
php artisan route:list
```

### Membuka Konsol Interaktif Tinker
```powershell
php artisan tinker
```
