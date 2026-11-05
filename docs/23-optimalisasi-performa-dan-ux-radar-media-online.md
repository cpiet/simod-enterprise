# ⚡ 23. Optimalisasi Performa Navigasi & Penyederhanaan UX (Radar Media Online, Radar Media Sosial, Command Center)

Dokumen ini mencatat (1) penyebab dan perbaikan **lambatnya perpindahan antar-menu**, dan (2) **Tahap A** penyederhanaan antarmuka Radar Media Online (`/media-intelligence`) untuk tiga kelompok pengguna: **pimpinan**, **Polda**, dan **operator**.

---

## 1. Diagnosis Kecepatan (diukur pada basis data lokal)

Pengukuran server-side per halaman (waktu respons, jumlah query):

| Halaman | Sebelum | Sesudah | Penyebab utama |
|:---|:---|:---|:---|
| Command Center `/` | 677 ms · 134 query | **~116 ms · 60 query** | Peta nasional menjalankan ~6 query `LIKE '%kata%'` × 11 wilayah pada setiap kunjungan |
| Radar Media Online | 1.111 ms · 31 query | **~124 ms** | Rekaman Inertia DevTools + `diffForHumans()` per artikel (1.169 baris) |
| Radar Media Sosial | 350 ms | **~104 ms** | Overhead bersama (lihat di bawah) |
| Halaman lain (isu, tugas, dll.) | 55–85 ms · 25 query | **~22–60 ms · 15–19 query** | Query data bersama (`share()`) di setiap navigasi |

### Akar masalah & perbaikan
1. **Inertia DevTools aktif di `APP_ENV=local`** — merekam dan men-serialize ulang seluruh props setiap render (penyumbang terbesar, hingga ~800 ms pada halaman berpayload besar). Sekarang dimatikan lewat `INERTIA_DEVTOOLS_ENABLED=false` (`.env` dan `.env.example`). Aktifkan sementara hanya bila sedang men-debug UI.
2. **Data bersama dicache** ([HandleInertiaRequests.php](../app/Http/Middleware/HandleInertiaRequests.php)) — daftar Polda (5 mnt), navigasi + izin per peran/tenant (5 mnt), hitungan badge sidebar (15 dtk). Cache otomatis tidak basi: trait [`BumpsShellCache`](../app/Models/Concerns/BumpsShellCache.php) menaikkan kunci versi `simod.shell_version` setiap kali `Polda`, `Polres`, `MenuItem`, atau `RolePermission` berubah, sehingga kunci cache berganti seketika.
3. **Peta nasional** ([DashboardController.php](../app/Http/Controllers/DashboardController.php)) — 4 query `count` per wilayah digabung menjadi 1 query agregat, dan seluruh hasil dicache 60 detik.
4. **Format waktu artikel** — [`FastTime`](../app/Support/FastTime.php) menggantikan `Carbon::diffForHumans()`/`format()` per baris (±140 ms untuk 1.169 artikel) dengan fungsi ringan berkeluaran Bahasa Indonesia yang setara.
5. **Kompresi & cache aset** ([public/.htaccess](../public/.htaccess)) — `mod_deflate` untuk HTML/JSON/JS/CSS (payload Radar Media Online ±1,1 MB tanpa kompresi) dan `Cache-Control: immutable` untuk `/build/*` yang ber-hash. Dibungkus `IfModule` sehingga aman bila modul tidak aktif. Penting terutama untuk demo lewat Ngrok.
6. **Prefetch navigasi** ([EnterpriseLayout.vue](../resources/js/Layouts/EnterpriseLayout.vue)) — tautan sidebar desktop memakai `prefetch cache-for="30s"`: data halaman mulai diambil saat kursor berada di atas menu, sehingga klik terasa instan.

> **Catatan produksi:** sertakan `INERTIA_DEVTOOLS_ENABLED=false` (atau `APP_ENV=production`), aktifkan `php artisan config:cache` / `route:cache`, dan pastikan Nginx memakai `gzip on` untuk `application/json`.

### Cara mengukur ulang
Gunakan tab **Network** browser (waktu *Waiting for server response* dan ukuran *transferred*), atau aktifkan `DB::listen()` sementara untuk menghitung query per request. Pastikan `INERTIA_DEVTOOLS_ENABLED=false` saat mengukur.
Baseline target: halaman biasa < 100 ms server-side, Radar Media Online < 250 ms.

---

## 2. Tahap A — Penyederhanaan UX Radar Media Online

Prinsip: **satu aksi utama per layar**, aksi sekunder masuk menu `⋯`, konten dahulu kontrol belakangan, tampilan menyesuaikan **izin** pengguna.

| Area | Sebelum | Sesudah |
|:---|:---|:---|
| Header | 4 tombol berwarna (Sinkron, Format WA, **Cetak A4**, SADEWA) + badge klasifikasi + paragraf | Judul + status live; **Sinkron & Analisis** (utama, hanya untuk izin `media_intelligence_ai`), **SADEWA AI**, menu `⋯` (Format WA Pimpinan) |
| Cetak A4 | Tombol di header dan tab Briefing, modal pratinjau, kontainer cetak | **Dihapus total** (UI, modal, kontainer, CSS `@media print`) |
| KPI | 4 kartu besar (dengan angka bawaan palsu 150 / 84,2%) | **1 strip**: angka + **sparkline 7 hari** + selisih vs kemarin (▲/▼ dengan teks, bukan hanya warna). Zona Merah dan EWS dapat diklik. Angka bawaan palsu dihapus (tampil `—` bila kosong) |
| Header tab 01 | Kartu besar + paragraf promosi | Satu baris judul + **Sinkron Berita** |
| Smart Dossier | 3 lapis selalu terbuka | Satu baris **Topik Atensi Pimpinan (n kasus • m mendesak) ▾**; terbuka otomatis saat topik dipilih |
| Aksi detail topik | Lihat/Perbarui + Tarik Internet + Salin WA | Lihat/Perbarui + menu **Lainnya** (Tarik Berita Internet, Salin Naskah WA) |
| Toolbar | Pill status + 3 dropdown + cari | Pill status + cari + tombol **Filter (n)** (Portal, Kategori, Baris) dengan **chip filter aktif** yang bisa dihapus satu per satu |
| Kartu berita | Buka, Simpan, Tag, Buat Isu | **Buka**, **Simpan**, menu `⋯` (Buat Isu, Tag Kasus). **Operator**: *Buat Isu* tetap terlihat langsung |

### Tampilan menurut peran
| Peran | Tab awal | Catatan |
|:---|:---|:---|
| `kadiv_humas`, `polda_leader` | **02 Executive Briefing** | Angka & naskah lebih dulu |
| Lainnya (analis, verifikator, operator, super admin) | **01 Berita Online** | Daftar kerja |
| Tanpa izin `media_intelligence_ai` | tombol *Sinkron & Analisis* disembunyikan | Selaras dengan penegakan server-side `module:media_intelligence_ai` |

### Yang dipertahankan
- **Matriks Respons** (rencana aksi 0–2 jam / 2–6 jam / 24 jam) tetap di tab Briefing dan Strategi.
- **Paritas data UI** (hitungan dropdown dihitung dari `props.sampleArticles`) tidak berubah.
- **Tema ganda** memakai kelas yang sama dengan komponen sekitarnya.

### Data baru
Prop `trend7` dari `MediaIntelligenceController::index()` — 7 hari terakhir, tiap elemen `{ date, label, total, negatif }`, dihitung dari artikel yang sudah dimuat (tanpa query tambahan).

---

## 2b. Tahap A — Radar Media Sosial (`/social-intelligence`)

Pola yang sama dengan Radar Media Online, memakai kelas tema yang sudah dipakai halaman ini (`dark:`).

| Area | Sebelum | Sesudah |
|:---|:---|:---|
| Header | Pill klasifikasi + judul + paragraf + 5 tombol (Dashboard, Perbarui, Lacak, Sinkron & Analisis, Buat Laporan) | Judul + status engine; **Sinkron & Analisis** (utama), **Lacak Topik**, menu `⋯` (Perbarui Data tanpa AI, Buat Laporan Isu). Tombol disembunyikan bila tanpa izin `social_intelligence_sync` / `issues_create`. Tautan "← Dashboard" dihapus (sudah ada di sidebar) |
| KPI | 4 kartu | **1 strip** dengan sparkline 7 hari (percakapan, negatif), selisih vs kemarin, bar sentimen; kartu KOL dan Viral bisa diklik (pindah tab / urut paling viral) |
| Tab awal | 01 Radar | `kadiv_humas`, `polda_leader` → **02 Briefing**; lainnya → 01 Radar |
| Topik Atensi | Selalu terbuka + paragraf | Satu baris dapat dilipat; terbuka otomatis saat kasus dipilih |
| Grafik tren | 3 garis + area + titik berdenyut, selalu tampil di atas feed | **Kolom bertumpuk 7 hari** (negatif di dasar → netral → positif), angka total di atas tiap kolom, label teks "▲ Lonjakan"; **dilipat otomatis bila stabil**, terbuka bila ada lonjakan. Skor stabilitas bawaan palsu (85) dihapus |
| Toolbar | 8 kontrol sejajar | Pill status + platform, cari, tombol **Filter (n)** (Sentimen, Wilayah, Hanya KOL) + chip aktif, dan Urutan |

## 2c. Tahap A — Command Center (`/`)

| Area | Sebelum | Sesudah |
|:---|:---|:---|
| Hero | Pill klasifikasi + status + judul panjang + paragraf + **5 tombol** yang menduplikasi sidebar/KPI | Status situasi + "Command Center" + wilayah; maksimal **2 aksi** (Buat Laporan, Unduh Rekap) sesuai izin. Indeks Citra Publik tanpa nilai bawaan palsu (78% / +6% / "Positif") |
| Blok sintesis | Judul "PRESISI COMMAND INTELLIGENCE HUB" + paragraf promosi | Judul "Briefing Intelijen Pimpinan"; 3 tab (Media Online, Media Sosial, **Respon & Cooling — dipertahankan**) tidak berubah isinya |
| Kontrol peta | Segmented Nasional/Satwil + segmented Peta/Matriks + dropdown wilayah + baris 3 checkbox layer + teks status | Segmented **Nasional/Satwil**, **ikon** Peta/Matriks (tetap tersedia), dan tombol **Opsi** berisi Wilayah + Layer; status 1 baris kecil |

## 2d. Perbaikan keamanan tenant yang ditemukan saat menyentuh halaman ini
Beberapa endpoint mengambil tenant dari input klien, bukan dari sesi:
- `analyze-gemini`, uji koneksi Gemini, dan `chat` membaca `?polda=` dengan default **SULBAR** — Sinkron & Analisis di tenant Divhumas diam-diam menganalisis Sulbar, dan personel Polda lain dapat memilih Polda sembarang lewat query.
- `storeTopic`, `adoptSuggestedTopic`, dan endpoint SADEWA menerima `polda_id`/`polda_code` dari klien.
- `SocialIntelligenceController::sync/updateSettings` menerima `polda_id`; `resetRolesMatrix`/`updateRolesMatrix` menerima `polda_id`; `RssSyncController::triggerSync` menerima kode Polda dan skop `all`.

Perbaikan: tenant selalu dari `resolveActiveTenant()`; override dari klien hanya untuk Super Admin lewat helper baru `requestedPoldaOverride()` dan `scopedPoldaId()` di [Controller.php](../app/Http/Controllers/Controller.php). Frontend tidak lagi mengirim `polda` ke server. Diuji di `TenantOverrideProtectionTest`.

## 3. Panduan Grafik (untuk tahap berikutnya)

| Tempat | Pertanyaan | Bentuk |
|:---|:---|:---|
| Strip KPI | "Apakah memburuk?" | Angka + sparkline + delta (sudah diterapkan) |
| Barometer sentimen | "Berapa proporsinya?" | Satu bar bertumpuk 100% |
| Tab Briefing | "Kapan lonjakan negatif?" | Kolom harian 7 hari, bagian negatif merah, hari lonjakan diberi label teks |
| Peringkat portal | "Siapa paling banyak membahas?" | Bar horizontal 5 teratas |

Hindari donat/gauge dan 3 garis saling silang; jangan hanya mengandalkan merah-hijau (sertakan ikon/teks).

---

## 4. Pengujian
- `php artisan test` → 151 passed (905 assertions); `npm.cmd run build` tanpa galat.
- Belum tercakup otomatis: perilaku visual/interaksi menu `⋯`, panel Filter, dan Dossier. Periksa manual di browser pada tema Dark dan Light, lebar desktop dan ponsel.

## Peta situasi geografis

Command Center menggunakan Leaflet dan tile OpenStreetMap melalui HTTPS; URL aplikasi mengikuti domain yang melayani aplikasi, sehingga tidak dikunci ke domain lokal. Tile default memerlukan akses internet. Untuk penyedia tile lain, atur `VITE_MAP_TILE_URL` dan `VITE_MAP_ATTRIBUTION` di `.env`, lalu jalankan `npm.cmd run build` saat deployment. Pertahankan atribusi dan patuhi ketentuan penyedia tile. Server tile publik OpenStreetMap bersifat best-effort dan tidak menyediakan jaminan layanan atau unduhan offline massal.

Penanda nasional adalah 11 sentra representatif, dengan lokasi pusat wilayah, bukan lokasi kejadian. Enam sentra awal menggunakan data tenant terkait; lima sentra lainnya adalah perkiraan dari kata kunci berita pada tenant Divhumas, bukan lokasi terverifikasi. Data nasional dibatasi ke tenant bagi pengguna wilayah. Penanda satwil menggunakan koordinat Polres yang tersimpan; wilayah tanpa koordinat tetap tersedia di daftar. Status berasal dari indikator berita/isu yang tersedia, bukan penetapan resmi, dan data kosong dinyatakan sebagai belum ada data. Cache peta bertahan 60 detik.
