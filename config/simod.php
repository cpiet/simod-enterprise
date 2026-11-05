<?php

return [
    // Retensi otomatis artikel berita (hari). Bookmark tidak pernah dihapus. 0 = nonaktifkan jadwal otomatis.
    'media_retention_days' => (int) env('MEDIA_RETENTION_DAYS', 90),

    // Token statis untuk endpoint API ingest (worker Python). Kosong = API ingest ditolak (fail-closed).
    'ingest_token' => env('INGEST_API_TOKEN', ''),
];
