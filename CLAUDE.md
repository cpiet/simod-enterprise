# 🤖 CLAUDE.md — Panduan Instruksi & Arsitektur Resmi untuk Claude AI & Claude Code

Dokumen ini adalah **instruksi sistem resmi** yang secara otomatis dibaca oleh Anthropic Claude (Claude Code, Claude 3.5 Sonnet, Claude 3.7 Sonnet) saat bekerja pada repositori **SIMOD Enterprise**.

---

## 🏛️ 1. Ikhtisar Sistem & Core Tech Stack

- **Nama Platform**: SIMOD Enterprise (Sistem Monitoring dan Opini Publik Digital POLRI v2.0)
- **Tujuan**: Platform Command Center intelijen media siber, pemantauan opini publik, Early Warning System (EWS), agregasi multi-portal (29 portal Sulbar & 33 portal nasional Divhumas Polri), orkestrasi rilis pers, dan manajemen operasional tugas kehumasan berstandar Divisi Humas Polri & Polda Jajaran.
- **Backend**: Laravel 12 (PHP 8.2+) dengan arsitektur MVC & Service Repository.
- **Frontend**: Vue 3 (Composition API `<script setup>`), Inertia.js v2 (tanpa Blade SPA tradisional).
- **Styling**: Tailwind CSS v4 (`@tailwindcss/vite` tanpa `tailwind.config.js`). Token tema tersentralisasi di `resources/css/app.css`.
- **Basis Data**: MySQL 8.x / MariaDB (produksi lokal Laragon) + SQLite in-memory (khusus automated testing di `phpunit.xml`).
- **Icons**: `lucide-vue-next`.

---

## ⚡ 2. Perintah Penting (Development Commands)

> [!IMPORTANT]
> Pada sistem operasi **Windows / Laragon PowerShell**, selalu gunakan `npm.cmd` (bukan `npm` polos) untuk menghindari hambatan `PSSecurityException`.

```bash
# Menjalankan seluruh test suite otomatis (93 tests, 538 assertions)
php artisan test

# Menjalankan spesifik test file
php artisan test tests/Feature/CommandCenterNationalMapAndKpiTest.php

# Kompilasi aset Vite frontend untuk produksi
npm.cmd run build

# Menjalankan live server Vite saat pengembangan
npm.cmd run dev

# Sinkronisasi berita RSS secara aman (didukung Mutex Lock)
php artisan rss:sync --polda=SULBAR   # Khusus regional Sulbar (29 feed)
php artisan rss:sync --polda=ALL      # Khusus nasional Divhumas Polri (33 feed)
php artisan rss:sync                  # Konsolidasi nasional seluruh 34 Polda

# Pemangkasan retensi berita lama (menjaga bookmark)
php artisan media:prune --days=90 --polda=SULBAR

# Pencadangan basis data snapshot terkompresi
php artisan db:backup --force

# Menjalankan live tunnel Ngrok resmi untuk demo pimpinan
ngrok http --domain=humped-obvious-blatantly.ngrok-free.dev --host-header=simod-enterprise.test 80
```

---

## 🛡️ 3. Aturan Kritis Arsitektur & Keamanan (Non-Negotiable)

1. **Integritas Konfigurasi Testing (`phpunit.xml`)**:
   - `phpunit.xml` menggunakan SQLite in-memory (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`).
   - **DILARANG MENGUBAH** konfigurasi ini ke MySQL agar database lokal tidak terhapus (*wiped*) saat testing.
   - Setiap tugas wajib diverifikasi dengan menjalankan `php artisan test` dan `npm.cmd run build` sebelum selesai.

2. **Multi-Tenant Scoping & Resolusi Tenant Kanonikal**:
   - Seluruh query data operasional (`MediaArticle`, `Issue`, `Task`, `EarlyWarning`, `MediaTopic`, `Publication`) **wajib** menyertakan isolasi `polda_id`.
   - Gunakan selalu helper `[$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request)`.
   - **Keamanan URL Anti-Tampering (Zero `?polda=` Query Parameter Leaks)**:
     - Dilarang membuat link atau tombol yang mengoper parameter plaintext `?polda=...`.
     - Seluruh pergantian tenant dilakukan melalui endpoint POST terotentikasi `POST /tenant/switch` dengan payload `polda_id`.
   - **Penargetan Tenant Divhumas Mabes vs Regional**:
     - Saat lingkup adalah Nasional (`$isNational = true` atau `selectedCode === 'ALL'`), query laporan intelijen eksekutif (`latestMediaReport` dan `latestSocialReport`) **wajib secara eksplisit menargetkan `polda_id` milik Divisi Humas Polri (Mabes)**. Jangan biarkan query tanpa filter yang dapat keliru mengambil laporan wilayah daerah.

3. **100% UI Data Parity (Mencegah "0 dari 0")**:
   - Opsi hitungan dropdown di frontend Vue (misal `availablePortals`, `availableCategories`) **wajib dihitung secara dinamis via `computed()` langsung dari memori artikel (`props.sampleArticles`)**.
   - Hal ini menjamin angka yang tertulis di dropdown (misal `(20)`) pasti 100% sama dengan jumlah kartu yang tampil di layar saat portal tersebut dipilih.

4. **Proteksi IDOR & Parameter Tampering**:
   - Pada semua aksi mutasi data (`store`, `update`, `destroy`), verifikasi kepemilikan data:
     ```php
     if (!$request->user()->isSuperAdmin() && $request->user()->polda_id) {
         $validated['polda_id'] = $request->user()->polda_id;
     }
     ```

5. **Integritas Akun Master & Anti-Self Lockout**:
   - Akun master sistem (`id: 1`, `kadivhumas`, `superadmin`, `cpiet`) kebal dari penghapusan (`deleteUser`) dan deaktivasi (`toggleUserStatus`).
   - Sesi pengguna aktif dilarang menghapus akunnya sendiri atau mencabut status aktifnya sendiri.

6. **Dual-Theme Engine (Command Center Dark & Executive Light)**:
   - Command Center Dark: `.dark` pada `<html>` (latar `#070d1d`, kartu `#0b1426`, aksen gold `#f59e0b`).
   - Executive Light: `.light` pada `<html>` (latar `#f1f5f9`, kartu `#ffffff`, border `#e2e8f0`).
   - Jangan membuat class styling baru yang meng-hardcode warna teks hitam pekat pada mode gelap.

---

## 🗺️ 4. Peta 4 Pilar Navigasi SIMOD Enterprise

Sidebar navigasi di [`resources/js/Layouts/EnterpriseLayout.vue`](file:///d:/laragon/www/simod-enterprise/resources/js/Layouts/EnterpriseLayout.vue) terstruktur ke dalam 4 pilar:

```
├── 1. KOMANDO & INTELIJEN
│   ├── Command Center               [/] -> Presisi Hub, Sintesis Pimpinan, Peta Situasi & EWS
│   ├── Radar Media Online           [/media-intelligence] -> 4 Pilar (Berita, Briefing, EWS, Aksi)
│   └── Radar Media Sosial           [/social-intelligence] -> 4 Pilar (Radar, Briefing, KOL, Tren Tagar)
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
    ├── Keamanan & Pemeliharaan      [/security] -> Pintu masuk 9 sub-modul pemeliharaan:
    │   ├── Keamanan Siber           [/security]
    │   ├── Hak Akses (RBAC)         [/roles]
    │   ├── Audit Log                [/audit-logs]
    │   ├── Backup Basis Data        [/database/backup]
    │   ├── Pembersihan Berita       [/maintenance/news-cleanup]
    │   ├── Otomasi Sinkron RSS      [/maintenance/rss-sync]
    │   ├── Mode Pemeliharaan        [/maintenance]
    │   ├── Konfigurasi Mail (SMTP)  [/settings/mail]
    │   └── Mesin Intelijen AI (LLM) [/settings/ai]
    └── Profil Saya                  [/profile] -> Kredensial dinas & PIN Cepat
```

---

## 🧩 5. Panduan Modifikasi Komponen & Best Practices

- **Vue 3 Composition API**: Selalu gunakan `<script setup>`. Hindari Options API.
- **Komponen Inertia**: Gunakan `<Link>` dari `@inertiajs/vue3` untuk navigasi internal SPA, dan tag `<a>` dengan `target="_blank"` serta `rel="noopener noreferrer"` untuk tautan keluar eksternal (misal berita asli portal).
- **Feedback Reaktif**:
  - Gunakan `window.dispatchEvent(new CustomEvent('simod-toast', { detail: { message: '...', type: 'success'|'error' } }))` atau props flash dari backend untuk memicu toast notification global.
  - Tambahkan disabled state dan spinner loading saat request sedang diproses guna mencegah *double-submit*.
- **Docstrings & Clean Code**: Pertahankan semua komentar PHPDoc yang relevan, tipe data parameter, dan dokumentasi arsitektur yang sudah ada.

---
*Dokumen ini disinkronkan bersama `AGENTS.md` dan `.github/copilot-instructions.md` untuk menjamin kompatibilitas penuh antar AI Agents (Claude, Codex, Gemini, Antigravity).*
