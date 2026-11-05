# 🤖 21. Standarisasi Multi-Agent & Sinkronisasi Lintas Model

SIMOD Enterprise dikerjakan bergantian oleh beberapa AI coding agent (Claude Code, OpenAI Codex, Gemini CLI / Antigravity, GitHub Copilot, Cursor, DeepSeek). Dokumen ini menjelaskan bagaimana semua agent membaca **aturan yang sama** tanpa salinan yang saling menyimpang.

---

## 1. Satu Sumber Aturan: `AGENTS.md`

Sebelumnya `AGENTS.md`, `CLAUDE.md`, dan `.github/copilot-instructions.md` berisi salinan aturan yang ditulis terpisah. Akibatnya isinya menyimpang: halaman yang sudah dihapus masih disebut, jumlah test dan feed berbeda, dan payload pergantian tenant ditulis keliru. Sejak 5 November 2026 strukturnya:

```text
simod-enterprise/
├── AGENTS.md                        <-- INDUK: seluruh aturan, peta navigasi, peta kode
├── CLAUDE.md                        <-- Claude Code: berisi `@AGENTS.md` (impor) + catatan kecil
├── GEMINI.md                        <-- Gemini CLI / Antigravity: berisi `@AGENTS.md` (impor)
├── .github/copilot-instructions.md  <-- Copilot / Codex / Cursor: ringkasan aturan wajib + rujukan ke AGENTS.md
└── docs/22-peta-kode-dan-sumber-kebenaran.md  <-- angka terverifikasi (rute, tabel, RBAC, test)
```

| Agent | Berkas yang dibaca otomatis | Isi |
|:---|:---|:---|
| Claude Code | `CLAUDE.md` | Mengimpor `AGENTS.md` |
| OpenAI Codex | `AGENTS.md` | Langsung |
| Gemini CLI / Antigravity | `GEMINI.md` / `AGENTS.md` | Mengimpor / langsung |
| GitHub Copilot / Cursor | `.github/copilot-instructions.md` (Copilot agent juga membaca `AGENTS.md`) | Ringkasan + rujukan |
| Lainnya (DeepSeek, dll.) | Arahkan ke `AGENTS.md` | Langsung |

**Aturan pemeliharaan:**
1. Mengubah aturan → edit **hanya `AGENTS.md`**. Bila aturan itu termasuk "wajib", sesuaikan juga ringkasan di `.github/copilot-instructions.md`.
2. Angka (jumlah test, rute, tabel, feed, modul RBAC) **hanya** ditulis di doc 22. Berkas lain merujuk ke sana supaya tidak basi.
3. Jangan menulis path absolut (`file:///d:/...`) di dokumen; gunakan tautan relatif. Folder proyek berbeda antar komputer.

---

## 2. Aturan Inti yang Berlaku untuk Semua Agent

Rinciannya ada di `AGENTS.md` §3. Ringkasnya:
1. `phpunit.xml` tetap SQLite in-memory dan tidak boleh diubah.
2. `php artisan test` dan `npm.cmd run build` wajib bersih sebelum tugas dinyatakan selesai.
3. Tenant hanya dari `resolveActiveTenant()`. Tidak ada `?polda=` di UI; pergantian lewat `POST /tenant/switch` dengan payload `code`.
4. Setiap rute modul memakai `module:<kunci>`.
5. UI tidak menampilkan angka karangan atau fitur mock.
6. Perubahan perilaku disertai pembaruan dokumen dalam commit yang sama.

---

## 3. Serah Terima Antar-Agent

1. `git status`: periksa perubahan yang belum di-commit. Jangan menimpa pekerjaan agent atau pengguna lain.
2. `php artisan test`: catat baseline sebelum mengubah apa pun.
3. Kerjakan perubahan sesuai `AGENTS.md`, lalu tambah atau ubah test.
4. `npm.cmd run build` + `php artisan test` harus bersih.
5. Perbarui doc 22 dan doc modul terkait. Commit (hanya bila diminta pengguna) dengan Conventional Commits berbahasa Indonesia, mis. `feat(media-intelligence): ...`.

---

Rujukan peta kode, rute, tabel, dan temuan audit: [doc 22](22-peta-kode-dan-sumber-kebenaran.md).
