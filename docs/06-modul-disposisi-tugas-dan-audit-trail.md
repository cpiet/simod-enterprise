# 📋 06. Modul Disposisi Tugas & Audit Trail

## 1. Disposisi Penugasan Personel (`/tasks`)
Digunakan oleh Pimpinan (Kapolda / Kabid Humas) untuk menerbitkan instruksi taktis kepada Satuan Kerja pelaksana di jajaran wilayah.

### Atribut Penugasan:
- **Judul Tugas**: Instruksi spesifik (misal: "Patroli Siber Akun Provokatif Pilkada").
- **Satker Pelaksana**: Penunjukan unit teknis (Ditreskrimsus, Ditlantas, Polresta Mamuju, dll.).
- **Personel Penanggung Jawab**: Nama perwira pelaksana.
- **Tingkat Prioritas**: *Urgent (Mendesak)*, *High (Tinggi)*, *Medium (Sedang)*, *Low (Rendah)*.
- **Batas Waktu (Due Date)**: Target jam penyelesaian instruksi.

### Pelaporan Progres Lapangan (`task_updates`):
Personel di lapangan dapat menginput catatan berkala secara kronologis (*timestamped*) dan menandai tugas selesai 100% jika investigasi atau liputan telah rampung.

---

## 2. Audit Trail Keamanan Imutabel (`audit_logs`)
Setiap aktivitas sensitif di SIMOD Enterprise dicatat secara permanen tanpa dapat diubah (*append-only*):

- **Aktor**: Pengguna yang melakukan tindakan (`user_id`).
- **Aksi**: Tindakan operasional (misal: `login`, `create_issue`, `publish_release`, `clarify_hoax`).
- **Metadata**: Rincian perubahan data dalam format JSON.
- **Identitas Jaringan**: Alamat IP (`ip_address`) dan peramban/perangkat (`user_agent`).
- **Waktu Eksekusi**: Waktu pasti kejadian (WITA).

Audit log ini menjamin akuntabilitas personel, kepatuhan SOP kehumasan, dan kesiapan inspeksi pengawasan internal (Itwasda / Propam).

