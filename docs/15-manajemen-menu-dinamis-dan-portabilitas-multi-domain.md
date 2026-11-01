# 🧭 Arsitektur Menu Dinamis, Portabilitas Multi-Domain & Keamanan Sesi

Dokumen ini menjelaskan arsitektur **Database-Driven Navigation (`/settings/menus`)**, **Portabilitas Multi-Domain & Multi-Hosting**, serta **Keamanan Sesi Pasca-Logout (`PreventBackHistory`)** pada platform **SIMOD Enterprise**.

---

## 🗺️ 1. Arsitektur Navigasi & Menu 100% Dinamis (`menu_items`)

Seluruh menu pada sidebar utama (4 Pilar Komando) maupun barisan sub-tab **Keamanan & Pemeliharaan** kini dikendalikan sepenuhnya dari basis data melalui tabel `menu_items` dan model [`MenuItem`](../app/Models/MenuItem.php).

### 1.1. Skema Tabel `menu_items`
| Kolom | Tipe Data | Keterangan |
|:---|:---|:---|
| `id` | `bigint (PK)` | Primary Key item menu |
| `title` | `varchar(255)` | Nama/label menu atau judul grup pilar |
| `description` | `varchar(255)` | Sub-teks deskripsi modul |
| `route_or_url` | `varchar(255)` | Path rute tujuan (misal: `/media-intelligence`) |
| `match_patterns` | `json` | Daftar pola URL untuk highlight menu aktif |
| `exclude_patterns` | `json` | Daftar pola URL pengecualian |
| `exact_match` | `boolean` | Pencocokan URL persis (`true` / `false`) |
| `icon` | `varchar(255)` | Nama ikon Lucide (`LayoutDashboard`, `Radio`, dll.) |
| `parent_id` | `bigint (FK)` | Relasi `self-referencing` untuk hierarki Pilar &rarr; Menu &rarr; Sub-Menu |
| `menu_location` | `varchar(50)` | Lokasi render: `sidebar` atau `security_tabs` |
| `order_index` | `integer` | Urutan tampil (*ordering*) dalam grupnya |
| `permission_required` | `varchar(255)` | Kunci izin RBAC (`dashboard_view`, `security_center`, dll.) |
| `role_required` | `varchar(255)` | Pembatasan role spesifik (opsional) |
| `badge_text` | `varchar(255)` | Lencana teks statis (`LIVE`, `ONLINE`, `RADAR`) |
| `badge_key` | `varchar(255)` | Kunci metrik dinamis (`total_issues`, `pending_verify`, dll.) |
| `is_active` | `boolean` | Status visibilitas menu (`true` = tampil, `false` = disembunyikan) |

### 1.2. Panel Administrator Manajemen Menu (`/settings/menus`)
Administrator dapat mengelola seluruh navigasi secara *real-time* melalui antarmuka [`resources/js/Pages/Settings/Menus.vue`](../resources/js/Pages/Settings/Menus.vue):
- **Tambah Pilar / Modul / Sub-Menu**: Menambahkan grup baru, menu utama, atau sub-menu bertingkat.
- **Edit Rute, Label & Ikon**: Mengubah nama, URL, deskripsi, dan memilih ikon visual dari 27 ikon Lucide.
- **Ubah Urutan (*Ordering*)**: Menggeser posisi pilar maupun item menu ke atas/bawah (`POST /settings/menus/{id}/move`).
- **Toggle Status Aktif/Nonaktif**: Menyembunyikan atau mengaktifkan modul dalam 1 klik (`POST /settings/menus/{id}/toggle`).
- **Factory Reset**: Memulihkan susunan 4 Pilar & 10 Sub-Tab standar kapan saja (`POST /settings/menus/reset`).

---

## 🌐 2. Portabilitas Multi-Domain & Multi-Hosting

SIMOD Enterprise dirancang agar dapat dipindahkan antar-domain, sub-domain, VPS (Nginx/Apache/aaPanel), maupun layanan cloud/reverse-proxy (Cloudflare, AWS ELB, Ngrok) **tanpa kode URL statis (*Zero Hardcoded URLs*)**:
1. **Deteksi Otomatis Protokol & Host (`AppServiceProvider.php`)**:
   - Membaca `request()->isSecure()`, `X-Forwarded-Proto`, `X-Forwarded-Ssl`, dan `CF-Visitor` (Cloudflare) untuk mengaktifkan `URL::forceScheme('https')` secara otomatis.
   - Menyesuaikan `filesystems.disks.public.url` secara dinamis mengikuti `request()->getHttpHost()` aktif.
2. **Konfigurasi `TrustProxies` Fleksibel (`bootstrap/app.php`)**:
   - Mempercayai header proxy standar (`X_FORWARDED_FOR`, `X_FORWARDED_HOST`, `X_FORWARDED_PORT`, `X_FORWARDED_PROTO`, `X_FORWARDED_AWS_ELB`).
3. **Abstraksi Domain Sesi (`config/session.php`)**:
   - `SESSION_DOMAIN=null` secara default agar cookie sesi mengikuti host aktif tanpa terkunci pada satu domain.

---

## 🔒 3. Keamanan Siklus Sesi, Proteksi Login & Anti-Back History

1. **Invalidasi Sesi & Cookie Pasca-Logout (`AuthController::logout`)**:
   - Menjalankan `Auth::guard('web')->logout()`, `$request->session()->invalidate()`, dan `$request->session()->regenerateToken()`.
   - Menghapus cookie sesi dan *remember token* dari browser pengguna.
   - Mencatat aktivitas `auth.logout` ke dalam tabel `audit_logs`.
2. **Proteksi Rute `/login` (`guest` Middleware)**:
   - Rute `GET /login` dan `POST /login` dilindungi oleh middleware `guest`. Pengguna yang sudah login otomatis dialihkan ke `/` (Command Center).
3. **Middleware `PreventBackHistory`**:
   - Menyuntikkan HTTP response headers pada seluruh halaman terproteksi:
     ```http
     Cache-Control: no-cache, no-store, max-age=0, must-revalidate
     Pragma: no-cache
     Expires: Sun, 02 Jan 1990 00:00:00 GMT
     ```
   - Mencegah browser menampilkan halaman dari cache lokal ketika tombol **Back** ditekan setelah logout.
4. **Proteksi Direct Parameter Tampering (Multi-Tenant IDOR Guard)**:
   - Metode `authorizeTenantRecord()` pada base `Controller` memastikan personel kewilayahan tidak dapat mengakses atau memanipulasi ID laporan/tugas milik Polda lain (`HTTP 403 Forbidden`).
