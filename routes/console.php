<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Pembersihan otomatis berita lama (non-bookmark) setiap hari pukul 02:00
if ((int) config('simod.media_retention_days') > 0) {
    \Illuminate\Support\Facades\Schedule::command('media:prune')->dailyAt('02:00');
}

// Otomasi Sinkronisasi RSS Multi-Polda dengan perlindungan anti-overlapping
\Illuminate\Support\Facades\Schedule::command('rss:sync --scheduled')
    ->everyMinute()
    ->withoutOverlapping(10);

