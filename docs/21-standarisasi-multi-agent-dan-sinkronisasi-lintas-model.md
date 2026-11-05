# 🤖 21. Standarisasi Multi-Agent & Sinkronisasi Lintas Model (Codex, Claude, Gemini, Antigravity)

Dokumen ini menjelaskan arsitektur interoperabilitas multi-agent pada **SIMOD Enterprise**, memungkinkan pengembang menggunakan berbagai platform Coding AI (OpenAI Codex, Anthropic Claude Code, Google Gemini / Antigravity, GitHub Copilot, Cursor, DeepSeek) secara bergantian atau bersamaan tanpa risiko konflik arsitektur, degradasi pengujian, atau perusakan basis data lokal.

---

## 🏛️ 1. Latar Belakang & Filosofi Desain Multi-Agent

Dalam pengembangan perangkat lunak modern berbasis kecerdasan buatan, pengembang sering kali memanfaatkan kekuatan unik dari beberapa model AI sekaligus:
- **Anthropic Claude 3.5 / 3.7 Sonnet (via Claude Code)**: Sangat presisi dalam refactoring logika frontend Vue 3, analisis alur komponen reaktif, dan integrasi Tailwind CSS v4.
- **OpenAI Codex / GitHub Copilot / Cursor**: Cepat dalam autocompletion, scaffolding controller backend Laravel, migrasi skema basis data, dan penulisan unit/feature test PHPUnit.
- **Google DeepMind Antigravity / Gemini 2.5 Pro**: Unggul dalam penalaran arsitektural multi-tier, orkestrasi MCP (Model Context Protocol), verifikasi browser visual (Chrome DevTools/Puppeteer), dan automasi deployment command center.

Agar seluruh agent tersebut bekerja pada **satu rel arsitektur yang sama**, SIMOD Enterprise menerapkan sistem pemetaan konfigurasi multi-agent tersentralisasi.

---

## 🗺️ 2. Peta Berkas Konfigurasi Spesifik Tiap Agent

Setiap platform agentic AI memiliki konvensi lokasi pembacaan sistem prompt yang berbeda. SIMOD Enterprise menyediakan berkas resmi pada titik masuk masing-masing:

```
simod-enterprise/
├── CLAUDE.md                           <-- Dibaca otomatis oleh Anthropic Claude Code
├── AGENTS.md                           <-- Standar universal sistem & Google Antigravity / Gemini
├── .github/
│   └── copilot-instructions.md         <-- Dibaca otomatis oleh GitHub Copilot, OpenAI Codex & Cursor
└── docs/
    └── 21-standarisasi-multi-agent-dan-sinkronisasi-lintas-model.md  <-- Panduan teknis komprehensif
```

### Matriks Dukungan & Perilaku Agent

| Platform AI Agent | File Konfigurasi Masuk | Tanggung Jawab & Fokus Utama |
|:---|:---|:---|
| **Anthropic Claude Code** | [`CLAUDE.md`](../CLAUDE.md) | Otomasi CLI terminal, verifikasi linter, audit reaktivitas Inertia/Vue, dan eksekusi tes mandiri. |
| **OpenAI Codex / Copilot** | [`.github/copilot-instructions.md`](../.github/copilot-instructions.md) | Penulisan kode PHP 8.2+ modern, typed properties, prepared statement, dan scaffolding model/seeder. |
| **Google Gemini / Antigravity** | [`AGENTS.md`](../AGENTS.md) | Orkestrasi 5 MCP tools (SSH, Chrome DevTools, Filesystem, Memory, Puppeteer), live test execution, dan deep auditing. |
| **Cursor AI** | [`.github/copilot-instructions.md`](../.github/copilot-instructions.md) / `.cursorrules` | Inline editing, context-aware indexing pada folder `app/` dan `resources/js/`. |

---

## 🛡️ 3. Tiga Rukun Utama (*Rules of Engagement*) untuk Semua Agent

Setiap AI agent yang masuk ke repositori ini **wajib mematuhi 3 aturan non-negotiable**:

### 1. Perlindungan Mutlak Basis Data Testing (`phpunit.xml`)
- Konfigurasi testing telah dikunci pada SQLite in-memory:
  ```xml
  <env name="DB_CONNECTION" value="sqlite"/>
  <env name="DB_DATABASE" value=":memory:"/>
  ```
- **DILARANG KERAS**: Mengubah konfigurasi ini menjadi `mysql` atau database fisik. Pengujian SQLite in-memory menjamin database MySQL lokal Laragon (`simod_enterprise`) yang berisi 29 feed RSS resmi dan data riil tetap aman dan tidak terhapus (*wiped*) saat automated testing dijalankan.

### 2. Standar Eksekusi Windows / Laragon (`npm.cmd`)
- Lingkungan kerja lokal berjalan di Windows 11 / PowerShell dengan Laragon.
- Jalankan perintah npm menggunakan executable Windows:
  ```bash
  npm.cmd run build   # BUKAN 'npm run build' polos yang dapat terblokir execution policy
  npm.cmd run dev
  ```

### 3. Kewajiban Lulus Pengujian (*Zero Regression Standard*)
- Sebelum mengakhiri tugas atau membuat git commit, agent wajib menjalankan:
  ```bash
  php artisan test
  npm.cmd run build
  ```
- Seluruh **93 tests (538 assertions)** harus berstatus **PASS (100% hijau)**. Jika ada test yang gagal, perbaiki kode sebelum melapor ke pengguna.

---

## 🧭 4. Standar Bisnis Proses & Arsitektur yang Harus Dijaga Bersama

### A. Isolasi Multi-Tenant & URL Kanonikal
- Dilarang membuat navigasi atau form yang membawa query string plaintext seperti `?polda=SULBAR`.
- Seluruh peralihan tenant dilakukan melalui endpoint terproteksi:
  `POST /tenant/switch` dengan payload `polda_id`.
- Setiap query data operasional wajib menyertakan filter `polda_id`.

### B. Penargetan Tenant Divhumas Mabes pada Command Center (`DashboardController.php`)
- Saat sesi berada dalam cakupan **Nasional (`selectedCode === 'ALL'` atau `DIVHUMAS`)**, query laporan intelijen eksekutif (`latestMediaReport` dan `latestSocialReport`) **secara eksplisit menargetkan `polda_id` milik Divisi Humas Polri (Mabes)**:
  ```php
  $targetPoldaId = $poldaId;
  if ($isNational || $selectedCode === 'DIVHUMAS') {
      $divhumasPolda = Polda::where('code', 'DIVHUMAS')->first();
      $targetPoldaId = $divhumasPolda?->id ?? $poldaId;
  }
  $latestMediaReport = MediaIntelligenceReport::where('polda_id', $targetPoldaId)
      ->latest('report_date')->latest('id')->first();
  ```
- Mencegah laporan daerah (seperti Polda Sulbar) terambil secara keliru di dashboard pimpinan Mabes.

### C. 100% UI Data Parity (Eliminasi False "0 dari 0")
- Angka hitungan pada dropdown portal/kategori di Vue (`availablePortals`, `availableCategories`) harus dihitung secara reaktif (`computed`) langsung dari array artikel di memori browser (`props.sampleArticles`).
- Menjamin angka yang tertulis di dropdown (misal `20`) pasti menampilkan 20 artikel saat dipilih.

### D. Tautan Interaktif Berita Asli & Naskah WA
- Seluruh kartu isu strategis pada *Executive Briefing* Command Center wajib menggunakan tag tautan aktif (`<a>` link dengan atribut `target="_blank"`) yang mengarahkan langsung ke URL artikel berita asli di portal penerbit.
- Tombol aksi cepat `[ Buka Lembar Briefing & Berita ↗ ]` mempermudah akses langsung ke Radar Media Online Tab 02.

---

## 🔄 5. Alur Serah Terima Pekerjaan Antar-Agent (*Agent Hand-Off*)

Ketika Anda beralih dari satu model AI ke model AI lainnya (misal: Antigravity &rarr; Claude Code &rarr; Codex):

1. **Langkah 1: Periksa Status Git**
   ```bash
   git status
   ```
   Pastikan tidak ada modifikasi berkas yang tertinggal dalam kondisi uncommitted secara tidak sengaja.
2. **Langkah 2: Verifikasi Integritas Sistem**
   ```bash
   php artisan test
   ```
   Pastikan 93 tests lulus sempurna sebagai *baseline* sebelum melakukan perubahan baru.
3. **Langkah 3: Lakukan Perubahan & Dokumentasikan**
   Lakukan penambahan fitur atau perbaikan kode sesuai instruksi di [`AGENTS.md`](../AGENTS.md) atau [`CLAUDE.md`](../CLAUDE.md).
4. **Langkah 4: Kompilasi & Verifikasi Ulang**
   ```bash
   npm.cmd run build
   php artisan test
   ```
5. **Langkah 5: Commit Menggunakan Conventional Commits**
   ```bash
   git add .
   git commit -m "feat(module): description of changes"
   git push origin main
   ```

Dengan protokol ini, setiap AI coding agent dapat melanjutkan pekerjaan agent sebelumnya secara mulus tanpa *knowledge loss* atau regresi arsitektur.

