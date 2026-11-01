# 🛡️ Keamanan Tenant, Smart Dossier Atensi Pimpinan & Mesin Intelijen AI

Dokumen ini menjelaskan tiga peningkatan arsitektur krusial pada sistem **SIMOD Enterprise**:
1. **Keamanan URL Multi-Tenant (Anti-Tampering & Anti-Hack)**
2. **Smart Dossier / Topik Atensi Pimpinan (Pemberitaan Khusus Request)**
3. **Mesin Intelijen AI Google Gemini Pro (Multi-API Failover Pool & Auto-Sanitizer)**

---

## 🔒 1. Keamanan URL Multi-Tenant (Session-Based Switching)

### 1.1. Latar Belakang Masalah
Pada implementasi awal, pemilihan wilayah kerja/tenant menggunakan query string plaintext pada URL publik:
- `http://simod-enterprise.test/?polda=ALL` (Divhumas Polri)
- `http://simod-enterprise.test/?polda=SULBAR` (Polda Sulawesi Barat)

Format query parameter seperti ini memiliki kelemahan keamanan serius:
1. **Mudah Ditebak & Dimanipulasi (*URL Tampering*)**: Pengguna tingkat operator satwil dapat mencoba menebak kode tenant lain langsung di address bar browser.
2. **Bypass Otoritas RBAC**: Melemahkan isolasi data kedinasan jika validasi otorisasi hanya bergantung pada parameter GET.
3. **Kebocoran Referer & Log**: URL dengan parameter wilayah dapat terekspos di browser history, log proksi jaringan, dan reverse proxy publik (seperti Ngrok).

### 1.2. Solusi Arsitektur Baru: Endpoint POST Terotentikasi
Sistem beralih sepenuhnya ke model **Stateful Session-Based Tenant Switching**:
- Parameter query `?polda=...` **telah dihapus dan dibersihkan** dari seluruh rute dan tautan antarmuka.
- Perpindahan tenant wajib melalui endpoint aman:
  ```http
  POST /tenant/switch
  Content-Type: application/json
  X-CSRF-TOKEN: <token>

  {
    "polda_id": 10
  }
  ```
- **Controller Pengaman (`TenantController.php`)**:
  - Memverifikasi status login dan hak akses pengguna (`auth()->check()`).
  - Memvalidasi keberadaan Polda di database (`exists:poldas,id`).
  - **Pengecekan RBAC Ketat**: Personel daerah/satwil dilarang beralih ke tenant Mabes (`polda_id = 10` / `DIVHUMAS`) kecuali memiliki role komando Mabes (`super_admin_mabes`, `kadiv_humas`, `superadmin`).
  - Menyimpan ID tenant aktif ke dalam sesi terenkripsi server: `session(['active_polda_id' => $poldaId])`.
  - Mengarahkan kembali pengguna ke URL bersih tanpa parameter query tambahan (`return back()`).

### 1.3. Uji Keamanan Otomatis
Diuji melalui test suite `tests/Feature/TenantSecurityTest.php`:
- Memastikan perpindahan tenant via POST berhasil menyimpan sesi.
- Memastikan pengguna non-admin dicegah mengakses tenant Mabes (HTTP 403 Forbidden).
- Memastikan query parameter `?polda=...` diabaikan atau dibersihkan secara otomatis.

---

## 📁 2. Smart Dossier / Topik Atensi Pimpinan

### 2.1. Latar Belakang Kebutuhan
Seringkali pimpinan Polri (Kadiv Humas, Kapolda, atau Kabid Humas) memberikan instruksi atensi khusus untuk memantau perkembangan kasus tertentu yang sedang viral atau sensitif (misalnya: *"Update berita kasus Penggerebekan Pesantren"* atau *"Operasi Zebra"*). Jika hanya mengandalkan kliping umum, berita atensi pimpinan akan tenggelam di antara ratusan berita harian lainnya.

### 2.2. Implementasi Fitur Smart Dossier
Pada halaman **Radar Media Online** (`/media-intelligence`):
1. **Dossier Quick-Tags & Atensi Pimpinan**:
   - Disediakan filter cepat berkas atensi pimpinan di samping bilah pencarian.
   - Kata kunci atensi (contoh: *"Pesantren"*, *"Narkoba"*, *"Korupsi"*, *"Operasi Lilin"*) langsung memfilter artikel terkait dari 29 feed secara instan.
2. **Konteks Analisis Khusus untuk Gemini Pro**:
   - Saat Smart Dossier aktif, tombol *Analisis Otomatis* mengarahkan Google Gemini untuk menyusun **Laporan Khusus Kasus Atensi** yang memuat:
     - Kronologi pemberitaan media.
     - Sentimen publik terhadap institusi Polri dalam penanganan kasus tersebut.
     - Potensi risiko/eksploitasi isu oleh pihak tidak bertanggung jawab.
     - Rekomendasi taktis *press release* & *doorstop interview* untuk juru bicara Polri.
3. **Eskalasi 1-Klik Menjadi Isu Resmi**:
   - Berita dalam Smart Dossier dapat langsung dieskalasikan menjadi berkas investigasi di menu **Laporan Isu & EWS** (`/issues/create`).

---

## 🤖 3. Mesin Intelijen AI & Google Gemini Failover Pool

### 3.1. Arsitektur Multi-API Failover Pool
Untuk mendukung pemantauan intelijen 24 jam nonstop tanpa terganggu batas kuota gratis (*HTTP 429 Too Many Requests*):
- Pengguna dapat mendaftarkan beberapa API Key Google Gemini (dari akun Google AI Studio berbeda) ke dalam satu pool tersentralisasi di `/settings/ai`.
- **Failover Seketika**: Kunci baris pertama bertindak sebagai **Kunci Utama**. Jika terjadi kegagalan jaringan atau kuota habis, `GeminiService` otomatis memutar (*rotate*) permintaan ke Kunci Cadangan #2, #3, dst.

### 3.2. Auto-Sanitizer & Validasi Ketat API Key
Kesalahan umum manusia adalah adanya spasi, baris kosong, atau karakter terpotong saat menyalin API key dari Google AI Studio. Sistem telah dilengkapi:
1. **Backend Regex Auto-Sanitize**:
   - Memindai pola resmi `AIzaSy[A-Za-z0-9_-]{33}` secara otomatis.
   - Menghilangkan spasi liar, garis miring, atau karakter non-standar sebelum diproses.
2. **Indikator Peringatan Dini Realtime di Frontend**:
   - Textarea mendeteksi ketidaksesuaian panjang karakter (wajib persis 39 karakter) dan menyala merah jika terdeteksi spasi.
3. **Tombol "Pulihkan Kunci DB"**:
   - Memungkinkan admin mengembalikan kunci valid dari basis data hanya dengan satu klik jika terjadi kesalahan pengetikan di form.
4. **Alat Pengujian Pool Realtime ("Uji Koneksi Pool Kunci")**:
   - Mengetes setiap kunci satu per satu secara paralel.
   - Menampilkan latensi milidetik (ms) dan status koneksi (*Terhubung* / *Gagal*) per kunci individual.

### 3.3. Formula Analisis Senior Media Analyst Humas Polri
Prompt analisis AI telah distandarisasi untuk memposisikan model sebagai:
> *"Senior Media Analyst & Praktisi Kehumasan Polri yang humanis, objektif, transparan, dan berbasis data faktual."*

Hasil analisis menghasilkan:
- **Executive Briefing**: Intisari situasi Kamtibmas nasional/regional.
- **Dampak & Risiko**: Bahaya laten atau eskalasi gangguan keamanan jika isu tidak dimitigasi sejak dini.
- **Polarisasi Sentimen**: Rasio sentimen Positif, Netral, dan Negatif.
- **Rekomendasi Taktis & Counter-Narasi**: Langkah konkret juru bicara Polri.
- **Naskah WhatsApp Siap Kirim**: Draf pesan singkat berformat dinas untuk laporan cepat ke pimpinan.
