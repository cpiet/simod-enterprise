<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Seluruh tugas terjadwal (sinkron RSS, cadangan, pemangkasan log audit & berita) + detak penjadwal.
// Definisi tunggal di App\Support\ScheduledTasks agar halaman Audit Log -> Penjadwal menampilkan status nyata.
\App\Support\ScheduledTasks::register();
