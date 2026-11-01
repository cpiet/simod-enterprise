# 📚 DOKUMENTASI RESMI SIMOD ENTERPRISE MULTI-TENANT v2.0
**Sistem Informasi Manajemen Operasional Dokumentasi & Humas POLRI**

Dokumentasi ini memandu arsitektur, seluruh perintah CLI, modul operasional, integrasi worker, basis data, panduan AI Coding Agents (Codex, Claude, Gemini, Antigravity), serta prosedur deployment untuk **SIMOD Enterprise (Laravel 12 + Inertia.js v2 + Vue 3 + Tailwind CSS v4 + Lucide Icons)**.

---

## 🗺️ Peta Navigasi Dokumen

| No | Dokumen | Ringkasan Pembahasan |
|:---|:---|:---|
| **🤖** | [AGENTS.md](../AGENTS.md) | **Instruksi Khusus AI Coding Agents (Codex, Claude, Gemini)**: Arsitektur, konvensi, aturan multi-tenant, dan panduan testing. |
| **01** | [01. Arsitektur & Multi-Tenant](01-arsitektur-dan-multi-tenant.md) | Hierarki Mabes Polri &rarr; Polda &rarr; Polres, skema basis data, dan isolasi tenant. |
| **02** | [02. Daftar Perintah Artisan & CLI](02-perintah-artisan-dan-cli.md) | Seluruh command Artisan: migrasi, seed, server, automated test suite, dan CLI scheduler. |
| **03** | [03. Media Intelligence & RSS Feeds](03-modul-media-intelligence-dan-rss.md) | Mesin agregator paralel `curl_multi`, 29 portal berita Sulbar & nasional, fair quota, live sync. |
| **04** | [04. Manajemen Isu & EWS](04-modul-manajemen-isu-dan-ews.md) | Siklus penanganan isu publik, cek fakta hoaks, dan matriks EWS 3-Fase respon taktis. |
| **05** | [05. Publikasi Kanal & Siaran Pers](05-modul-publikasi-dan-siaran-pers.md) | Orkestrasi publikasi Tribratanews, Instagram, TikTok, dan draf rilis pers pimpinan. |
| **06** | [06. Disposisi Tugas & Audit Trail](06-modul-disposisi-tugas-dan-audit-trail.md) | Penugasan operasional ke Satker/Polres, update progres lapangan, dan immutable log. |
| **07** | [07. Worker Scraper Python & REST API](07-worker-scraper-python-dan-api.md) | Integrasi background worker Python, REST API `/api/v1/ingest/article`, arsitektur scraping medsos. |
| **08** | [08. Panduan Deployment & Virtual Host](08-panduan-deployment-dan-virtual-host.md) | Konfigurasi domain `http://simod-enterprise.test` di Laragon, Apache/Nginx, dan VPS Hostinger. |
| **09** | [09. Katalog Lengkap 19 Modul & Paritas Fitur](09-katalog-lengkap-19-modul-simod-enterprise.md) | Pemetaan lengkap seluruh 19 modul operasional dan arsitektur sidebar 4 pilar terpadu. |
| **10** | [10. Daftar Lengkap 29 Sumber RSS Feed Sulbar](10-daftar-rss-feed-resmi-sulbar.md) | Rincian 29 portal resmi Sulbar & nasional, status Cloudflare bypass, dan proxy Google News. |
| **11** | [11. Radar Media Sosial & Apify](11-modul-radar-media-sosial-apify.md) | Monitoring 𝕏, TikTok, Instagram, YouTube, Multi-API Token Pool, target akun, dan thumbnail HD. |
| **12** | [12. Modul Personalia & Matriks Hak Akses (RBAC)](12-modul-personalia-dan-rbac-roles.md) | Tata kelola personel, proteksi akun master, anti-self-lockout, dan integrasi matriks 17 modul `/roles`. |
| **13** | [13. Keamanan Tenant & Mesin Intelijen AI](13-keamanan-tenant-dan-mesin-intelijen-ai.md) | Keamanan URL tenant (POST session-based), Smart Dossier Atensi Pimpinan, dan Multi-API Gemini failover. |
| **14** | [14. Katalog RSS Media Online Nasional](14-katalog-rss-media-online-nasional.md) | Registri feed media online nasional, endpoints valid, dan protokol polling ingestion pipeline. |

---

## 🚀 Ringkasan Menjalankan Aplikasi Secara Cepat

```bash
# 1. Jalankan Laragon (Apache & MySQL)
# Pastikan database 'simod_enterprise' aktif di localhost:3306

# 2. Masuk ke direktori proyek
cd d:\Foto\laragon\www\simod-enterprise

# 3. Jalankan migrasi dan seeding komplit
php artisan migrate --seed

# 4. Bangun aset frontend
npm run build

# 5. Jalankan automated test suite (72 tests, 381 assertions)
php artisan test

# 6. Jalankan sinkronisasi berita RSS aman (dengan Mutex Lock)
php artisan rss:sync --polda=SULBAR

# 7. Sinkronisasi berita nasional Divhumas Polri
php artisan rss:sync --polda=ALL
```

---
*Dikembangkan secara terpadu untuk Divisi Humas Polri & Bidang Humas Polda Jajaran.*
