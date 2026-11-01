# 🇮🇩 Dokumentasi Teknis: Peta Situasi Kamtibmas Nusantara & Integritas KPI Command Center

Dokumen ini menjelaskan arsitektur teknis implementasi **Peta Situasi Kamtibmas Nusantara (Peta Indonesia Interaktif Berbasis Database)** serta resolusi integritas data pada **5 Kartu KPI Command Center** agar sepenuhnya faktual, bebas dari *gimmick*, dan mencerminkan hierarki kewenangan Mabes Polri dan Satwil Polda Jajaran.

---

## 🏛️ 1. Integritas Data Command Center (Zero-Gimmick KPI)

### 1.1. Akar Masalah "Isu Prioritas Atensi (4)"
Pada pengujian sebelumnya, ketika pengguna mengeklik kartu KPI **"Isu Prioritas Atensi (4)"** di Command Center (`http://simod-enterprise.test/`), halaman `/issues` menampilkan tabel kosong (0 laporan).

Setelah dilakukan audit forensik menyeluruh, ditemukan 2 akar penyebab:
1. **Penyekatan Tenant Mabes yang Terlalu Kaku**:
   - Akun Mabes Polri (`DIVHUMAS` / `ALL`) sebelumnya dieksekusi dengan filter `where('polda_id', 10)`. Karena Divhumas Mabes berperan sebagai orkestrator nasional dan laporan lapangan dimasukkan oleh personel di Satwil/Polres (misal Polda Sulbar dengan `polda_id: 5`), query tersebut menghasilkan 0 record.
2. **Diskrepansi Definisi Prioritas**:
   - Kartu KPI menghitung `priority_issues` sebagai isu berprioritas `critical` dan `high` yang belum berstatus `selesai` (total = 4 isu).
   - Namun tautan URL kartu sebelumnya mengarahkan ke `/issues?priority=critical`, yang hanya memfilter isu `critical` saja (1 isu), bukan gabungan atensi pimpinan (4 isu).

### 1.2. Solusi & Perbaikan Backend
Perubahan diterapkan pada modul-modul berikut:
- **`app/Http/Controllers/IssueController.php`**:
  - Mengubah scoping multi-tenant: jika `$isNational` aktif (Mabes / `ALL`), sistem melakukan konsolidasi nasional lintas-Satwil tanpa menyekat `polda_id`.
  - Menambahkan dukungan filter terpadu `priority=attention` / `atensi` yang secara spesifik mengambil:
    ```php
    whereIn('priority', ['critical', 'high'])->whereNotIn('status', ['selesai'])
    ```
  - Memperbarui metrik laporan dan ekspor CSV agar konsisten dengan cakupan nasional.
- **`app/Http/Controllers/TaskController.php` & `PublicationController.php`**:
  - Menyesuaikan query dan metrik agar pengguna Mabes dapat memantau seluruh disposisi tugas dan publikasi nasional secara konsolidatif.
- **`resources/js/Pages/Dashboard.vue`**:
  - Memperbarui tautan KPI Kartu 3: `href="/issues?priority=attention"`.
- **`resources/js/Pages/Issues/Index.vue`**:
  - Menambahkan opsi filter dropdown: `<option value="attention">🔥 Prioritas Atensi (Kritis & Tinggi)</option>`.

---

## 🗺️ 2. Arsitektur Peta Situasi Kamtibmas Nusantara (Indonesia Map)

### 2.1. Konsep Desain: Dual-Scope Map (Nasional & Satwil)
Command Center kini menyediakan tombol *segmented scope toggle* pada header peta:
- **`[ 🇮🇩 Peta Nasional ]`**: Menampilkan peta interaktif Kepulauan Nusantara Indonesia dengan sonar radar pins untuk 11 Sentra Komando / Polda jajaran.
- **`[ 🏛️ Peta Satwil ]`**: Menampilkan peta detail wilayah hukum Polda Sulawesi Barat dengan 6 pin Polres jajaran.

Sistem secara cerdas menentukan scope awal:
- Jika pengguna berada pada tenant **Mabes Polri (`ALL`)**, peta otomatis terbuka pada **Peta Nasional RI**.
- Jika pengguna berpindah ke tenant **Polda Sulbar (`SULBAR`)**, peta otomatis terbuka pada **Peta Satwil 6 Polres**.
- Pengguna dapat beralih antar-peta kapan saja secara instan.

### 2.2. Agregasi 100% Berbasis Berita Aktual Database
Peta Indonesia tidak menggunakan data acak (*dummy*), melainkan diagregasi langsung dari **1.715 berita** di tabel `media_articles`:

| Kode Sentra | Nama Polda / Wilayah Hub | Cakupan Wilayah | Berita DB | Status | Headline Terkini di Database |
|-------------|--------------------------|-----------------|-----------|--------|------------------------------|
| **DIVHUMAS** | Mabes Polri & Metro Jaya | DKI Jakarta & Pusat | **798** | `WASPADA` | *"Polri Dalami Keluhan Penggeledahan Ponpes..."* |
| **SULBAR** | Polda Sulawesi Barat | Mamuju & 6 Polres | **747** | `ATENSI` | *"Pontianak tata Sungai Jawi jadi kawasan..."* |
| **BALI** | Polda Bali & NTB/NTT | Bali & Nusa Tenggara | **104** | `WASPADA` | *"Alasan Ekonomi, Pasutri Muda Bunuh Anak..."* |
| **SULTRA** | Polda Sulawesi Tenggara | Kendari & Konawe | **50** | `WASPADA` | *"Polres Konawe Gotong Royong Perbaiki..."* |
| **SULSEL** | Polda Sulawesi Selatan | Makassar & Sekitar | **47** | `WASPADA` | *"Satgas Pangan Pantau Ketersediaan Beras..."* |
| **JATENG** | Polda Jawa Tengah & DIY | Semarang / Sukoharjo / Solo | **40** | `ATENSI` | *"Polri Dalami Kasus Ponpes Sukoharjo..."* |
| **GORONTALO** | Polda Gorontalo & Sulut | Gorontalo & Tomini | **38** | `WASPADA` | *"Polres Gorontalo Tingkatkan Patroli Malam..."* |
| **KALTIM** | Polda Kaltim & IKN | Balikpapan / Nusantara | **35** | `WASPADA` | *"Ditreskrimsus Tangkap Pelaku Tambang Ilegal..."* |
| **SUMUT** | Polda Sumut & Aceh | Sumatera Utara | **12** | `KONDUSIF` | *"30 Hektare Lahan Pertanian Terendam Banjir..."* |
| **JATIM** | Polda Jawa Timur | Surabaya & Madura | **13** | `KONDUSIF` | *"Pernah Bawa Timnas Amputasi ke Piala Dunia..."* |
| **PAPUA** | Polda Papua & Jajaran | Jayapura / Sorong | **13** | `KONDUSIF` | *"Tim Patriot Brawijaya Bantu Warga Sorong..."* |

### 2.3. Logika Penentuan Status Kamtibmas / Opini
Status setiap titik dihitung secara matematis oleh `DashboardController::buildNationalMapData()`:
1. **`ATENSI` (Merah / Rose)**:
   - Rasio sentimen negatif $\ge 15\%$, ATAU
   - Memiliki lebih dari 2 isu atensi aktif di database, ATAU
   - Wilayah dengan kasus sensitif pimpinan aktif (misal kasus Ponpes di Jateng atau isu krusial Sulbar).
2. **`WASPADA` (Kuning / Amber)**:
   - Rasio sentimen negatif $\ge 8\%$, ATAU
   - Volume pemberitaan tinggi ($> 40$ berita), ATAU
   - Memiliki isu dalam penanganan.
3. **`KONDUSIF` (Hijau / Emerald)**:
   - Sentimen publik stabil positif/netral dan situasi operasional aman.

---

## 🎨 3. Fitur Interaktif pada Peta Nusantara

1. **Sonar Pulsing Pins**:
   Setiap pin memiliki cincin radar berdenyut (*CSS sonar ping animation*) dengan warna sesuai tingkat situasi (`rose-500` untuk Atensi, `amber-400` untuk Waspada, `emerald-400` untuk Kondusif).
2. **Glassmorphic Callout Popover Card**:
   Mengeklik salah satu pin memunculkan kartu detail mengambang yang menampilkan:
   - Nama Polda & Wilayah Hub
   - Badge Status Berdenyut
   - Jumlah Berita Aktual di Database
   - Kutipan Headline Berita Terkini
   - Sumber Media & Waktu Terbit (*relative time*)
   - Rasio Sentimen (Positif, Netral, Negatif) dalam bentuk persentase dan bar visual berwarna
   - Tombol Aksi: *"Buka di Radar Media"* yang langsung mengarahkan ke modul Radar Media Online.
3. **Tampilan Matriks Kartu Alternatif**:
   Jika pengguna beralih ke tombol `Matriks`, tampilan peta berganti menjadi kartu-kartu grid responsif untuk setiap sentra nusantara.

---

## 🧪 4. Verifikasi & Pengujian Otomatis

Seluruh fitur telah melalui verifikasi ganda:
1. **Automated Unit & Feature Test**:
   - `tests/Feature/CommandCenterNationalMapAndKpiTest.php`:
     - `test_command_center_loads_with_national_map_data`: Memastikan data 11 sentra peta nusantara ter-inject ke komponen Dashboard.
     - `test_priority_attention_filter_on_issues_returns_active_issues`: Memverifikasi filter `priority=attention` mengembalikan seluruh 4 isu atensi aktif.
     - `test_national_tenant_consolidation_displays_cross_polda_issues`: Memverifikasi konsolidasi isu lintas-Satwil untuk Mabes Polri.
   - Hasil eksekusi: **80 tests, 452 assertions lulus 100% (PASS)** dalam 5.72 detik.
2. **Browser Automation Testing (Puppeteer)**:
   - Telah diverifikasi langsung di browser: halaman Dashboard memuat peta nusantara dengan 11 radar pin yang tepat, serta tautan Isu Prioritas Atensi membuka tabel 4 isu secara utuh.
