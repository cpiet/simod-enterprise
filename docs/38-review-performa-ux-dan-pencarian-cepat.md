# 38. Review Performa, UX/UI Seluruh Modul & Pencarian Cepat (Ctrl+K)

Tanggal: 7 November 2026. Lingkup: seluruh halaman di 4 pilar (21 halaman utama), lokal dan production.

## 1. Cara pengukuran

| Pengukuran | Alat | Keterangan |
|:---|:---|:---|
| Waktu server & jumlah kueri | skrip sapu per halaman (permintaan HTML biasa, tanpa header `X-Inertia`) | dibandingkan lokal vs production |
| LCP, unduhan JS, galat konsol | Chrome headless (puppeteer-core), throttling jaringan | tiap halaman, peran Super Admin & Pimpinan Polda |
| Luapan layar ponsel | viewport 390×844 | elemen yang melebihi lebar layar |
| Kontras tema | tema terang & gelap | teks yang tidak terbaca |

## 2. Temuan & tindakan

| Temuan | Dampak | Tindakan |
|:---|:---|:---|
| Server cepat: render < 120 ms, TTFB ± 200 ms, aset brotli + cache panjang | — | tidak perlu diubah |
| Leaflet (peta) ikut di `vendor.js` (415 KB) dan dimuat di semua halaman | unduhan awal besar | dipisah ke chunk `vendor-map` (`vite.config.js`); `vendor.js` turun ke 264 KB, peta hanya dimuat di halaman yang memakainya |
| Font Plus Jakarta Sans dari Google Fonts (2 domain tambahan, blokir render) | LCP lebih lambat 100–200 ms | di-host sendiri lewat `@fontsource-variable/plus-jakarta-sans` (diimpor di `resources/js/app.js`); tautan Google Fonts dihapus dari `app.blade.php` |
| Lonceng peringatan memanggil `/social-intelligence/alerts` di setiap pindah halaman → HTTP 429 di hosting | konsol merah, lonceng kosong | penyimpanan bersama `resources/js/utils/alertsStore.js` (TTL 60 detik, permintaan ganda digabung); batas rute dinaikkan ke `throttle:120,1` |
| `/roles` meluap ke 1163 px di ponsel (checkbox `sr-only` tanpa induk `relative`) | gulir horizontal | label sakelar diberi `relative` |
| Header ponsel terpotong (nama tenant panjang mendorong tombol keluar layar) | tombol tidak terjangkau | header `min-w-0`/`shrink-0`, label tenant `truncate` bertingkat per lebar layar |
| Personalia: jumlah modul per peran ditulis manual ("16 modul", dst.) | angka bisa tidak sesuai matriks `/roles` | dihitung dari data (`granted_count`/`total_count`); bila tidak ada → `—` |
| Radar Medsos: stabilitas sentimen memakai angka bawaan bila kosong | angka karangan | `?? null`, tampil netral/`—` |

Hasil setelah perbaikan: 21 halaman tanpa galat konsol dan tanpa luapan di ponsel. Command Center masih menjalankan ± 75 kueri (± 50 ms); itu masih wajar dan tidak diubah.

## 3. Fitur baru: Pencarian Cepat (Ctrl+K / ⌘K)

Tombol **Cari… Ctrl K** di header (ikon kaca pembesar di ponsel) membuka satu kotak pencarian untuk seluruh aplikasi.

- **Server** `GET /quick-search?q=` (`App\Http\Controllers\QuickSearchController`, `throttle:60,1`):
  - **Berita** (`media_intelligence_view`): judul/ringkasan; tautan membuka portal asli di tab baru. "Lihat semua" → `/media-intelligence?q=`.
  - **Laporan isu** (`issues_view`): judul, ringkasan, kode laporan → `/issues/{id}`.
  - **Tugas** (`tasks_execute|tasks_disposition`): judul, instruksi, satuan → isu terkait atau `/tasks`.
  - **Akun pantauan** & **unggahan medsos** (`social_intelligence_view`).
  - Maks. 5 hasil per kelompok, minimal 2 huruf. Kelompok tanpa hasil tidak dikirim.
- **Keamanan**: tenant selalu dari sesi (`resolveActiveTenant` + `aggregatesNational`), sama dengan modul asalnya. Tiap kelompok hanya dicari bila peran memiliki izin modulnya (setara middleware `module:`), sehingga pengguna tidak pernah melihat data modul yang tidak boleh dibukanya. Rute sendiri cukup login karena izin diperiksa per kelompok.
- **Halaman/menu**: dicocokkan di browser dari `navigation.sidebarGroups` dan `navigation.securityTabs` yang sudah difilter izin oleh server (mis. ketik "audit" → Audit Log).
- **Keyboard**: ↑ ↓ pilih, Enter buka, Esc tutup. Mendukung tema terang & gelap.
- Test: `tests/Feature/QuickSearchTest.php` (pengelompokan & kunci tenant, saringan izin peran, panjang minimal & tamu ditolak).
