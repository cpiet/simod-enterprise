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
Authorization: Bearer <INGEST_API_TOKEN>
```
Token dibaca dari `INGEST_API_TOKEN` (`config/simod.php` → `ingest_token`). Alternatif header: `X-Ingest-Token`. Token kosong di server → HTTP **503** (fail-closed); token salah/absen → HTTP **401**. Endpoint `GET /api/v1/health` tetap publik.

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

### Endpoint Lainnya

| Method | Endpoint | Fungsi |
|:---|:---|:---|
| `POST` | `/api/v1/ingest/bulk` | Ingest banyak artikel sekaligus |
| `GET` | `/api/v1/health` | Status layanan (`status`, `system`, `version`, `timestamp`) |
| `GET` | `/api/v1/poldas` | Daftar Polda aktif beserta Polres |

### Perilaku Penting `ingest/article`
- Kunci tenant adalah **`polda_code`**; kode yang tidak dikenal akan jatuh ke Polda pertama di basis data.
- Duplikat dideteksi berdasarkan `url`. Artikel yang sudah ada tidak dibuat ulang: `virality_score` dan `sentiment` diperbarui dan respons berisi `"status": "updated"`.
- `sentiment` hanya menerima `positif`, `netral`, atau `negatif`.
- `ingest/article`, `ingest/bulk`, dan `GET /api/v1/poldas` dilindungi middleware `ingest.token` (`EnsureIngestToken`). Worker membaca token dari variabel lingkungan `SIMOD_INGEST_TOKEN` (endpoint dapat diganti via `SIMOD_API_ENDPOINT`).

---

## 3. Menjalankan Python Worker

1. Masuk ke folder scraper:
   ```powershell
   cd d:\laragon\www\simod-enterprise\scraper
   ```
2. Instal pustaka dependensi:
   ```powershell
   pip install -r requirements.txt
   ```
3. Eksekusi worker (token wajib sama dengan `INGEST_API_TOKEN` di `.env` Laravel):
   ```powershell
   $env:SIMOD_INGEST_TOKEN = "<token>"
   python media_crawler.py
   ```

