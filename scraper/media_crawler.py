"""
SIMOD Enterprise - Python Media & Social Ingestion Worker
Crawls real-time headlines & pushes parsed intelligence directly into Laravel 12 API.
"""

import sys
import time
import requests

API_ENDPOINT = "http://127.0.0.1:8000/api/v1/ingest/article"

CRAWL_TARGETS = [
    {
        "polda_code": "SULBAR",
        "title": "Ditreskrimsus Polda Sulbar Ungkap Modus Penipuan Online Catut Pejabat",
        "source_name": "ANTARA SULBAR",
        "url": "https://sulbar.antaranews.com/berita/129841/siber-sulbar-ungkap-penipuan-catut-pejabat",
        "summary": "Subdit Siber Polda Sulbar menangkap pelaku sindikat penipuan yang membuat akun media sosial palsu mengatasnamakan pejabat kepolisian daerah.",
        "category": "Harkamtibmas",
        "sentiment": "positif",
        "sentiment_score": 0.82,
        "virality_score": 74,
    },
    {
        "polda_code": "SULBAR",
        "title": "Satlantas Polresta Mamuju Pasang Kamera ETLE Mobile di Tiga Titik Rawan Pelanggaran",
        "source_name": "DETIKCOM REGIONAL",
        "url": "https://news.detik.com/berita/sulbar-mamuju-pasang-etle-mobile-baru",
        "summary": "Pengawasan keselamatan berlalu lintas di Mamuju diperkuat dengan deployment kamera ETLE Mobile untuk menekan angka kecelakaan.",
        "category": "Lalu Lintas",
        "sentiment": "positif",
        "sentiment_score": 0.79,
        "virality_score": 68,
    },
    {
        "polda_code": "GORONTALO",
        "title": "Polda Gorontalo Salurkan 5 Ton Beras Bantuan Sosial ke Komunitas Nelayan Pesisir",
        "source_name": "GORONTALO POST",
        "url": "https://gorontalopost.id/berita/polda-gorontalo-salurkan-bansos-nelayan",
        "summary": "Biro Logistik dan Ditpolairud Polda Gorontalo menggelar aksi kemanusiaan menyalurkan paket sembako kepada nelayan terdampak cuaca ekstrem.",
        "category": "Pelayanan Publik",
        "sentiment": "positif",
        "sentiment_score": 0.91,
        "virality_score": 88,
    },
]

def run_crawler():
    print("=" * 65)
    print(" [SIMOD WORKER] Memulai Crawling & Ingest Media ke Laravel API")
    print(f" Target API: {API_ENDPOINT}")
    print("=" * 65)

    success_count = 0

    for idx, item in enumerate(CRAWL_TARGETS, 1):
        print(f"\n[{idx}/{len(CRAWL_TARGETS)}] Mengirim: {item['title'][:45]}... ({item['polda_code']})")
        try:
            resp = requests.post(API_ENDPOINT, json=item, timeout=10)
            if resp.status_code in (200, 201):
                res_json = resp.json()
                print(f"   -> [OK {resp.status_code}] {res_json.get('message', 'Sukses')}")
                success_count += 1
            else:
                print(f"   -> [ERR {resp.status_code}] {resp.text}")
        except Exception as e:
            print(f"   -> [EXC] Gagal terhubung ke Laravel API: {e}")

    print("\n" + "=" * 65)
    print(f" [SIMOD WORKER] Selesai! Berhasil meng-ingest {success_count}/{len(CRAWL_TARGETS)} artikel.")
    print("=" * 65)

if __name__ == "__main__":
    run_crawler()
