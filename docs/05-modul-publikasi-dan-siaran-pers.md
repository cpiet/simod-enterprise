# 📢 05. Modul Publikasi Multi-Kanal & Siaran Pers

## 1. Orkestrasi Publikasi Multi-Kanal (`/publications`)
SIMOD Enterprise memungkinkan tim humas mengelola narasi yang disebarkan ke berbagai kanal komunikasi publik secara tersinkronisasi:

- **Tribratanews**: Website berita resmi kepolisian tingkat Polda dan Polres.
- **Instagram**: Feed edukasi grafis dan video Reels kegiatan kepolisian humanis.
- **TikTok**: Konten video pendek berdurasi 30–60 detik untuk menembus algoritma FYP generasi muda.
- **Facebook**: Informasi kamtibmas dan interaksi dengan komunitas warga lokal.
- **X (Twitter)**: Respons cepat *customer care* darurat dan klarifikasi hoaks.
- **YouTube**: Dokumentasi video panjang, podcast pimpinan, dan konferensi pers lengkap.

Setiap draf publikasi dapat ditinjau dan disetujui (*approval workflow*) sebelum tautan resminya didokumentasikan di sistem.

---

## 2. Manajemen Siaran Pers Resmi (`press_releases`)
Fitur ini berlokasi di **Tab 02 (Berita & Briefing)** pada Dashboard utama.

### Format Standar Divisi Humas Polri
Setiap rilis pers mencakup elemen wajib:
1. **Nomor Surat Dinas Resmi**: Format penomoran otomatis `B/SP-{NOMOR}/{BULAN}/HUM.6.1.1/{TAHUN}/{POLDA}`.
2. **Kop & Wilayah**: Terikat pada Polda/Polres penerbit rilis.
3. **Nama Juru Bicara**: Pejabat utama yang berwenang memberikan pernyataan (Kabid Humas / Kasi Humas Polres).
4. **Isi Pernyataan Resmi**: Kronologi peristiwa, langkah kepolisian, dan imbauan kamtibmas.
5. **Talking Points**: Poin-poin kunci yang wajib ditekankan oleh perwira saat wawancara media.
6. **Panduan DO & DON'T**: Arahan etika komunikasi publik agar personel tidak salah bicara.

### Fitur Taktis:
- Tombol **`[📋 Salin Siaran Pers]`**: Menyalin seluruh naskah rilis pers ke papan klip dalam 1 klik untuk segera disebarkan ke grup WhatsApp wartawan mitra.
- Tombol **`[+ Terbitkan Siaran Pers Baru]`**: Modal pembuat rilis instan yang langsung menyimpan data ke tabel `press_releases`.

