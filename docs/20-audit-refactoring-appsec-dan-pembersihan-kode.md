# 🛡️ 20. Audit Menyeluruh, Refactoring, AppSec Hardening & Portabilitas Multi-Domain

Dokumen ini mencatat secara terperinci hasil audit mendalam, pembersihan kode mati (*dead code cleanup*), penguatan keamanan aplikasi (*AppSec hardening*), penataan ulang rute kanonikal (*clean routing anti-tampering*), penyediaan sistem *feedback* visual reaktif global, serta standarisasi portabilitas multi-domain dan multi-hosting pada **SIMOD Enterprise**.

---

## 🏛️ 1. Ikhtisar Hasil Audit & Refactoring

Audit ini dilaksanakan dengan prinsip **Zero Dead Code, Zero Gimmick, dan Zero URL Tampering**:

| Komponen | Status Sebelum Audit | Status Pasca Refactoring & Hardening |
|:---|:---|:---|
| **URL Parameters** | Banyak tautan internal membawa `?polda=SULBAR` atau `?polda=ALL` dalam plaintext | **100% Bersih & Kanonikal**: Seluruh tautan internal Vue bersih tanpa query string tenant; isolasi tenant dikelola server-side via session aman & endpoint otentikasi `POST /tenant/switch`. |
| **Dead Code / Stagnant Endpoints** | Sinkronisasi Antara Hukum menggunakan URL feed stagnan; limit payload global 1.500 memotong portal lama | **Feed Dimutakhirkan ke Sindikasi Google News Antara**; limit query dinaikkan ke 3.000; sinkronisasi matematis 100% antara hitungan dropdown dan kartu artikel di layar. |
| **Feedback Pengguna (Toasts)** | Notifikasi flash dari Laravel (`session()->get('success')`) belum tertampil visual | **Global Reactive Toast Notification System** terpasang di `EnterpriseLayout.vue` dengan auto-dismiss 4.5 detik, transisi halus, dan dukungan `CustomEvent` browser. |
| **Proteksi IDOR & Parameter Tampering** | Akses mutasi data (Issue, Task, Publication, Media) memeriksa tenant saat view | **Tenant Enforcement di Lapisan Simpan & Mutasi**: Seluruh mutasi `POST` mengunci `polda_id` dari session personel yang sedang login (`$request->user()->polda_id`) untuk mencegah manipulasi form payload. |
| **Portabilitas Multi-Hosting** | Header User-Agent scraper mengandung string statis `simod-enterprise.test` | **Portabilitas Dinamis**: User-Agent scraper membaca `config('app.url')`; trusted proxies menangani header `X-Forwarded-*`, CF-Visitor, dan auto-deteksi HTTPS. |
| **Validasi Form & Anti Double-Submit** | Form pembuatan isu menggunakan `alert()` browser standar | **Reactive Error Alert Banner** + status disabled dan indikator pemrosesan otomatis saat pengiriman form. |

---

## 🧹 2. Pembersihan Kode Mati & Rute Usang (Dead Code Cleanup)

### 2.1. Eliminasi Parameter Query Statis `?polda=...`
Seluruh rute internal pada frontend Vue dibersihkan dari penempelan parameter query plaintext:
- `resources/js/Pages/Issues/Index.vue`: Tautan dashboard, buat laporan, ekspor CSV, dan kartu detail laporan kini menggunakan rute kanonikal (`/`, `/issues/create`, `/issues/{id}`, `/issues-export-csv`).
- `resources/js/Pages/Issues/Create.vue`: Tautan batal kembali kini mengarah ke `/issues`.
- `resources/js/Pages/Issues/Show.vue`: Tombol kembali kini mengarah ke `/issues`.
- `resources/js/Pages/Reports/Index.vue`: Tautan ekspor CSV dan detail isu dibersihkan ke rute kanonikal.
- `resources/js/Pages/AuditLogs/Index.vue`: Tautan ekspor CSV diarahkan ke rute kanonikal.
- `resources/js/Pages/Gallery/Index.vue`: Tautan pill isu terkait diarahkan ke rute kanonikal.
- `resources/js/Pages/SocialIntelligence/Index.vue`: Tombol navigasi dan eskalasi 1-klik kini bersih dari query tenant.
- `resources/js/Pages/Settings/Roles.vue`: Form simpan dan reset matriks hak akses diarahkan ke endpoint bersih `/roles` dan `/roles/reset`.

### 2.2. Pembersihan Skrip Stagnan pada Feed Media
- Endpoint lama Antara Hukum & Peradilan (`https://www.antaranews.com/rss/hukum.xml`) yang berhenti diperbarui dari server pusat Antara digantikan dengan sindikasi Google News Antara (`https://news.google.com/rss/search?q=site:antaranews.com+hukum&hl=id&gl=ID&ceid=ID:id`).
- Angka kuota portal pada dropdown Radar Media Online diderivasi secara presisi dari array artikel yang dikirimkan ke view Vue, menghilangkan anomali *Menampilkan 0 dari 0*.

---

## 🔐 3. Keamanan Sesi, Autentikasi & Cache Hardening

### 3.1. Siklus Sesi Aman & Invalidasi Total saat Logout
Pada `AuthController::logout()`:
```php
Auth::guard('web')->logout();
$request->session()->invalidate();
$request->session()->regenerateToken();
$request->session()->put('simod_logged_out', true);

return redirect()->route('login')
    ->withCookie(cookie()->forget(config('session.cookie')))
    ->withCookie(cookie()->forget(Auth::guard('web')->getRecallerName()))
    ->with('success', 'Sesi dinas Anda telah diakhiri dan diamankan.');
```

### 3.2. Proteksi Middleware Guest pada Halaman Login
Rute `/login` dibungkus dengan middleware `guest`:
```php
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.post');
});
```
Jika pengguna yang telah terautentikasi mengakses `/login`, Laravel otomatis mengalihkan pengguna kembali ke dashboard (`/`).

### 3.3. Header Anti-Cache Browser (Prevent Back History)
Middleware `PreventBackHistory` disuntikkan secara global pada grup `web` di `bootstrap/app.php`:
```http
Cache-Control: no-cache, no-store, max-age=0, must-revalidate
Pragma: no-cache
Expires: Sun, 02 Jan 1990 00:00:00 GMT
```
Mencegah tombol 'Back' browser membuka kembali data sensitif atau formulir intelijen kepolisian setelah pengguna melakukan logout.

### 3.4. Otorisasi Akses & Anti-IDOR (Insecure Direct Object Reference)
- Diperkuat di `app/Http/Controllers/Controller.php` melalui method `authorizeTenantRecord(Request $request, object $record)`.
- Diterapkan pada seluruh method mutasi dan detail di `IssueController`, `TaskController`, `PublicationController`, dan `SystemModuleController`.
- Perlindungan otomatis di level `store()`: Jika pengguna adalah personel kewilayahan (Polda/Polres), nilai `polda_id` pada payload form dipaksa (*override*) menggunakan `$request->user()->polda_id`, sehingga pengguna tidak dapat menyelipkan ID wilayah lain melalui inspeksi elemen browser.

---

## 🌐 4. Portabilitas Multi-Domain & Multi-Hosting

Aplikasi telah teruji dan siap dijalankan pada berbagai skenario infrastruktur tanpa konfigurasi ulang kode program:

### 4.1. Reverse Proxy & Deteksi Skema Dinamis (`AppServiceProvider.php`)
```php
if (!$this->app->runningInConsole() && request()->getHost()) {
    $cfVisitor = request()->header('CF-Visitor', '');
    $isHttps = request()->isSecure()
        || strtolower((string) request()->header('X-Forwarded-Proto')) === 'https'
        || strtolower((string) request()->header('X-Forwarded-Ssl')) === 'on'
        || str_contains($cfVisitor, 'https')
        || str_contains((string) request()->header('X-Forwarded-Host'), 'ngrok')
        || str_contains(request()->getHost(), 'ngrok');

    if ($isHttps) {
        URL::forceScheme('https');
    }

    $scheme = $isHttps ? 'https' : request()->getScheme();
    $dynamicBaseUrl = $scheme . '://' . request()->getHttpHost();

    config(['filesystems.disks.public.url' => $dynamicBaseUrl . '/storage']);
}
```

### 4.2. Panduan Setup Environment Multi-Hosting

#### 1. Local Development (Laragon / XAMPP):
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=http://simod-enterprise.test
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
```

#### 2. Cloud VPS / Remote Server (Nginx + Let's Encrypt / Hostinger / aaPanel):
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://simod.polri.go.id
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
```

#### 3. Tunneling Presentasi & Live Demo (Ngrok):
```env
APP_ENV=local
APP_DEBUG=true
APP_URL=https://humped-obvious-blatantly.ngrok-free.dev
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true
```

---

## 🎨 5. Sistem Feedback Visual & Ergonomi UI/UX

1. **Floating Global Toast Notification**:
   - Terintegrasi di [`EnterpriseLayout.vue`](../resources/js/Layouts/EnterpriseLayout.vue) pada posisi `fixed bottom-6 right-6 z-[9999]`.
   - Menangkap otomatis pesan flash dari backend (`page.props.flash.success` dan `page.props.flash.error`).
   - Mendukung penembakan event custom dari JavaScript komponen: `window.dispatchEvent(new CustomEvent('simod-toast', { detail: { message: '...', type: 'success'|'error' } }))`.
   - Menggunakan transisi halus, indikator warna tematik (hijau zamrud untuk sukses, merah mawar untuk error), dan auto-dismiss 4.5 detik.

2. **Pencegahan Double-Submit & Spinner Loading**:
   - Seluruh tombol simpan/kirim dilengkapi status `:disabled="isSubmitting"` dan teks dinamis (misal: `Mengirim...`, `Memproses...`).
   - Formulir pembuatan laporan di [`Create.vue`](../resources/js/Pages/Issues/Create.vue) dilengkapi banner validasi reaktif sebelum data diunggah ke server.

---

## 🧭 6. Penyelarasan Executive Briefing Nasional & Navigasi Interaktif

1. **Penyelarasan Tenant-Scoping Executive Briefing di Command Center (`DashboardController.php`)**:
   - Memperbaiki query `$latestMediaReport` dan `$latestSocialReport`: Saat otoritas **DIVHUMAS / Nasional (`selectedCode === 'ALL'`)** aktif, sistem secara eksplisit mengambil laporan intelijen milik **Divisi Humas Polri (Mabes)**. Mencegah laporan kewilayahan (misal Polda Sulbar) terambil secara keliru akibat klausa `when(!$isNational, ...)` tanpa target tenant.
   - Tetap menyediakan fallback berjenjang jika suatu Polda belum memiliki laporan intelijen AI.

2. **Navigasi Interaktif 1-Klik ke Berita Asli & Naskah Eksekutif (`Dashboard.vue`)**:
   - Menambahkan tombol aksi cepat **`[ Buka Lembar Briefing & Berita ↗ ]`** pada header kartu *Executive Briefing Situasi Pemberitaan (24 Jam Terakhir)* untuk navigasi langsung ke Radar Media Online Tab 02.
   - Menjadikan seluruh kartu **Isu Strategis Terdeteksi** dapat diklik langsung (`<a>` link dengan atribut `target="_blank"`) menuju URL berita asli di portal media penerbit, disertai nama sumber media dan timestamp.
   - Menambahkan footer terarah untuk akses cepat ke **Format Naskah WhatsApp Pimpinan**.
   - Menerapkan pola yang sama pada kartu *Executive Briefing Situasi Media Sosial & Warganet* dengan tautan langsung ke Radar Media Sosial Tab Briefing.

3. **Sinkronisasi Reaktif Dropdown Portal (`MediaIntelligence/Index.vue`)**:
   - Menghitung opsi dropdown portal secara dinamis (`availablePortals`) dari memori artikel aktif untuk menjamin 100% konsistensi antara angka di dropdown dan jumlah kartu yang tampil di layar.
   - Menambahkan tombol **`[ 🔄 Sinkron Berita Terkini ]`** pada kartu empty state saat filter portal kosong.

---

## 🧪 7. Hasil Automated Test Suite

Seluruh test suite otomatis berhasil dijalankan dengan hasil **100% lulus tanpa regresi**:
```text
  Tests:    93 passed (538 assertions)   # snapshot historis; baseline terkini: lihat doc 22 §10
  Duration: 11.33s
```
Aplikasi memenuhi seluruh standar keamanan, integritas data, dan arsitektur Presisi Divisi Humas Polri.

