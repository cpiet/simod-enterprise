# 37. Command Center: Peta Situasi & Pemberitaan

Tanggal: 7 November 2026. Menggantikan peta nasional lama (doc 29 §peta).

## 1. Masalah sebelumnya
- Hanya 11 titik dengan pengelompokan buatan ("Polda Sumut & Aceh", "Jateng & DIY", "Bali & NTB/NTT", …). Kata kunci "Sumat" juga menghitung Sumatera Barat/Selatan.
- Berita dihitung **sepanjang masa**, sehingga status tidak mencerminkan situasi terkini.
- Aturan status tanpa makna: lebih dari 40 berita langsung "WASPADA" walau seluruhnya positif.
- Klik titik hanya menampilkan satu judul **tanpa tautan** di kotak di bawah peta, dan tombolnya membuka Radar Media Online tanpa saringan.
- Panel sekitar: "Top Isu" diurutkan dan diberi label `virality_score` yang selalu 0; "Top Viral Medsos" diurutkan viralitas yang kini `null` (doc 34); "Radar Berita 29 RSS" angka 29 ditulis mati; jam berita diberi label WITA dari jam peramban.

## 2. Sekarang
### Peta nasional (tenant Divhumas/nasional): `App\Services\SituationMapService`
- **38 provinsi**, titik = ibu kota provinsi (bukan lokasi kejadian).
- Pemberitaan **7 hari** terakhir tenant (Divhumas saja; seluruh Polda hanya bila `SIMOD_NATIONAL_AGGREGATE=true`).
- Pencocokan **kata utuh** nama provinsi, singkatan, dan kota utama pada judul + ringkasan, dengan pengecualian agar tidak tumpang tindih (Riau ≠ Kepulauan Riau, Maluku ≠ Maluku Utara, Papua ≠ provinsi Papua lain). Satu berita dapat menyebut beberapa provinsi. Jumlah berita yang tidak menyebut wilayah ditampilkan.
- Status (definisi tampil di UI):

| Status | Definisi |
|:---|:---|
| ATENSI | ≥ 3 berita negatif dan ≥ 30% berita wilayah itu negatif |
| WASPADA | ada berita negatif, di bawah ambang ATENSI |
| KONDUSIF | ada berita, tidak ada yang negatif |
| BELUM ADA DATA | tidak ada berita yang menyebut wilayah (bukan berarti aman) |

- Cache 5 menit; satu kueri 7 hari (maks. 5.000 berita) lalu pencocokan di PHP.

### Peta Polres (tenant Polda)
Titik Polres dari matriks Satwil (status dari laporan isu, doc 29), kini dengan 5 berita terbaru per Polres (7 hari) dan pintasan pencarian.

### Komponen `SituationMap.vue`
- Peta selebar penuh dengan **panel samping**. Tanpa pilihan, panel menampilkan "Wilayah paling disorot" (status terberat, lalu laporan aktif, lalu jumlah berita), definisi status, dan daftar semua wilayah. Klik titik atau wilayah menampilkan alasan status, bilah sentimen, **5 berita terbaru dengan tautan ke sumber**, dan tombol "Lihat semua di Radar Media Online" (`?q="Nama Provinsi"`) serta "Laporan Polres" (tenant Polda).
- Ukuran titik = jumlah berita; warna = status; titik terpilih disorot. Legenda menampilkan jumlah wilayah per status.

### Panel di bawah peta
- **Isu aktif teratas**: hanya isu aktif, diurutkan prioritas (kritis → rendah) lalu terbaru; menampilkan prioritas, kategori, dan waktu; dapat diklik ke detail isu.
- **Unggahan terpopuler (7 hari)**: hanya unggahan bermetrik **nyata** dari akun pantauan (doc 35) dan radar, diurutkan suka + komentar + bagikan; dapat diklik ke unggahan asli.
- **Berita terbaru**: label jujur, waktu relatif yang benar, tautan `rel="noopener noreferrer"`.
- Komposisi sentimen medsos: tayangan/suka tampil "—" bila tidak diketahui.

## 3. Pengujian
`SituationMapTest` (kata utuh + pengecualian + jendela 7 hari + berita tanpa wilayah; aturan status, tautan berita, pintasan Radar; isu aktif & unggahan bermetrik nyata), penyesuaian `CommandCenterNationalMapAndKpiTest`, `CommandCenterRealDataTest`, `DivhumasIsolationTest`. Diuji juga di peramban (admin nasional: 38 titik; pimpinan Polda: titik Polres) tanpa galat konsol.
