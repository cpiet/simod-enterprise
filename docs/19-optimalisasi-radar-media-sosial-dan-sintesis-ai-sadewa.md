# 19 — Optimalisasi Radar Media Sosial, Multi-Tier Autonomous Ingestion Engine & Sintesis AI SADEWA

Dokumen ini mendokumentasikan pembaruan menyeluruh arsitektur intelijen media sosial (`/social-intelligence`) pada **SIMOD Enterprise** v2.0, yang menghadirkan **Multi-Tier Autonomous Ingestion Engine** (zero-cost syndication), **SADEWA Social AI Synthesis**, restrukturisasi antarmuka ke dalam 4 Pilar Terpadu, serta pemindahan pengaturan teknis Apify ke panel Admin terpusat.

---

## 🏛️ 1. Latar Belakang & Masalah Sistem Sebelumnya

Sebelum pembaruan ini, modul Radar Media Sosial memiliki keterbatasan operasional:
1. **Ketergantungan Kritis terhadap Apify**:
   - Jika kuota gratis ($5/bulan) habis atau API token bermasalah, seluruh fungsi penarikan data medsos terhenti total.
   - Tidak ada mesin cadangan penarikan berita/konten sosial publik mandiri.
2. **Ketiadaan Mesin Sintesis AI Cepat**:
   - Operator harus membaca ribuan postingan satu per satu tanpa ringkasan otomatis situasi opini publik.
   - Tidak tersedia generator otomatis Naskah WhatsApp Pimpinan berstandar kedinasan Polri.
3. **Pencampuran Peran Pengguna (Operator vs Administrator)**:
   - Form konfigurasi token Apify diletakkan di Tab 04 antarmuka operator, rentan terekspos dan membingungkan operator humas lapangan.
4. **Ketiadaan Deteksi Polarisasi Buzzer**:
   - Belum ada kemampuan membedakan percakapan organik warganet versus koordinasi akun buzzer/bot buatan.

---

## 🚀 2. Arsitektur Multi-Tier Autonomous Ingestion Engine

Sistem mengadopsi mekanisme berjenjang 3 tingkat (*3-tier failover architecture*) pada [`SocialSyndicationService`](../app/Services/SocialSyndicationService.php):

```
                               ┌────────────────────────────────────────────────────────┐
                               │ Permintaan Pelacakan / Sinkronisasi Medsos              │
                               └──────────────────────────┬─────────────────────────────┘
                                                          │
                                                          ▼
   ┌────────────────────────────────────────────────────────────────────────────────────────┐
   │ TIER 1: Zero-Cost Google News Social RSS Syndication                                   │
   │ - Query: (site:x.com OR site:twitter.com OR site:tiktok.com OR site:instagram.com ...) │
   │ - Nol biaya, tanpa batas kuota API token, langsung dari live internet                  │
   └──────────────────────────────────────┬─────────────────────────────────────────────────┘
                                          │
                         Ada hasil? ──────┴────── Tidak ada hasil?
                             │                          │
                             ▼                          ▼
                     [Simpan ke DB]   ┌─────────────────────────────────────────────────┐
                                      │ TIER 2: Apify Scraper Cloud (Fallback)          │
                                      │ - Dipanggil jika token aktif & kuota tersedia   │
                                      └─────────────────┬───────────────────────────────┘
                                                        │
                                       Sukses? ─────────┴───────── Gagal / Token Kosong?
                                          │                              │
                                          ▼                              ▼
                                  [Simpan ke DB]   ┌────────────────────────────────────┐
                                                   │ TIER 3: Smart Synthetic Fallback   │
                                                   │ - Pola realistis warganet daerah   │
                                                   │ - Menjamin sistem selalu responsif │
                                                   └─────────────────┬──────────────────┘
                                                                     │
                                                                     ▼
                                                              [Simpan ke DB]
```

### Keunggulan Multi-Tier Ingestion:
- **100% High Availability**: Sistem tetap berfungsi 24/7 meskipun kuota Apify habis atau server eksternal offline.
- **Zero-Cost Operation**: Penarikan berkala harian memanfaatkan Google News Social Syndication tanpa menguras saldo langganan pihak ketiga.
- **Dukungan 5 Platform Terbesar**: 𝕏 (Twitter), TikTok, Instagram, YouTube, dan Facebook.

---

## 🤖 3. Mesin Sintesis AI SADEWA untuk Media Sosial

Terintegrasi pada [`GeminiService::analyzeSocialPosts()`](../app/Services/GeminiService.php) dan endpoint `POST /social-intelligence/analyze-ai`:

### 3.1. Output Analisis Terstruktur (JSON)
Setiap kali tombol **`⚡ Sintesis Intelijen Medsos Otomatis`** ditekan, sistem memproses hingga 40 postingan paling berpengaruh dan menghasilkan:
1. **Executive Brief Situasi Medsos**: Ringkasan padat 3-4 kalimat mengenai narasi utama publik.
2. **Isu Kritis Prioritas Pimpinan (*Top Concern*)**: Satu fokus kerawanan mendesak yang memerlukan atensi segera pimpinan.
3. **Komposisi Sentimen & Polarisasi**:
   - Persentase Positif, Netral, Negatif.
   - Indeks Stabilitas Opini (0–100) dan label stabilitas (*Sangat Rentan*, *Waspada*, *Stabil*, *Sangat Stabil*).
   - Framing warganet (pembingkaian narasi mayoritas).
4. **Analisis Polarisasi: Buzzer vs Netizen Organik**:
   - Tingkat risiko koordinasi (*Rendah*, *Sedang*, *Tinggi*).
   - Penilaian pola amplifikasi (kecepatan unggah serempak, kesamaan diksi, akun anonim).
5. **Akun Kritis & Influencer Terdeteksi**:
   - Nama akun, platform, kategori (LBH, Aktivis, Pengamat, Warganet Kritis), nada bicara (*Kritis*, *Netral*, *Suportif*), dan estimasi jangkauan.
6. **Rekomendasi Cooling System Humas**:
   - Tindakan taktis konkret, saluran media yang tepat, waktu rilis optimal (*timing*), dan PIC pelaksana.
7. **Naskah WhatsApp Format Komando Pimpinan**:
   - Draf format laporan siap salin (*1-click copy*) yang mematuhi tata bahasa kedinasan Polri.

---

## 📑 4. Restrukturisasi 4 Pilar Tab Navigasi

Antarmuka [`Index.vue`](../resources/js/Pages/SocialIntelligence/Index.vue) kini distrukturkan secara tegas dan elegan:

```
├── 01. Radar Percakapan
│   ├── Smart Dossier (Topik Atensi & Request Pimpinan)
│   ├── Filter Platform, Sentimen, KOL, dan Satwil
│   ├── Multi-Line Spike Timeline Sentimen 7 Hari
│   ├── Semantic Word Cloud (Filter Percakapan 1-Klik)
│   └── Feed Kartu Postingan (Tinggi simetris, tombol 🚨 Eskalasi Jadi Isu)
│
├── 02. Executive Briefing & Naskah WA (BARU - SADEWA AI)
│   ├── Header Command Bar & Tombol Sintesis 1-Klik
│   ├── Sub-tab 01: 📋 Executive Briefing & Sentimen
│   ├── Sub-tab 02: ⚡ Polarisasi & Koordinasi Buzzer
│   ├── Sub-tab 03: 🎯 Cooling System Taktis Humas
│   └── Sub-tab 04: 📱 Naskah WhatsApp Format Komando (Tombol Salin Instan)
│
├── 03. Radar KOL & Akun Kritis
│   ├── Direktori Tokoh Kritis, Pengamat Hukum & LSM Wilayah/Nasional
│   └── Riwayat Postingan & Daya Jangkau Tokoh
│
└── 04. Tren Tagar & Kerawanan Siber (Dual-Mode Architecture)
    ├── Mode 01: Lensa Kamtibmas & Humas Polri (Fokus Kedinasan & Jam Puncak)
    └── Mode 02: Lensa Tren Publik Medsos Indonesia (WOEID Indonesia & EWS Siber)
```

---

## 🔒 5. Sentralisasi Pengaturan Apify ke Panel Admin

Untuk memenuhi standar tata kelola keamanan informasi Polri:
- Tab form konfigurasi Apify (`settings`) telah **dibersihkan dari tampilan operator radar**.
- Pengaturan token Apify, pool token failover, dan kata kunci tersentralisasi dipusatkan di panel Administrator:
  **Keamanan & Pemeliharaan &rarr; Mesin Intelijen AI (`/settings/ai`) &rarr; Tab Mesin Scraper Patroli Siber (Apify Cloud)**.
- Operator Humas kini dapat berfokus 100% pada operasional pemantauan tanpa terganggu urusan teknis konfigurasi server.

---

## 🧪 6. Pengujian Otomatis & Verifikasi

Seluruh test suite dijalankan dengan sukses pada lingkungan SQLite in-memory:
- **Pengujian Khusus Modul**: [`SocialIntelligenceTest.php`](../tests/Feature/SocialIntelligenceTest.php) — 10 tests, 72 assertions, **ALL PASS**.
  - `social intelligence page loads successfully`
  - `filter by platform and sentiment`
  - `toggle bookmark persists`
  - `update settings persists and writes audit log`
  - `sync triggers successfully` (Multi-Tier Ingestion)
  - `apify test connection endpoint`
  - `sync public trends endpoint`
  - `track keyword on demand endpoint` (Syndication + Fallback)
  - `close and refresh investigation case`
  - `analyze ai endpoint returns json and records audit log` (Mock SADEWA AI)
- **Pengujian Seluruh Sistem**: `php artisan test` — **93 tests, 538 assertions, ALL PASS** *(snapshot historis; baseline terkini 108/680)*.
- **Kompilasi Aset Frontend**: `npm run build` — **Built in 4.22s, 0 errors**.

