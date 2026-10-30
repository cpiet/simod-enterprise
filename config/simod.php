<?php

return [
    // Retensi otomatis artikel berita (hari). Bookmark tidak pernah dihapus. 0 = nonaktifkan jadwal otomatis.
    'media_retention_days' => (int) env('MEDIA_RETENTION_DAYS', 90),
];
