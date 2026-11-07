<?php

return [
    // Retensi otomatis artikel berita (hari). Bookmark tidak pernah dihapus. 0 = nonaktifkan jadwal otomatis.
    'media_retention_days' => (int) env('MEDIA_RETENTION_DAYS', 90),

    // Mode Nasional (kode sesi ALL = Divhumas) hanya menampilkan data tenant Divhumas sendiri. Setel true untuk
    // kembali ke tampilan gabungan seluruh Polda aktif (agregasi nasional).
    'national_aggregate' => (bool) env('SIMOD_NATIONAL_AGGREGATE', false),

    // Isi basis data dengan DATA CONTOH (isu, tugas, publikasi, berita historis, postingan medsos, laporan AI contoh).
    // Bawaan false: instalasi baru hanya berisi data inti (Polda, Polres, akun, feed RSS, menu, hak akses, pengaturan).
    'seed_sample_data' => (bool) env('SIMOD_SEED_SAMPLE', false),

    // Fallback medsos berisi data buatan (postingan sintetis, tren publik statis). Nonaktif: radar hanya berisi data nyata.
    'social_synthetic_fallback' => (bool) env('SIMOD_SOCIAL_SYNTHETIC', false),

    // Sinkron otomatis radar medsos lapis gratis (sindikasi Google News, tanpa Apify) tiap 30 menit lewat penjadwal.
    'social_auto_sync' => (bool) env('SIMOD_SOCIAL_AUTO_SYNC', true),

    // Rem biaya Apify: maksimum run Actor per hari (seluruh aplikasi; pantau akun + ringkasan komentar). 0 = tanpa batas.
    'apify_daily_runs' => (int) env('SIMOD_APIFY_DAILY_RUNS', 60),

    // Token statis untuk endpoint API ingest (worker Python). Kosong = API ingest ditolak (fail-closed).
    'ingest_token' => env('INGEST_API_TOKEN', ''),
];
