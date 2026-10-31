# 🤖 AGENTS.md — Panduan Arsitektur & Instruksi Operasional untuk AI Coding Agents (Codex, Claude, Gemini, Antigravity)

Dokumen ini adalah **panduan rujukan resmi sistem** yang dirancang khusus untuk Agentic AI (OpenAI Codex, Claude 3.5 Sonnet, Google Gemini, DeepSeek, Antigravity, GitHub Copilot) saat membaca, menganalisis, merancang fitur, atau memodifikasi basis kode **SIMOD Enterprise**.

---

## 🏛️ 1. Ikhtisar Sistem & Core Tech Stack

- **Nama Platform**: SIMOD Enterprise (Sistem Monitoring dan Opini Publik Digital POLRI v2.0)
- **Tujuan**: Platform Command Center intelijen media siber, pemantauan opini publik, Early Warning System (EWS), agregasi 29 RSS multi-portal, orkestrasi rilis pers, dan manajemen operasional tugas kehumasan berstandar Divisi Humas Polri & Polda Jajaran.
- **Arsitektur Utama**:
  - **Backend**: Laravel 11 / 12 (PHP 8.2+) dengan arsitektur MVC & Service Repository.
  - **Frontend**: Vue 3 (Composition API dengan `<script setup>`), Inertia.js v2 (tanpa Blade view SPA tradisional).
  - **Styling**: Tailwind CSS v4 (`@tailwindcss/vite` tanpa `tailwind.config.js`). Konfigurasi token tema tersentralisasi di [`resources/css/app.css`](file:///d:/Foto/laragon/www/simod-enterprise/resources/css/app.css).
  - **Basis Data**: MySQL 8.x / MariaDB (produksi lokal Laragon) + SQLite in-memory (khusus automated testing).
  - **Icons**: `lucide-vue-next`.

---

## 🗺️ 2. Peta 4 Pilar Navigasi & Modul Terpadu

Sidebar navigasi di [`EnterpriseLayout.vue`](file:///d:/Foto/laragon/www/simod-enterprise/resources/js/Layouts/EnterpriseLayout.vue) distrukturkan secara tegas ke dalam 4 pilar hierarkis:

```
├── 1. KOMANDO & INTELIJEN
│   ├── Command Center               [/] -> Ringkasan Eksekutif, Peta Situasi & EWS
│   └── Media Online                 [/media-intelligence] -> Radar 29 RSS, Executive Briefing, Naskah WA
│
├── 2. MANAJEMEN ISU & TUGAS
│   ├── Laporan Polres & Isu         [/issues] -> Pemantauan daftar isu & eskalasi sentimen
│   ├── Input Laporan Baru           [/issues/create] -> Formulir laporan lapangan & bukti foto
│   ├── Antrean Verifikasi           [/verification-queue] -> Validasi fakta & klarifikasi hoaks
│   └── Disposisi Tugas              [/tasks] -> Surat perintah penugasan anggota & rekap hasil
│
├── 3. PUBLIKASI & DOKUMENTASI
│   ├── Publikasi Multi-Kanal        [/publications] -> Tribratanews, Instagram, TikTok, Facebook, X
│   ├── Galeri Media Aman            [/gallery] -> Arsip foto/video bukti terenkripsi
│   └── Laporan & Anev               [/reports] -> Rekapitulasi kinerja kehumasan & ekspor CSV/PDF
│
└── 4. ADMINISTRASI & SISTEM
    ├── Personalia & Petugas         [/personalia] -> Manajemen akun personel & Satker
    ├── Pengaturan Satwil            [/settings/polda] -> Konfigurasi Polda & Polres jajaran
    ├── Keamanan & Pemeliharaan      [/security] -> Pintu masuk 8 sub-modul pemeliharaan terpadu:
    │   ├── Keamanan Siber           [/security]
    │   ├── Hak Akses (RBAC)         [/roles]
    │   ├── Audit Log                [/audit-logs]
    │   ├── Backup Basis Data        [/database/backup]
    │   ├── Pembersihan Berita       [/maintenance/news-cleanup]
    │   ├── Otomasi Sinkron RSS      [/maintenance/rss-sync]
    │   ├── Mode Pemeliharaan        [/maintenance]
    │   └── Konfigurasi Mail (SMTP)  [/settings/mail]
    └── Profil Saya                  [/profile] -> Kredensial dinas & PIN Cepat
```

---

## 🌓 3. Dual-Theme Engine (Mode Gelap & Mode Terang)

Aplikasi memiliki mesin tema ganda bawaan yang terpasang pada tingkat root `<html>`:
- **Mode Gelap (Command Center Dark)**: Menggunakan kelas `.dark` pada `<html>`. Latar belakang `#070d1d`, kartu `#0b1426`, teks slate terang dengan aksen gold/amber `#f59e0b`.
- **Mode Terang (Executive Light)**: Menggunakan kelas `.light` pada `<html>`. Latar belakang `#f1f5f9`, kartu `#ffffff`, border `#e2e8f0`, teks kontras tinggi `#0f172a`.
- **Anti-Flash Script**: Dijalankan secara sinkron di `<head>` pada [`resources/views/app.blade.php`](file:///d:/Foto/laragon/www/simod-enterprise/resources/views/app.blade.php) membaca `localStorage.getItem('simod_theme')` sebelum Vue ter-mount.
- **PENTING UNTUK AGENT**: Jangan membuat class styling baru yang meng-hardcode warna teks hitam pekat pada dark mode. Manfaatkan token Tailwind yang sudah dipetakan di [`resources/css/app.css`](file:///d:/Foto/laragon/www/simod-enterprise/resources/css/app.css).

---

## 📡 4. Mesin Agregasi Media Online (29 RSS Feeds) & Auto-Sync

### 4.1. Daftar 29 Portal Resmi Sulbar
Seluruh 29 portal media dikonfigurasi melalui seeder [`SulbarRssFeedsSeeder.php`](file:///d:/Foto/laragon/www/simod-enterprise/database/seeders/SulbarRssFeedsSeeder.php):
- **Regional Sulselbar (20)**: Detik Sulsel, Tribun Sulbar, ANTARA Makassar (Terkini, Top News, Daerah, Lintas Daerah), Mesakada, Mekora, Globalsulbar, Radar Sulbar, Sulbar Express, Mamuju Pos, Katinting, Kabar Sulbar, Suwarta, Sulbar Terkini, Malaqbi, Rakyat Sulbar, Bugis Pos, Bacasaja.id.
- **Nasional & Umum (4)**: Antara News Terkini, CNN Indonesia Nasional, Tribunnews (Nasional), ANTARA Makassar (Politik).
- **Hukum & Kriminal (3)**: Detikcom Hukum & Kriminal, ANTARA Makassar (Hukum), Polisiku.co.id.
- **Google News RSS (2)**: iNews Mamuju (Keyword Search), Google News - iNews Mamuju (Site Search).

### 4.2. Aturan Kritis Penarikan & Kuota Adil (*Fair Quota*)
> [!IMPORTANT]
> Media nasional (Detik, CNN, Antara) memproduksi ratusan berita harian. **DILARANG** melakukan `array_slice()` global setelah pengurutan timestamp!
- **Wajib menggunakan pengelompokan per sumber (*per-source grouping*)**: Setiap feed diberi kuota simpan adil (default 25–30 berita per feed). Hal ini menjamin portal daerah (Katinting, Malaqbi, Antara Hukum/Politik) tidak terdesak menjadi 0 berita.
- **Bypass Cloudflare & Permalinks Rusak**: Portal dengan proteksi Cloudflare (seperti `Bacasaja.id`) atau struktur tautan bermasalah (`Malaqbi.com`) disindikasikan secara aman melalui Google News RSS (`site:domain.com`).

### 4.3. Fitur Keamanan & Kestabilan di Balik Layar
- **Mutex Atomic Lock**: `Cache::lock('rss_sync_engine_lock', 600)`. Jika proses sinkronisasi sedang berlangsung, cron berikutnya akan dilewati (*skipped_locked*) untuk mencegah *server overload*.
- **Auto-Capping Berimbang**: Jika kapasitas berita non-bookmark melebihi ambang batas (`max_retained_articles_per_polda`), pemangkasan dilakukan berimbang per-sumber (`autoCapArticles` di `RssSyncService`), bukan menghapus portal daerah yang terbit berkala.
- **Bookmark Permanen**: Kolom `is_bookmarked` pada tabel `media_articles` bersifat kebal dari pemangkasan otomatis (*never pruned*).

---

## 🤖 5. Alur Analisis Intelijen AI (Tab 02, Tab 03, Tab 07)

1. **Salin Prompt AI**: Di Tab 01, tombol *"Salin Prompt AI"* merangkum konteks situasi wilayah dan berita terkini menjadi formula prompt terstandar analis intelijen siber Polri.
2. **Tempel Ringkasan AI**: Hasil keluaran ChatGPT / Claude / Gemini ditempelkan pada modal *"Tempel Ringkasan AI"*.
3. **Penyimpanan Permanen Basis Data**:
   - Controller [`MediaIntelligenceController::storeAiBriefing`](file:///d:/Foto/laragon/www/simod-enterprise/app/Http/Controllers/MediaIntelligenceController.php) mem-parsing payload JSON / naskah dan menyimpannya ke tabel **`media_intelligence_reports`**.
   - Halaman selalu memuat laporan **paling mutakhir** (`latest()->first()`) sesuai Polda yang aktif.
   - Mengisi otomatis: **Tab 02** (Executive Briefing & Respon Cepat), **Tab 03** (Polarisasi Sentimen), **Tab 05** (EWS), **Tab 06** (Counter Narasi), dan **Tab 07** (Naskah WhatsApp Siap Kirim ke Pimpinan).

---

## ⌨️ 6. Perintah CLI & Otomasi Scheduler

```bash
# Sinkronisasi berita RSS secara aman (didukung Mutex Lock)
php artisan rss:sync --polda=SULBAR

# Sinkronisasi seluruh 34 Polda (Konsolidasi Nasional)
php artisan rss:sync

# Pemangkasan retensi berita lama (menjaga bookmark)
php artisan media:prune --days=90 --polda=SULBAR

# Menjalankan seluruh test suite otomatis (19 tests, 88 assertions)
php artisan test

# Kompilasi aset Vite frontend
npm run build
```

---

## ⚠️ 7. Protokol Pengembangan untuk AI Agents

Saat Anda (Agent) diminta memodifikasi kode ini:
1. **Verifikasi Database Testing**: File `phpunit.xml` telah dikonfigurasi menggunakan SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`). Jangan mengganti konfigurasi ini agar database MySQL pengembangan lokal tidak ter-wipe saat testing.
2. **Jalankan Testing Sebelum Selesai**: Selalu jalankan `php artisan test` dan `npm run build` sebelum menyelesaikan tugas.
3. **Multi-Tenant Scoping**: Setiap query data operasional (`MediaArticle`, `Issue`, `Task`, `EarlyWarning`) **wajib** menyertakan filter `polda_id`.
4. **Preserve Docstrings**: Pertahankan semua dokumentasi, komentar PHPDoc, dan struktur kode yang ada.
