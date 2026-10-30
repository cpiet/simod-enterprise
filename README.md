# SIMOD Enterprise (Sistem Monitoring dan Opini Publik Digital)

Platform intelijen media, monitoring opini publik, dan manajemen operasional kehumasan terpadu berbasis multi-tenant untuk Kepolisian Negara Republik Indonesia (Mabes Polri, Polda, dan Polres/Satwil).

---

## 🌟 Fitur Utama

- **Multi-Tenant Architecture**: Pemisahan data dan konteks operasional antar Polda dan jajaran Satwil (Polres/Polresta) di seluruh Indonesia.
- **Media Monitoring & Intelligence**: Pelacakan sentimen publik (Positif, Netral, Negatif), media online, dan tren media sosial secara real-time.
- **Early Warning System (EWS)**: Deteksi dini isu viral atau berpotensi krisis dengan klasifikasi urgensi (Tinggi, Sedang, Rendah).
- **Issue & Crisis Management**: Siklus penanganan isu menyeluruh dari eskalasi hingga resolusi lengkap dengan Counter Narasi & Press Release resmi.
- **Antrean Verifikasi (Verification Queue)**: Validasi dan kurasi link rilis, pemberitaan, dan media pendukung oleh operator Humas.
- **Manajemen Personalia & Satker**: Struktur hierarki komando dan distribusi personel Humas yang dinamis.
- **Laporan & Anev (Analisis dan Evaluasi)**: Ekspor dan rekapitulasi performa kehumasan harian, mingguan, dan bulanan.
- **Audit Trails**: Pencatatan aktivitas log keamanan dan operasional untuk akuntabilitas data.

---

## 🛠️ Tech Stack

- **Backend**: Laravel 11 (PHP 8.2+)
- **Frontend**: Vue 3, Inertia.js, Tailwind CSS v4
- **Database**: MySQL 8.x / MariaDB
- **State Management & Icons**: Pinia, Lucide Vue Next

---

## 🚀 Setup & Instalasi

### 1. Clone Repositori
```bash
git clone https://github.com/cpiet/simod-enterprise.git
cd simod-enterprise
```

### 2. Instalasi Dependency
```bash
composer install
npm install
```

### 3. Konfigurasi Environment
```bash
cp .env.example .env
php artisan key:generate
```
Sesuaikan konfigurasi database di file `.env`:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=simod_enterprise
DB_USERNAME=root
DB_PASSWORD=
```

### 4. Migrasi & Seeding Database
```bash
php artisan migrate --seed
# Atau seeder enterprise lengkap:
php artisan db:seed --class=EnterpriseDatabaseSeeder
```

### 5. Jalankan Server Pengembangan
```bash
# Terminal 1: Vite Dev Server
npm run dev

# Terminal 2: Laravel Server
php artisan serve
```

---

## 🔒 Lisensi & Hak Cipta
Hak cipta dilindungi undang-undang. Dikembangkan untuk kebutuhan operasional kehumasan kepolisian.
