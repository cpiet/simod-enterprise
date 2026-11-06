# 🤖 26. SADEWA AI — Asisten Percakapan Multi-Sumber & Riwayat Privat

SADEWA AI adalah panel chat global (komponen [`SadewaCopilot.vue`](../resources/js/Components/SadewaCopilot.vue), dimuat di layout) yang menjawab **hanya dari data sistem**, kini mencakup tiga pilar sumber data (Media Online, Media Sosial, dan Isu Internal), bukan lagi terbatas pada Radar Media Online.

---

## 1. Sumber Data dan Lingkup (`scope`)

| Lingkup | Isi Konteks (Dibatasi Tenant) | Syarat Modul RBAC |
|:---|:---|:---|
| `media` | Laporan radar media terbaru + 30 artikel berita terbaru (kode rujukan `[M#]`) | `media_intelligence_view` |
| `social` | Sintesis medsos terbaru + 20 postingan paling viral 7 hari (kode rujukan `[S#]`) | `social_intelligence_view` |
| `issues` | 15 isu aktif (`pending`, `active`, `clarified`) + 6 peringatan dini kamtibmas EWS (kode rujukan `[I#]`, `[E#]`) | `issues_view` |
| `all` | Gabungan seluruh lingkup yang **diizinkan** bagi peran pengguna aktif | Minimal 1 modul di atas |

> Data dari modul yang tidak diizinkan **tidak pernah dimasukkan ke dalam prompt AI**. Permintaan ke lingkup yang tidak berhak langsung ditolak (HTTP 403). Konteks wilayah selalu ditentukan melalui `resolveActiveTenant()`; parameter `polda_id` dari klien hanya dihormati untuk Super Admin.

---

## 2. Endpoint API (`SadewaController`, Grup `/sadewa`)

| Rute | Metode | Middleware & Otorisasi | Fungsi |
|:---|:---|:---|:---|
| `/sadewa/meta` | `GET` | `auth`, `idle`, RBAC multi-view | Mengembalikan daftar lingkup yang tersedia untuk pengguna dan statistik angka nyata (portal aktif, berita 24 jam, postingan medsos, isu aktif). |
| `/sadewa/chat` | `POST` | `auth`, `idle`, `throttle:20,1` | Memproses pertanyaan pengguna (`message`, `scope`, `conversation_id`, `subject`). |
| `/sadewa/conversations` | `GET` | `auth`, `idle` | Mengambil daftar riwayat percakapan milik pengguna aktif (terbaru, maks. 30). |
| `/sadewa/conversations/{id}` | `GET` | `auth`, `idle` | Mengambil isi lengkap percakapan berdasarkan ID. |
| `/sadewa/conversations/{id}` | `DELETE` | `auth`, `idle` | Menghapus satu percakapan tertentu milik pengguna. |
| `/sadewa/conversations` | `DELETE` | `auth`, `idle` | Membersihkan seluruh riwayat percakapan milik pengguna. |

Middleware grup: `module:media_intelligence_view|social_intelligence_view|issues_view`. Endpoint lama `POST /media-intelligence/chat` telah didepresiasi dan digantikan sepenuhnya oleh `/sadewa/chat`.

---

## 3. Jawaban Bersumber Terverifikasi (Grounded Citations)

Sistem prompt mewajibkan model AI hanya memakai fakta dari data yang diberikan, mengakui bila data tidak ada (tanpa halusinasi), dan membubuhkan kode rujukan:
- **`[M1] – [M30]`**: Artikel berita media online (menampilkan nama portal media, judul, dan tautan luar `target="_blank"`).
- **`[S1] – [S20]`**: Postingan media sosial (menampilkan platform, `@handle` pembuat, dan tautan langsung ke URL postingan).
- **`[I1] – [I15]`**: Laporan isu internal (menampilkan kode laporan kepolisian dan tautan internal ke `/issues/{id}`).
- **`[E1] – [E6]`**: Peringatan Dini Kamtibmas (*Early Warning System*).

Server memfilter dan hanya mengirimkan sumber yang benar-benar dikutip dalam teks jawaban. Pada antarmuka pengguna, kode rujukan tampil sebagai chip tautan interaktif yang dapat diklik langsung. Seluruh keluaran markdown disanitasi secara ketat (*raw HTML* dibuang) guna mencegah potensi serangan XSS.

---

## 4. Riwayat Percakapan Privat per Akun (`SadewaConversation`)

Tabel `sadewa_conversations` menyimpan:
- `user_id`: ID akun pengguna (terikat privasi akun).
- `polda_id`: Wilayah penugasan saat percakapan dibuat.
- `title`: Judul ringkas sesi percakapan.
- `scope`: Lingkup data yang digunakan (`all`, `media`, `social`, `issues`).
- `messages`: Kolom JSON memuat pesan pengguna, jawaban AI, timestamp, dan daftar sumber rujukan.

Maksimal 60 pesan per percakapan dan 30 sesi per akun. Riwayat percakapan bersifat privat penuh per pengguna dan **tidak lagi terhapus saat berpindah halaman**.

---

## 5. Integrasi UI & Pemicu Kontekstual Lintas Halaman

Panel SADEWA Copilot dapat dipanggil secara kontekstual melalui event `CustomEvent('open-sadewa-copilot')`:
- **Peluncur Global**: Tombol mengambang (*floating button*) di sudut kanan bawah seluruh halaman layout.
- **Radar Media Online**: Membuka dengan `{ scope: 'media' }`.
- **Radar Media Sosial**: Tombol `[ SADEWA AI ]` di toolbar atas membuka dengan `{ scope: 'social' }`.
- **Detail Laporan Isu**: Tombol `[ SADEWA ]` pada aksi laporan membuka dengan `{ scope: 'issues', subject: issue.title }`.

---

## 6. Pengujian Otomatis

Diuji secara komprehensif pada [`tests/Feature/SadewaChatTest.php`](../tests/Feature/SadewaChatTest.php) (7 tes, 58 assertions):
1. Tamu tak terotentikasi ditolak (HTTP 401).
2. Jawaban mengutip sumber rujukan dan menyimpan riwayat percakapan pada pemilik akun.
3. Riwayat bersifat privat per akun (pengguna lain tidak dapat membaca/menghapus riwayat).
4. Pengguna daerah tidak dapat membaca data Polda lain via input klien.
5. Permintaan ke lingkup tanpa izin modul RBAC ditolak dan datanya tidak dikirim ke AI.
6. Lingkup medsos berhasil memuat postingan media sosial untuk pengguna yang berhak.
7. Validasi masukan pesan kosong dan lingkup tidak dikenal (HTTP 422).

Baseline pengujian sistem saat ini: **151 passed (905 assertions)**.

## Addendum 6 Nov 2026: pencarian internet
Bila data SIMOD tidak memuat jawaban, SADEWA dapat mencari lewat Google Search (hanya pertanyaan pengguna yang dikirim), diberi label "dari internet" dan sumber `[W1]..`, dihitung kuota (`chat_web`), dan dapat dimatikan admin (`ai_settings.web_search_enabled`). Detail: doc 33 §3.
