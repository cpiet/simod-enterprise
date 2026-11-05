<?php

namespace App\Support;

/**
 * Pemformat waktu ringan tanpa objek Carbon.
 *
 * Dipakai pada daftar besar (ribuan baris artikel) di mana `Carbon::diffForHumans()` dan
 * `Carbon::format()` per baris memakan ratusan milidetik. Keluaran dibuat setara dengan
 * lokal `id` (mis. "5 menit yang lalu").
 */
class FastTime
{
    /**
     * Waktu relatif Bahasa Indonesia dari string tanggal basis data (atau null).
     */
    public static function ago(?string $raw, string $fallback = 'Baru saja'): string
    {
        if (empty($raw)) {
            return $fallback;
        }

        $ts = strtotime($raw);
        if ($ts === false) {
            return $fallback;
        }

        $diff = time() - $ts;
        if ($diff < 0) {
            $diff = 0;
        }

        return match (true) {
            $diff < 60       => max(1, $diff) . ' detik yang lalu',
            $diff < 3600     => intdiv($diff, 60) . ' menit yang lalu',
            $diff < 86400    => intdiv($diff, 3600) . ' jam yang lalu',
            $diff < 604800   => intdiv($diff, 86400) . ' hari yang lalu',
            $diff < 2592000  => intdiv($diff, 604800) . ' minggu yang lalu',
            $diff < 31536000 => intdiv($diff, 2592000) . ' bulan yang lalu',
            default          => intdiv($diff, 31536000) . ' tahun yang lalu',
        };
    }

    /**
     * Format `d M Y, H.i` dari string tanggal basis data; bila kosong memakai waktu sekarang.
     */
    public static function stamp(?string $raw): string
    {
        $ts = $raw ? strtotime($raw) : false;

        return date('d M Y, H.i', $ts !== false ? $ts : time());
    }
}
