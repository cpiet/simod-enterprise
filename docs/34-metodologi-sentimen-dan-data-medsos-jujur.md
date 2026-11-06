# 34. Metodologi Sentimen & Data Media Sosial yang Jujur

Tanggal: 6 November 2026. Lingkup: Radar Media Online, Radar Media Sosial, Command Center, naskah WhatsApp, SADEWA.

## 1. Pertanyaan pengguna
"Positif/negatifnya dasarnya dari mana? Apakah ada metodologinya?" Jawaban kini tersedia di aplikasi. Setiap label sentimen dapat diklik dan menampilkan:

1. **Mengapa** item itu berlabel positif/netral/negatif: kata/pola yang cocok, dari bagian mana (judul, judul + ringkasan, atau isi unggahan), dan apakah teks menyebut Polri.
2. **Metodologi** lengkap beserta keterbatasannya.
3. **Daftar kata** yang dipakai, diambil langsung dari kode (`NewsSentiment::lexicon()`), sehingga selalu sama dengan aturan yang berjalan.

Ikon ⓘ pada kartu "Sentimen Positif" (Radar Media Online) dan "Sentimen Publik" (Radar Media Sosial) membuka metodologi yang sama. Komponen: `resources/js/Components/SentimentInfo.vue` (popup di-*teleport* ke `body`, tetap di dalam layar, tutup dengan Esc atau klik di luar, dua tema).

## 2. Metodologi (satu sumber: `App\Support\NewsSentiment`)
Berbasis aturan, tanpa AI dan tanpa biaya. Dinilai dari sudut pandang **citra dan kewaspadaan Polri**, bukan baik-buruknya peristiwa.

1. Teks menyebut Polri (polisi, polda, oknum, …) **dan** memuat isu sensitif (oknum, pungli, salah tangkap, pola "anggota … ditangkap/diperiksa") → **negatif**.
2. Selain itu, jumlah penanda negatif (gangguan kamtibmas: bentrok, begal, tewas, …) dibandingkan dengan penanda positif (apresiasi, berhasil, humanis, pola "polisi tangkap/amankan/ungkap" = kinerja). Yang lebih banyak menentukan label; sama banyak atau tidak ada → **netral**.
3. Berita: judul dinilai lebih dulu; ringkasan baru dipakai bila judul netral.
4. **Media sosial** (`detectSocial()`/`explainSocial()`): aturan yang sama + ungkapan warganet (`SOCIAL_NEGATIVE`: kecewa, bohong, lambat, …; `SOCIAL_POSITIVE`: mantap, terima kasih, salut, …).
5. Kata dicocokkan utuh ("aman" tidak cocok dengan "pengamanan").

`detect()` kini hanya mengambil label dari `explain()`, sehingga label dan alasan tidak mungkin berbeda aturan. Bila label tersimpan berbeda dari aturan saat ini (mis. diisi worker eksternal), popup menyebutkannya.

**Keterbatasan:** tidak memahami sindiran, konteks panjang, atau isi gambar/video. Label adalah klasifikasi awal yang perlu ditinjau analis. Ringkasan analisis AI (SADEWA) adalah penilaian terpisah.

## 3. Temuan audit dan perbaikan data media sosial
Sebelum perbaikan ini, sentimen medsos memakai tiga aturan berbeda yang tersebar (salah satunya mencocokkan potongan kata dan menganggap "waspada"/"tangkap" negatif). Jalur pengambilan data juga mengarang nilai:

| Jalur (prefiks `post_id`) | Data karangan sebelumnya | Sekarang |
|:---|:---|:---|
| `syndication_` (Google News, lapis gratis) | suka/bagikan/komentar/tayangan/viralitas `rand()`, tagar kata kunci ditempel ke isi, waktu acak bila tanggal kosong | metrik `null`, isi apa adanya, tanggal dari sumber atau `null` |
| `apify_` (Sinkron & Analisis, pencarian Google via Apify) | penulis ditebak sebagai akun resmi (teks berisi "divhumas" → `@divisihumaspri`, "kompas" → `@kompascom`, dll.), tagar #PolriPresisi dll. disisipkan, gambar stok Unsplash, metrik & waktu acak | penulis hanya dari URL (mis. `instagram.com/<akun>`), selain itu nama situs; tanpa tagar sisipan; thumbnail hanya ID video YouTube asli; metrik & waktu `null` |
| `ondemand_` (Lacak Topik) | penulis "Netizen Indonesia"/`@warga_xxx` acak, tagar sisipan, metrik & waktu acak | sama seperti `apify_` |
| Direktori KOL nasional | 6 tokoh buatan (Kompolnas, IPW, KontraS, Narasi, …) dengan jumlah postingan & jangkauan karangan | hanya dari unggahan tersimpan |
| Tagar viral | jangkauan +1.500 per unggahan, viralitas bawaan 60 | jangkauan = tayangan bila diketahui, selain itu interaksi yang diketahui; viralitas rata-rata hanya dari nilai yang ada |

- Migrasi `2026_11_11_090000_make_social_metrics_nullable_and_clean_fabricated_data`: kolom `likes_count`, `shares_count`, `comments_count`, `views_count`, `virality_score` boleh `NULL` (= tidak diketahui). Unggahan berprefiks di atas dibersihkan dan sentimennya dihitung ulang. Data dari sumber lain (mis. Instagram via Apify, API ingest) tidak disentuh. Pembersihan tidak dapat dibalik karena nilai lama memang karangan.
- UI: metrik `null` tampil "—" (`formatNumber`), kartu unggahan menulis "Metrik keterlibatan tidak tersedia dari sumber ini", dan skor viralitas `null` tampil "belum diketahui". Total di kartu statistik `null` bila tidak satu pun unggahan membawa angka itu.
- API ingest: nilai viralitas acak untuk artikel dihapus (kolomnya memang tidak ada di `media_articles`).
- SADEWA: metrik yang tidak diketahui ditulis "tidak diketahui" di konteks AI.

## 4. Radar Media Online: angka cadangan palsu dihapus
- Panel sentimen laporan nasional/kewilayahan tidak lagi memakai cadangan 60/30/10 (55/15). Tanpa data tampil "—".
- **Naskah WhatsApp** pimpinan: persentase kosong ditulis "belum ada data"; kalimat "situasi aman dan kondusif" yang dikarang diganti keterangan bahwa ringkasan AI belum ada; **3 isu kewilayahan buatan** (Pilkada Mamuju, hoaks begal Tarailu, jukir Manakarra / Polda Metro, Jabar, Divhumas) dihapus; status bawaan "HIJAU - KONDUSIF" diganti "belum dinilai"; bagian risiko tidak lagi mengklaim telaah bila tidak ada peringatan dini.
- Label isu tanpa sentimen tampil "Belum dinilai" (sebelumnya "Positif").

## 5. Tab KOL: platform dibedakan
- "Pantau Akun **Instagram**" diberi label platform di judul, setiap akun, dan tautan profil, beserta keterangan bahwa pantauan per akun baru untuk Instagram; X/TikTok/YouTube/Facebook masuk lewat Radar Percakapan.
- Direktori KOL: judul "semua platform", deskripsi jujur (dari unggahan tersimpan), dan saringan per platform dengan jumlah dari data yang dimuat.

## 6. Pengujian
`SentimentTransparencyTest` (alasan = label, varian medsos & kata utuh, sindikasi tanpa metrik/tagar/waktu karangan, migrasi pembersihan, prop popup & tanpa KOL buatan) dan `NewsSentimentAndSearchTest` (aturan berita tidak berubah).

## 7. Deployment
Cadangkan basis data (`php artisan db:backup --force`), `php artisan migrate --force`, `php artisan optimize:clear`, lalu `php artisan social:trends-refresh`.
