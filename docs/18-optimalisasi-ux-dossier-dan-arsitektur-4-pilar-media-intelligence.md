# 📱 18. Optimalisasi UX Dossier & Arsitektur 4 Pilar Media Intelligence

Dokumen ini mencatat pembaruan arsitektural dan penyempurnaan desain antarmuka (UX) pada modul **Radar Media Online** (`resources/js/Pages/MediaIntelligence/Index.vue`), meliputi konsolidasi tab anti-redundansi, layout 2-kolom Smart Dossier, Command Bar 1-baris, dan integrasi asisten intelijen **SADEWA AI Copilot**.

---

## 🏛️ 1. Latar Belakang & Eliminasi Redundansi (Anti-Gimmick)

Sebelumnya, navigasi Radar Media Online memiliki 7 tab yang mengalami sejumlah masalah ergonomis dan duplikasi konten:
1. **Duplikasi 100% Naskah WhatsApp**: Di bagian bawah Tab 02 sudah terdapat terminal naskah WhatsApp pimpinan lengkap dengan tombol salin, kirim, dan unduh teks. Sementara Tab 07 menampilkan teks dan tombol yang sama persis secara terpisah.
2. **Fragmentasi Data Sentimen**: Angka sentimen publik Nasional dan Kewilayahan sebelumnya dipisahkan di Tab 03, sehingga pimpinan harus berpindah tab hanya untuk melihat persentase positif/negatif dan ringkasan briefing.
3. **Penumpukan Vertikal Kotak Topik Atensi**: Kotak atensi pimpinan lama memotong judul berita dengan tanda elipsis (`...`), menumpuk tombol umum (*Semua Berita*, *Bookmark*) di dalam kotak kasus, dan menampilkan baki rekomendasi AI secara permanen yang mendorong kartu berita terlalu jauh ke bawah.

---

## 🧭 2. Arsitektur 4 Pilar Terpadu (Linear Intelligence Flow)

Seluruh 7 tab lama dikonsolidasikan secara elegan menjadi **4 Pilar Terpadu** yang mengikuti siklus intelijen kepolisian (*Monitor ➔ Telaah ➔ Waspada ➔ Aksi*):

```
┌────────────────────────────────────────────────────────────────────────────────────────────────────────────┐
│ 01. BERITA ONLINE (LIVE) │ 02. BRIEFING & SENTIMEN (ANALISIS) │ 03. EARLY WARNING (EWS) │ 04. STRATEGI HUMAS & AKSI │
└────────────────────────────────────────────────────────────────────────────────────────────────────────────┘
```

| Pilar | Label Navigasi | Badge | Deskripsi & Cakupan Data |
| :--- | :--- | :--- | :--- |
| **01** | `01. Berita Online (Live RSS)` | `LIVE` | Agregasi 29/33 portal media, Smart Dossier Topik Atensi, Ingesti Google News Live, filter Zona Merah, dan pagination berita. |
| **02** | `02. Executive Briefing & Sentimen` | `ANALISIS` | **Pusat Komando Eksekutif 1-Layar**: Ringkasan eksekutif 200 kata, Barometer Sentimen Nasional & Kewilayahan, Top Concern, Matriks Respons Taktis Bertahap (0-2 Jam, 2-6 Jam, 24 Jam), Kajian Risiko & Doktrin Humas, Isu Strategis Nasional & Wilayah, serta Terminal Naskah WhatsApp Resmi Siap Kirim (lengkap tombol salin, unduh .txt). |
| **03** | `03. Early Warning System (EWS)` | `EWS` | Khusus mengisolasi isu beresiko krisis reputasi (Level MERAH / KUNING), kajian dampak fatal tanpa mitigasi (6–24 jam), penunjukan PIC satker, dan Rencana Aksi Darurat 3-Tahap. |
| **04** | `04. Strategi Humas & Aksi` | `AKSI` | **Pedoman Tindakan Nyata**: Ide Konten Kreatif Viralisasi Positif (Tribratanews, Instagram, TikTok, X), Rekomendasi Komunikasi Publik & Panduan Juru Bicara, serta Orkestrasi Diseminasi Satwil (34 Polda / Polres jajaran). |

---

## 📂 3. Ergonomi Smart Dossier: Layout 2-Kolom Seimbang

Tampilan **Topik Atensi & Request Pimpinan** ditata ulang untuk memberikan visibilitas maksimal tanpa memakan ruang layar berlebih:

### 3.1. Badge Kasus Responsif (*Adaptive Flex Badges*)
- Judul kasus memiliki batas lebar yang leluasa (`max-w-[240px]`), mencegah pemotongan teks canggung (`...`).
- Titik indikator prioritas (*priority dot*): warna merah berkedip animasi (*pulse*) untuk kasus mendesak (*urgent*), dan amber untuk prioritas tinggi.
- Badge hitungan artikel terkait: `X Berita` dengan aksen kontras.
- Tombol reset cepat: tombol `✕ Tutup Filter Kasus` otomatis muncul saat suatu topik sedang difilter.

### 3.2. Baki Rekomendasi Tren AI Lipat (*Collapsible Smart Tray*)
- Rekomendasi tren Google News AI tidak lagi memakan ruang secara permanen.
- Diringkas menjadi tombol cerdas di header: `✨ X Rekomendasi AI`.
- Saat diklik, baki membuka secara halus menampilkan kartu-kartu rekomendasi dengan tombol 1-klik `✓ Pantau` (otomatis menarik berita Google News) dan tombol `✕` untuk mengabaikan.

### 3.3. Detail Dossier 2-Kolom Seimbang
Saat suatu topik dipilih, detail dossier disajikan dalam format grid simetris (`grid-cols-1 lg:grid-cols-12`) yang **menghemat tinggi layar hingga ~45%**:
- **Kolom Kiri (`lg:col-span-5`)**:
  - Kutipan arahan/disposisi pimpinan (*directive quote*).
  - Awan tag kata kunci pantau (`#tags`) dengan tombol hapus tag dan tambah tag.
  - Kartu mini Polarisasi Sentimen Media (Positif, Netral, Negatif %) dengan *multi-color proportional progress bar*.
- **Kolom Kanan (`lg:col-span-7`)**:
  - Toolbar Aksi Taktis: `Lihat Analisis AI` (instan dari cache), `Perbarui Analisis` (dengan badge `+X Baru`), `🌐 Tarik Berita Internet (Google News)`, dan `📱 Salin WA`.
  - Alert deteksi radar lokal jika berita belum terhimpun.
  - Kartu Ringkasan Eksekutif AI yang elegan dengan tautan buka analisis lengkap.

---

## 🎛️ 4. Command Bar Radar Terpadu (1-Baris Sempurna)

Tombol filter berita lama digabungkan ke dalam satu baris perintah terpadu (*Unified Single-Line Command Bar*):

```
[ 🌐 Semua Berita (1231) ] [ 🚨 Zona Merah (18) ] [ 🔖 Tersimpan (5) ] | Portal: [Semua] | Kategori: [Semua] | Baris: [5 Baris] | [ Cari berita.. 🔍 ] [ Cari ]
```

1. **Quick Status Pills**:
   - `🌐 Semua Berita`: Menampilkan seluruh berita radar aktif.
   - `🚨 Zona Merah`: Filter cepat 1-klik berita bersentimen negatif / rawan kamtibmas lengkap dengan titik radar berkedip.
   - `🔖 Tersimpan`: Menampilkan hanya berita yang di-bookmark (aman dari pembersihan otomatis).
2. **Dropdown Terintegrasi**: Pilihan Portal (29 portal daerah / 33 portal nasional), Kategori kepolisian, dan Pilihan Jumlah Baris Tampilan (3, 5, 8, 10, 15, 17 baris per halaman).
3. **Pencarian Instan**: Dilengkapi ikon pembersih cepat `✕` untuk mereset kata kunci dalam 1 ketukan.

---

## 🤖 5. SADEWA AI Copilot (Sistem Analisis Digital & Evaluasi Wacana Aktual)

Sebagai bagian dari orkestrasi kecerdasan buatan terpadu, sistem dilengkapi dengan **SADEWA AI Copilot**:
- **Definisi**: Copilot intelijen percakapan interaktif berbasis LLM yang tertanam langsung pada header command center.
- **Kapabilitas**:
  - Menghimpun dan menganalisis seluruh aliran berita 24 jam dari 33 media siber.
  - Menjawab pertanyaan pimpinan (*Quick-Ask*) mengenai isu terkini (contoh: perkembangan kasus tertentu, polarisasi sentimen, atau respons instansi terkait).
  - Menyusun telaah intelijen resmi berstandar Divisi Humas Polri secara otomatis.
- **Event Dispatcher Global**: Komponen frontend mana pun dapat membuka SADEWA dengan pertanyaan otomatis melalui:
  ```javascript
  window.dispatchEvent(new CustomEvent('open-sadewa-copilot', { detail: { query: 'Cari update kasus...' } }));
  ```

---

## 🧪 6. Verifikasi & Integritas Pengujian

- **Kompilasi Frontend**: `npm run build` sukses mengompilasi seluruh chunk Vite tanpa peringatan.
- **Automated Test Suite**: Seluruh **92 tests (522 assertions; snapshot historis, baseline terkini 108/680)** pada `php artisan test` lulus 100% hijau, menjamin tidak adanya regresi pada multi-tenant scoping, RBAC roles, maupun ingesti data media.

