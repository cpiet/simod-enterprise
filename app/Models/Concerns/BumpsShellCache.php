<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Cache;

/**
 * Menaikkan versi cache "shell" aplikasi (daftar Polda, navigasi, izin) setiap kali model berubah,
 * sehingga `HandleInertiaRequests` dapat menyimpan data bersama tersebut di cache tanpa menampilkan data basi.
 */
trait BumpsShellCache
{
    public const SHELL_VERSION_KEY = 'simod.shell_version';

    public static function bootBumpsShellCache(): void
    {
        $bump = static fn () => Cache::forever(self::SHELL_VERSION_KEY, (string) microtime(true));

        static::saved($bump);
        static::deleted($bump);
    }
}
