# 🛠️ 02. Daftar Perintah Artisan & CLI

Kumpulan perintah CLI resmi untuk pemeliharaan, pengembangan, pengujian otomatis, dan operasional SIMOD Enterprise di Laragon.

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
# Seed data awal multi-tenant (Polda Sulbar, Gorontalo, Sulsel, Sultra, Kaltim, Polres, Akun Dinas)
php artisan db:seed --class=MultiTenantPoldasSeeder

# Seed data isu kamtibmas & siaran pers
php artisan db:seed --class=IssueAndReleaseSeeder

# Seed 29 sumber portal RSS resmi Sulbar & Nasional
php artisan db:seed --class=SulbarRssFeedsSeeder

# Seed artikel historis multi-Polda (untuk uji coba retensi & umur berita)
php artisan db:seed --class=MultiPoldaArticlesHistoricalSeeder

# Seed publikasi media sosial & disposisi tugas operasional
php artisan db:seed --class=PublicationsAndTasksSeeder
```

---

## 2. Perintah Operasional SIMOD CLI

### 2.1. Sinkronisasi Agregasi Berita RSS Multi-Polda
Menarik berita secara paralel dari portal media dan menyimpannya ke basis data dengan proteksi Mutex Lock dan Auto-Capping:
```powershell
# Sinkronisasi aman untuk Polda Sulawesi Barat
php artisan rss:sync --polda=SULBAR

# Abaikan mutex lock jika ada proses terkunci macet
php artisan rss:sync --polda=SULBAR --force

# Sinkronisasi seluruh 34 Polda terdaftar secara berurutan
php artisan rss:sync
```

### 2.2. Pembersihan & Pemangkasan Berita Lama (Retention Pruner)
Membersihkan artikel non-bookmark yang melebihi batas hari simpan:
```powershell
# Bersihkan artikel non-bookmark yang lebih tua dari 90 hari untuk seluruh Polda
php artisan media:prune --days=90

# Bersihkan untuk Polda tertentu saja (misal Sulbar 30 hari)
php artisan media:prune --days=30 --polda=SULBAR
```

### 2.3. Pencadangan Basis Data Otomatis & Manual (Database Snapshot & Prune)
Mencadangkan basis data MySQL ke snapshot terkompresi Gzip dengan verifikasi hash SHA-256 dan auto-pruning berkas lama:
```powershell
# Eksekusi backup seketika (mengabaikan jam jadwal)
php artisan db:backup --force

# Eksekusi terjadwal (memverifikasi konfigurasi jadwal waktu di database)
php artisan db:backup --type=scheduled

# Eksekusi dengan retensi kustom (hapus berkas lama lebih tua dari 30 hari)
php artisan db:backup --force --retention=30
```

---

## 3. Pengujian Otomatis (Automated Testing Suite)

Pengujian fitur aplikasi menggunakan PHPUnit dengan basis data SQLite in-memory (aman, cepat, dan tidak menghapus database MySQL lokal):
```powershell
# Menjalankan seluruh 26 Unit & Feature Tests (128 assertions)
php artisan test

# Menjalankan spesifik modul DatabaseBackupTest
php artisan test --filter=DatabaseBackupTest

# Menjalankan spesifik modul RssSyncTest
php artisan test --filter=RssSyncTest

# Menjalankan spesifik modul SecurityCenterTest
php artisan test --filter=SecurityCenterTest
```

---

## 4. Perintah Frontend & Aset Vite

### Kompilasi Aset untuk Produksi (Wajib setelah mengubah file Vue/CSS)
```powershell
npm run build
```

### Menjalankan Vite Dev Server (Hot Module Replacement)
```powershell
npm run dev
```

---

## 5. Perintah Server, Cache & Task Scheduler

### Menjalankan Server Lokal Port 8000
```powershell
php artisan serve --port=8000
```

### Membersihkan Seluruh Cache Aplikasi
```powershell
php artisan optimize:clear
```

### Menjalankan Simulasi Task Scheduler
```powershell
php artisan schedule:run
```
*(Scheduler di `routes/console.php` menjalankan `rss:sync --scheduled` setiap menit dan `media:prune` setiap pukul 02:00).*
