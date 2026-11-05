# 📚 DOKUMENTASI RESMI SIMOD ENTERPRISE MULTI-TENANT v2.0
**Sistem Informasi Manajemen Operasional Dokumentasi & Humas POLRI**

Dokumentasi ini memandu arsitektur, seluruh perintah CLI, modul operasional, integrasi worker, basis data, panduan AI Coding Agents (Codex, Claude, Gemini, Antigravity), serta prosedur deployment untuk **SIMOD Enterprise (Laravel 12 + Inertia.js v2 + Vue 3 + Tailwind CSS v4 + Lucide Icons)**.

---

## 🗺️ Peta Navigasi Dokumen

| No | Dokumen | Ringkasan Pembahasan |
|:---|:---|:---|
| **🤖** | [AGENTS.md](../AGENTS.md) / [CLAUDE.md](../CLAUDE.md) / [Copilot](../.github/copilot-instructions.md) | **Instruksi Khusus AI Coding Agents (Codex, Claude, Gemini, Copilot)**: Arsitektur, konvensi, aturan multi-tenant, dan panduan testing. |
| **01** | [01. Arsitektur & Multi-Tenant](01-arsitektur-dan-multi-tenant.md) | Hierarki Mabes Polri &rarr; Polda &rarr; Polres, skema basis data, dan isolasi tenant. |
| **02** | [02. Daftar Perintah Artisan & CLI](02-perintah-artisan-dan-cli.md) | Seluruh command Artisan: migrasi, seed, server, automated test suite, dan CLI scheduler. |
| **03** | [03. Media Intelligence & RSS Feeds](03-modul-media-intelligence-dan-rss.md) | Mesin agregator paralel `curl_multi`, 29 portal berita Sulbar & nasional, fair quota, live sync. |
| **04** | [04. Manajemen Isu & EWS](04-modul-manajemen-isu-dan-ews.md) | Siklus penanganan isu publik, cek fakta hoaks, dan matriks EWS 3-Fase respon taktis. |
| **05** | [05. Publikasi Kanal & Siaran Pers](05-modul-publikasi-dan-siaran-pers.md) | Orkestrasi publikasi Tribratanews, Instagram, TikTok, dan draf rilis pers pimpinan. |
| **06** | [06. Disposisi Tugas & Audit Trail](06-modul-disposisi-tugas-dan-audit-trail.md) | Penugasan operasional ke Satker/Polres, update progres lapangan, dan immutable log. |
| **07** | [07. Worker Scraper Python & REST API](07-worker-scraper-python-dan-api.md) | Integrasi background worker Python, REST API `/api/v1/ingest/article`, arsitektur scraping medsos. |
| **08** | [08. Panduan Deployment & Virtual Host](08-panduan-deployment-dan-virtual-host.md) | Konfigurasi domain `http://simod-enterprise.test` di Laragon, Apache/Nginx, dan VPS Hostinger. |
| **09** | [09. Katalog Lengkap 21 Modul & Halaman](09-katalog-lengkap-19-modul-simod-enterprise.md) | Pemetaan lengkap seluruh 21 modul/halaman operasional dan arsitektur sidebar 4 pilar terpadu. |
| **10** | [10. Daftar Lengkap 29 Sumber RSS Feed Sulbar](10-daftar-rss-feed-resmi-sulbar.md) | Rincian 29 portal resmi Sulbar & nasional, status Cloudflare bypass, dan proxy Google News. |
| **11** | [11. Radar Media Sosial & Apify](11-modul-radar-media-sosial-apify.md) | Monitoring 𝕏, TikTok, Instagram, YouTube, Multi-API Token Pool, target akun, dan thumbnail HD. |
| **12** | [12. Modul Personalia & Matriks Hak Akses (RBAC)](12-modul-personalia-dan-rbac-roles.md) | Tata kelola personel, proteksi akun master, anti-self-lockout, dan integrasi matriks 20 modul `/roles`. |
| **13** | [13. Keamanan Tenant & Mesin Intelijen AI](13-keamanan-tenant-dan-mesin-intelijen-ai.md) | Keamanan URL tenant (POST session-based), Smart Dossier Atensi Pimpinan, dan Multi-API Gemini failover. |
| **14** | [14. Katalog RSS Media Online Nasional](14-katalog-rss-media-online-nasional.md) | Registri feed media online nasional, endpoints valid, dan protokol polling ingestion pipeline. |
| **15** | [15. Manajemen Menu Dinamis & Portabilitas Multi-Domain](15-manajemen-menu-dinamis-dan-portabilitas-multi-domain.md) | Navigasi berbasis basis data (`/settings/menus`), portabilitas multi-hosting, dan keamanan sesi (`PreventBackHistory`). |
| **16** | [16. Peta Situasi Kamtibmas Nusantara & Integritas KPI](16-peta-nusantara-dan-integritas-kpi-command-center.md) | Peta Indonesia interaktif berbasis berita database (11 sentra), dual-scope map toggle, dan eliminasi KPI *gimmick*. |
| **17** | [17. Perbaikan Navigasi Tab, Kontras Keamanan, & Otomasi Sinkron Topik Atensi](17-perbaikan-kontras-keamanan-dan-penarikan-otomatis-topik-atensi.md) | Redesain tab 2 baris 5 kolom anti-truncate, pembersihan gambar stok Unsplash, default placeholder teks berita, dan otomasi sinkron Google News saat adopsi Topik Atensi. |
| **18** | [18. Optimalisasi UX Dossier & Arsitektur 4 Pilar Media Intelligence](18-optimalisasi-ux-dossier-dan-arsitektur-4-pilar-media-intelligence.md) | Eliminasi redundansi tab kuno, layout 2-kolom Smart Dossier, baki collapsible rekomendasi tren AI, command bar 1-baris, dan asisten intelijen SADEWA AI Copilot. |
| **19** | [19. Optimalisasi Radar Medsos & Sintesis AI SADEWA](19-optimalisasi-radar-media-sosial-dan-sintesis-ai-sadewa.md) | Multi-Tier Autonomous Ingestion Engine (zero-cost syndication), SADEWA Social AI Synthesis, restrukturisasi 4 pilar tab, dan sentralisasi token Apify di admin. |
| **20** | [20. Audit, Refactoring, AppSec & Portabilitas Multi-Domain](20-audit-refactoring-appsec-dan-pembersihan-kode.md) | Pembersihan kode mati, kanonikalisasi rute anti-tampering, sistem notifikasi toast reaktif global, proteksi IDOR, dan panduan multi-hosting. |
| **21** | [21. Standarisasi Multi-Agent & Sinkronisasi Lintas Model](21-standarisasi-multi-agent-dan-sinkronisasi-lintas-model.md) | Panduan interoperabilitas multi-agent (Codex, Claude, Gemini, Copilot), mapping file konfigurasi, 3 aturan baku, dan protokol hand-off. |
| **22** | [22. Peta Kode & Sumber Kebenaran](22-peta-kode-dan-sumber-kebenaran.md) | **Rujukan utama**: stack, perilaku tenant nyata, 110 rute, 36 tabel, 20 modul RBAC, seeder, CLI, pengujian, dan temuan audit dokumentasi. |
| **23** | [23. Optimalisasi Performa & UX (Radar Media, Medsos, Command Center)](23-optimalisasi-performa-dan-ux-radar-media-online.md) | Diagnosis lambatnya pindah menu (DevTools, query bersama, peta), cache versi, prefetch, serta Tahap A penyederhanaan UX per peran. |
| **24** | [24. Penyederhanaan Isu & Tugas, Publikasi, Administrasi](24-penyederhanaan-isu-tugas-publikasi-dan-administrasi.md) | Alur isu resmi tunggal dan bug alur yang diperbaiki, persetujuan publikasi, unggah galeri nyata, keamanan yang benar-benar diterapkan, serta halaman mock yang dihapus. |
| **25** | [25. Peta, Grafik dan Pantauan Instagram](25-peta-grafik-dan-pantauan-instagram.md) | Peta OpenStreetMap, perhitungan grafik media sosial, pantauan akun Instagram manual, batasan data, dan deployment. |
| **26** | [26. SADEWA AI Asisten Multi-Sumber](26-asisten-sadewa-multi-sumber-dan-riwayat-percakapan.md) | Chat SADEWA lintas Media Online, Media Sosial, dan Isu Internal; lingkup mengikuti RBAC, jawaban bersumber terverifikasi [M/S/I/E], dan riwayat privat per akun di server. |
| **27** | [27. Perbarui Briefing Command Center](27-perbarui-briefing-command-center.md) | Satu aksi untuk menyegarkan Media Online dan Media Sosial dengan progres per jalur, mode hemat Apify, dan hak akses per langkah. |

> **Konvensi:** bila ada perbedaan antara dokumen dan kode, rujukan yang berlaku adalah [doc 22](22-peta-kode-dan-sumber-kebenaran.md) dan kode itu sendiri. Dokumen bertanda *snapshot historis* (angka test/berita lama) tidak diperbarui otomatis.

---

## 🚀 Ringkasan Menjalankan Aplikasi Secara Cepat

```bash
# 1. Jalankan Laragon (Apache & MySQL)
# Pastikan database 'simod_enterprise' aktif di localhost:3306

# 2. Masuk ke direktori proyek
cd d:\laragon\www\simod-enterprise

# 3. Jalankan migrasi dan seeding komplit
php artisan migrate --seed

# 4. Bangun aset frontend
npm.cmd run build

# 5. Jalankan automated test suite (142 tests, 853 assertions)
php artisan test

# 6. Jalankan sinkronisasi berita RSS aman (dengan Mutex Lock)
php artisan rss:sync --polda=SULBAR

# 7. Sinkronisasi berita nasional Divhumas Polri
php artisan rss:sync --polda=ALL
```

---
*Dikembangkan secara terpadu untuk Divisi Humas Polri & Bidang Humas Polda Jajaran.*
