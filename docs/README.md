# 📚 DOKUMENTASI RESMI SIMOD ENTERPRISE MULTI-TENANT v2.0
**Sistem Informasi Manajemen Operasional Dokumentasi & Humas POLRI**

Dokumentasi ini memandu arsitektur, seluruh perintah CLI, modul operasional, integrasi worker Python, basis data, serta prosedur deployment untuk **SIMOD Enterprise (Laravel 12 + Inertia.js v2 + Vue 3 + Tailwind CSS v4 + Lucide Icons)**.

---

## 🗺️ Peta Navigasi Dokumen

| No | Dokumen | Ringkasan Pembahasan |
|:---|:---|:---|
| **01** | [01. Arsitektur & Multi-Tenant](01-arsitektur-dan-multi-tenant.md) | Hierarki Mabes Polri &rarr; Polda &rarr; Polres, skema basis data, dan isolasi tenant. |
| **02** | [02. Daftar Perintah Artisan & CLI](02-perintah-artisan-dan-cli.md) | Seluruh command Spark/Artisan: migrasi, seed, server, build asset, dan CLI crawler. |
| **03** | [03. Media Intelligence & RSS Feeds](03-modul-media-intelligence-dan-rss.md) | Mesin agregator paralel `curl_multi`, 14 portal berita Sulbar & nasional, live sync. |
| **04** | [04. Manajemen Isu & EWS](04-modul-manajemen-isu-dan-ews.md) | Siklus penanganan isu publik, cek fakta hoaks, dan matriks EWS 3-Fase respon taktis. |
| **05** | [05. Publikasi Kanal & Siaran Pers](05-modul-publikasi-dan-siaran-pers.md) | Orkestrasi publikasi Tribratanews, Instagram, TikTok, dan draf rilis pers pimpinan. |
| **06** | [06. Disposisi Tugas & Audit Trail](06-modul-disposisi-tugas-dan-audit-trail.md) | Penugasan operasional ke Satker/Polres, update progres lapangan, dan immutable log. |
| **07** | [07. Worker Scraper Python & REST API](07-worker-scraper-python-dan-api.md) | Integrasi background worker Python, REST API `/api/v1/ingest/article`, arsitektur scraping medsos. |
| **08** | [08. Panduan Deployment & Virtual Host](08-panduan-deployment-dan-virtual-host.md) | Konfigurasi domain `http://simod-enterprise.test` di Laragon, Apache/Nginx, dan VPS Hostinger. |

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

# 5. Jalankan server lokal (opsional bila tidak menggunakan virtual host Laragon)
php artisan serve --port=8000

# 6. Jalankan penarikan berita RSS berkala
php artisan simod:scrape-rss --polda=SULBAR
```

---
*Dikembangkan secara terpadu untuk Divisi Humas Polri & Bidang Humas Polda Jajaran.*
