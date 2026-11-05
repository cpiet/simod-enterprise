<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Pencarian berita di sisi server (atas seluruh arsip, bukan hanya yang termuat di browser).
 *
 * Sintaks yang dipahami:
 *   kapolri penyadapan      -> semua kata harus ada (AND), di judul / ringkasan / nama portal
 *   "jenderal bintang 4"    -> frasa persis
 *   -hoaks  atau  -"fakta"  -> kecualikan kata / frasa
 */
class NewsSearch
{
    private const MAX_LENGTH = 200;
    private const MAX_INCLUDE = 8;
    private const MAX_EXCLUDE = 5;

    /**
     * @return array{include: array<int,string>, exclude: array<int,string>}
     */
    public static function parse(?string $query): array
    {
        $query = trim(mb_substr((string) $query, 0, self::MAX_LENGTH));
        $result = ['include' => [], 'exclude' => []];

        if ($query === '') {
            return $result;
        }

        preg_match_all('/(-?)"([^"]+)"|(-?)(\S+)/u', $query, $matches, PREG_SET_ORDER);

        foreach ($matches as $m) {
            $quoted = isset($m[2]) && $m[2] !== '';
            $negated = $quoted ? ($m[1] === '-') : (($m[3] ?? '') === '-');
            $term = trim($quoted ? $m[2] : ($m[4] ?? ''), " \t\"'");
            $term = mb_strtolower(preg_replace('/\s+/u', ' ', $term) ?? $term);

            if ($term === '' || (! $quoted && mb_strlen($term) < 2)) {
                continue;
            }

            $bucket = $negated ? 'exclude' : 'include';
            $limit = $negated ? self::MAX_EXCLUDE : self::MAX_INCLUDE;
            if (count($result[$bucket]) < $limit && ! in_array($term, $result[$bucket], true)) {
                $result[$bucket][] = $term;
            }
        }

        return $result;
    }

    /** Escape karakter khusus LIKE (memakai `!` sebagai escape agar portabel MySQL & SQLite). */
    public static function like(string $term): string
    {
        return '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term) . '%';
    }

    /**
     * Terapkan syarat pencarian ke kueri artikel.
     *
     * @param array{include: array<int,string>, exclude: array<int,string>} $parsed
     */
    public static function apply(Builder $query, array $parsed): Builder
    {
        foreach ($parsed['include'] as $term) {
            $like = self::like($term);
            $query->where(function (Builder $w) use ($like) {
                $w->whereRaw("media_articles.title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("media_articles.summary LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("media_articles.source_name LIKE ? ESCAPE '!'", [$like]);
            });
        }

        foreach ($parsed['exclude'] as $term) {
            $like = self::like($term);
            $query->whereRaw("media_articles.title NOT LIKE ? ESCAPE '!'", [$like])
                ->where(function (Builder $w) use ($like) {
                    $w->whereNull('media_articles.summary')
                        ->orWhereRaw("media_articles.summary NOT LIKE ? ESCAPE '!'", [$like]);
                });
        }

        return $query;
    }

    /**
     * Ekspresi skor relevansi: judul bobot 3, ringkasan bobot 1, per kata/frasa.
     *
     * @param array{include: array<int,string>, exclude: array<int,string>} $parsed
     * @return array{0: string, 1: array<int,string>} [SQL, binding]
     */
    public static function relevance(array $parsed): array
    {
        $parts = [];
        $bindings = [];

        foreach ($parsed['include'] as $term) {
            $like = self::like($term);
            $parts[] = "(CASE WHEN media_articles.title LIKE ? ESCAPE '!' THEN 3 ELSE 0 END)";
            $bindings[] = $like;
            $parts[] = "(CASE WHEN media_articles.summary LIKE ? ESCAPE '!' THEN 1 ELSE 0 END)";
            $bindings[] = $like;
        }

        return [$parts === [] ? '0' : implode(' + ', $parts), $bindings];
    }

    /**
     * Normalisasi judul untuk menggabungkan berita ganda: huruf kecil, tanpa sufiks nama portal
     * ("... - Detikcom", "... | CNN Indonesia"), tanpa tanda baca. Mengembalikan null bila judul terlalu pendek.
     */
    public static function titleHash(?string $title): ?string
    {
        $t = mb_strtolower(trim((string) $title));
        $t = preg_replace('/\s+[\-\|–—]\s+[^\-\|–—]{2,40}$/u', '', $t) ?? $t;
        $t = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $t) ?? $t;
        $t = trim($t);

        return mb_strlen($t) < 15 ? null : md5($t);
    }
}
