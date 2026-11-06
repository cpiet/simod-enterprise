<?php

namespace App\Support;

use App\Services\PublicTrendsService;

/**
 * Penanda "unggahan menonjol" pada akun Instagram yang dipantau. Aturannya tertulis dan dapat dibaca analis:
 *  1. Keterlibatan (suka + komentar) >= ENGAGEMENT_RATIO × median keterlibatan akun itu sendiri,
 *     hanya bila minimal MIN_POSTS unggahan punya metrik (median dari sampel kecil tidak bermakna).
 *  2. Keterangan unggahan memuat kata kunci kamtibmas (daftar sama dengan tab Tren: PublicTrendsService).
 * Tidak ada penilaian AI di sini; angka dan kata yang memicu penanda dikirim ke UI apa adanya.
 */
class InstagramHighlights
{
    public const ENGAGEMENT_RATIO = 2.0;

    public const MIN_POSTS = 4;

    /**
     * @param  array<int, array<string, mixed>>  $posts
     * @return array<int, array<string, mixed>> unggahan dengan kunci `highlight` = ['ratio' => ?float, 'keywords' => string[]] atau null
     */
    public static function annotate(array $posts): array
    {
        $engagement = fn (array $p): ?int => (is_numeric($p['likes'] ?? null) || is_numeric($p['comments'] ?? null))
            ? (int) ($p['likes'] ?? 0) + (int) ($p['comments'] ?? 0)
            : null;

        $values = array_values(array_filter(array_map($engagement, $posts), fn ($v) => $v !== null));
        $median = null;
        if (count($values) >= self::MIN_POSTS) {
            sort($values);
            $mid = intdiv(count($values), 2);
            $median = count($values) % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
        }

        $trends = app(PublicTrendsService::class);

        return array_map(function (array $p) use ($engagement, $median, $trends) {
            $e = $engagement($p);
            $ratio = ($median !== null && $median > 0 && $e !== null && $e >= self::ENGAGEMENT_RATIO * $median)
                ? round($e / $median, 1)
                : null;
            $keywords = $trends->matchKeywords((string) ($p['caption'] ?? ''));
            $p['highlight'] = ($ratio !== null || $keywords) ? ['ratio' => $ratio, 'keywords' => $keywords] : null;

            return $p;
        }, $posts);
    }
}
