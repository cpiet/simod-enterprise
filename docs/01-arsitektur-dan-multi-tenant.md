# 🏛️ 01. Arsitektur Sistem & Model Multi-Tenant

## 1. Konsep Hierarki Wilayah Hukum Polri
SIMOD Enterprise dirancang dengan arsitektur **Modular Monolith Multi-Tenant** yang mencerminkan struktur komando Kepolisian Negara Republik Indonesia:

```
                      [ MABES POLRI ]
                 (Konsolidasi Nasional 34 Polda)
                               │
            ┌──────────────────┴──────────────────┐
            ▼                                     ▼
   [ POLDA SULAWESI BARAT ]              [ POLDA GORONTALO ]
   (Tenant SULBAR - WITA)                (Tenant GORONTALO - WITA)
            │                                     │
   ┌────────┴────────┐                   ┌────────┴────────┐
   ▼                 ▼                   ▼                 ▼
[Polresta Mamuju] [Polres Majene]      [Polresta Gorontalo] [Polres Boalemo]
(dan 4 Polres)                         (dan 3 Polres)
```

### Karakteristik Desain:
- **Tenant Identification**: Setiap entitas utama (`issues`, `press_releases`, `media_feeds`, `publications`, `tasks`, `early_warnings`) memiliki kolom `polda_id` (foreign key ke `poldas`).
- **Sub-Tenant (Satwil)**: Opsional terikat ke `polres_id` untuk penugasan tingkat kewilayahan.
- **Tenant Switching Instan**: Pada antarmuka Inertia.js, pergantian Polda dilakukan secara reaktif tanpa *full-page reload* melalui `POST /tenant/switch` dengan payload `{ "code": "SULBAR" }` (tanpa parameter query di URL).
- **Konsolidasi Mabes**: Kode tenant `ALL` (alias `DIVHUMAS`) mengagregasi data dari seluruh Polda terdaftar untuk memantau situasi kamtibmas nasional.

---

## 2. Struktur Tabel Basis Data (MySQL 8)

| Tabel | Fungsi & Relasi |
|:---|:---|
| **`poldas`** | Menyimpan identitas induk wilayah hukum (Nama, Kode, Region, Timezone, Kapolda, Kabid Humas). |
| **`polres`** | Menyimpan satuan kewilayahan di bawah Polda (`polda_id`). |
| **`users`** | Data personel kepolisian dengan peran (`role`: `kadiv_humas`, `super_admin_mabes`, `polda_leader`, `verifikator`, `analis`, `operator`). |
| **`early_warnings`** | Matriks peringatan dini EWS (*level: red, yellow, green*), risiko reputasi, dan protokol respon 3 fase. |
| **`media_feeds`** | Daftar sumber RSS feed lokal & nasional per Polda (`polda_id`). |
| **`media_articles`** | Artikel hasil tarikan agregator RSS maupun crawling worker Python. |
| **`issues`** | Kasus pemantauan publik, skor viralisasi, disposisi satker, hasil cek fakta, dan stempel hoaks. |
| **`press_releases`** | Draf dan siaran pers resmi berformat kop surat dinas, poin juru bicara, dan panduan komunikasi. |
| **`publications`** | Konten rilis multi-kanal (*Tribratanews, Instagram, TikTok, Facebook, Twitter, YouTube*). |
| **`tasks`** | Disposisi penugasan operasional ke personel/satker dengan batas waktu dan tingkat urgensi. |
| **`task_updates`** | Riwayat kronologis laporan progres personel pelaksana di lapangan. |
| **`audit_logs`** | Catatan audit trail imutabel (*actor, action, metadata, IP address, user agent*). |

---

## 3. Tingkat Akses (Role-Based Access Control)

Peran bawaan sistem (`RolePermission::defaultRoles()`; detail matriks 20 modul di [doc 12](12-modul-personalia-dan-rbac-roles.md)):

1. **Kadivhumas Polri (Mabes Komando Nasional)** (`kadiv_humas`):
   - Pimpinan tertinggi komando kehumasan Polri: **Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.**
   - Otoritas tertinggi dan pengawasan terpusat atas seluruh Polda dan Biro/Bagian Divhumas Polri (`polda_id = null`).
   - Akses penuh (20/20 modul) ke seluruh modul intelijen, siaran pers pimpinan, penugasan nasional, dan pengawasan satwil.
2. **Super Admin Mabes Polri** (`super_admin_mabes`):
   - Akses administratif teknis penuh lintas seluruh Polda (20/20 modul, bypass kunci tenant).
   - Menambah, mengubah status, mengelola konfigurasi multi-tenant, dan mengaudit seluruh Polda.
3. **Pimpinan Polda / Kabid Humas** (`polda_leader`):
   - Otoritas tertinggi pada satu wilayah hukum Polda (`polda_id` terkunci).
   - Mengelola feeds RSS lokal, menerbitkan Siaran Pers pimpinan, dan mendisposisikan tugas ke jajaran Polres.
4. **Verifikator / Kasubbid Penmas** (`verifikator`): validasi fakta, klarifikasi hoaks, dan penerbitan rilis (12/20 modul).
5. **Analis Intelijen Media & Siber** (`analis`): radar media online/sosial, analisis AI, dan pelaporan (8/20 modul).
6. **Operator Satwil / Sie Humas Polres** (`operator`):
   - Mengelola isu dan penugasan yang didelegasikan ke Polres terkait (`polres_id`).
   - Melaporkan perkembangan investigasi lapangan (*task updates*) (7/20 modul).

Peran dengan `polda_id` terisi (selain `kadiv_humas`/`super_admin_mabes`/`superadmin`) **terkunci** ke Polda-nya oleh `resolveActiveTenant()`.

---

## 4. Referensi Silang

- Daftar lengkap 39 tabel basis data, 120 rute, services, dan perintah CLI: [doc 22](22-peta-kode-dan-sumber-kebenaran.md).
- Cakupan "34 Polda" adalah **target arsitektur**; data awal yang di-seed saat ini adalah Divhumas Polri (Mabes) + 5 Polda (Sulbar, Gorontalo, Sulsel, Sultra, Kaltim).
