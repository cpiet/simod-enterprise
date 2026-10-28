# 🐍 07. Worker Scraper Python & REST Ingest API

## 1. Arsitektur Pemisahan Tanggung Jawab (Separation of Concerns)

```
┌─────────────────────────────────┐           ┌─────────────────────────────────┐
│     PYTHON SCRAPER WORKER       │           │     LARAVEL 12 COMMAND CENTER   │
│  (scraper/media_crawler.py)     │           │  (REST Ingest API & Database)   │
│                                 │           │                                 │
│  - Playwright / Scrapy / BS4    │   HTTP    │  - POST /api/v1/ingest/article  │
│  - Crawl TikTok, IG, X, RSS     │ ───────►  │  - Validasi multi-tenant        │
│  - Deteksi sentimen & virality  │   POST    │  - Simpan ke MySQL 8            │
│  - Asynchronous background loop │           │  - Broadcast ke dashboard Vue 3 │
└─────────────────────────────────┘           └─────────────────────────────────┘
```

### Mengapa Python Dipisahkan?
- **Python**: Unggul dalam otomasi peramban tanpa kepala (*Headless Browser automation* via Playwright/Puppeteer), penanganan CAPTCHA medsos, dan pustaka Natural Language Processing (NLP) untuk analisis sentimen berbahasa Indonesia.
- **Laravel 12**: Berperan sebagai pusat komando yang tangguh, mengelola otorisasi RBAC multi-tenant, antarmuka Vue 3/Inertia yang reaktif, dan relasi basis data relasional.

---

## 2. Spesifikasi REST API Ingest

### Endpoint: `POST /api/v1/ingest/article`

#### Header Request:
```http
Content-Type: application/json
Accept: application/json
```

#### Payload JSON:
```json
{
  "polda_code": "SULBAR",
  "title": "Ditreskrimsus Polda Sulbar Ungkap Modus Penipuan Online Catut Pejabat",
  "source_name": "ANTARA SULBAR",
  "url": "https://sulbar.antaranews.com/berita/129841/siber-sulbar-ungkap-penipuan",
  "summary": "Subdit Siber Polda Sulbar menangkap pelaku sindikat penipuan online...",
  "category": "Harkamtibmas",
  "sentiment": "positif",
  "sentiment_score": 0.82,
  "virality_score": 74
}
```

#### Contoh Respons Sukses (HTTP 201 Created):
```json
{
  "status": "created",
  "message": "Artikel baru berhasil di-ingest ke sistem intelijen!",
  "data": {
    "id": 15,
    "polda_id": 1,
    "title": "Ditreskrimsus Polda Sulbar Ungkap Modus Penipuan Online...",
    "category": "Harkamtibmas",
    "sentiment": "positif"
  }
}
```

---

## 3. Menjalankan Python Worker

1. Masuk ke folder scraper:
   ```powershell
   cd d:\Foto\laragon\www\simod-enterprise\scraper
   ```
2. Instal pustaka dependensi:
   ```powershell
   pip install -r requirements.txt
   ```
3. Eksekusi worker:
   ```powershell
   python media_crawler.py
   ```
