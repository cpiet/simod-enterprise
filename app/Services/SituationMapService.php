<?php

namespace App\Services;

use App\Models\MediaArticle;
use App\Support\FastTime;
use Illuminate\Support\Facades\Cache;

/**
 * Peta Situasi nasional Command Center: pemberitaan 7 hari terakhir per provinsi (38), dari berita nyata tenant.
 *
 * - Titik = ibu kota provinsi (bukan lokasi kejadian).
 * - Berita dicocokkan dengan KATA UTUH nama provinsi/singkatan/kota utama pada judul + ringkasan; `exclude` mencegah
 *   tumpang tindih (mis. "Riau" tidak menghitung "Kepulauan Riau"). Satu berita dapat menyebut beberapa provinsi.
 * - Status (definisi tertulis, juga tampil di UI):
 *     ATENSI         ≥ 3 berita negatif DAN ≥ 30% berita wilayah itu negatif
 *     WASPADA        ada berita negatif (di bawah ambang ATENSI)
 *     KONDUSIF       ada berita, tanpa berita negatif
 *     BELUM ADA DATA tidak ada berita yang menyebut wilayah ini dalam 7 hari
 */
class SituationMapService
{
    public const DAYS = 7;
    public const ATENSI_MIN_NEG = 3;
    public const ATENSI_SHARE = 0.3;
    public const ARTICLES_PER_REGION = 5;

    /** [kode, nama provinsi, lintang, bujur, kata kunci, pengecualian] */
    public const PROVINCES = [
        ['ACEH', 'Aceh', 5.5483, 95.3238, ['aceh', 'banda aceh', 'lhokseumawe'], []],
        ['SUMUT', 'Sumatera Utara', 3.5952, 98.6722, ['sumatera utara', 'sumut', 'medan', 'deli serdang'], []],
        ['SUMBAR', 'Sumatera Barat', -0.9471, 100.4172, ['sumatera barat', 'sumbar', 'padang', 'bukittinggi'], ['padang lawas', 'padang sidempuan']],
        ['RIAU', 'Riau', 0.5071, 101.4478, ['riau', 'pekanbaru', 'dumai'], ['kepulauan riau']],
        ['KEPRI', 'Kepulauan Riau', 1.1301, 104.0529, ['kepulauan riau', 'kepri', 'batam', 'tanjungpinang'], []],
        ['JAMBI', 'Jambi', -1.6101, 103.6131, ['jambi'], []],
        ['SUMSEL', 'Sumatera Selatan', -2.9761, 104.7754, ['sumatera selatan', 'sumsel', 'palembang'], []],
        ['BABEL', 'Kepulauan Bangka Belitung', -2.1316, 106.1169, ['bangka belitung', 'babel', 'pangkalpinang'], []],
        ['BENGKULU', 'Bengkulu', -3.7928, 102.2608, ['bengkulu'], []],
        ['LAMPUNG', 'Lampung', -5.3971, 105.2668, ['lampung'], []],
        ['BANTEN', 'Banten', -6.12, 106.1503, ['banten', 'serang', 'cilegon'], []],
        ['METRO', 'DKI Jakarta (Metro Jaya)', -6.2088, 106.8456, ['jakarta', 'metro jaya', 'bekasi', 'depok', 'tangerang'], []],
        ['JABAR', 'Jawa Barat', -6.9175, 107.6191, ['jawa barat', 'jabar', 'bandung', 'bogor', 'cirebon'], []],
        ['JATENG', 'Jawa Tengah', -6.9667, 110.4167, ['jawa tengah', 'jateng', 'semarang', 'surakarta'], []],
        ['DIY', 'DI Yogyakarta', -7.7956, 110.3695, ['yogyakarta', 'jogja', 'sleman', 'bantul'], []],
        ['JATIM', 'Jawa Timur', -7.2575, 112.7521, ['jawa timur', 'jatim', 'surabaya', 'malang'], []],
        ['BALI', 'Bali', -8.65, 115.2167, ['bali', 'denpasar'], []],
        ['NTB', 'Nusa Tenggara Barat', -8.5833, 116.1167, ['nusa tenggara barat', 'ntb', 'mataram', 'lombok'], []],
        ['NTT', 'Nusa Tenggara Timur', -10.1772, 123.607, ['nusa tenggara timur', 'ntt', 'kupang', 'flores'], []],
        ['KALBAR', 'Kalimantan Barat', -0.0263, 109.3425, ['kalimantan barat', 'kalbar', 'pontianak'], []],
        ['KALTENG', 'Kalimantan Tengah', -2.2161, 113.9135, ['kalimantan tengah', 'kalteng', 'palangka raya', 'palangkaraya'], []],
        ['KALSEL', 'Kalimantan Selatan', -3.3186, 114.5944, ['kalimantan selatan', 'kalsel', 'banjarmasin'], []],
        ['KALTIM', 'Kalimantan Timur', -1.2379, 116.8529, ['kalimantan timur', 'kaltim', 'samarinda', 'balikpapan', 'ikn'], []],
        ['KALTARA', 'Kalimantan Utara', 2.8375, 117.3653, ['kalimantan utara', 'kaltara', 'tarakan'], []],
        ['SULUT', 'Sulawesi Utara', 1.4748, 124.8421, ['sulawesi utara', 'sulut', 'manado'], []],
        ['GORONTALO', 'Gorontalo', 0.5435, 123.0568, ['gorontalo'], []],
        ['SULTENG', 'Sulawesi Tengah', -0.8999, 119.8707, ['sulawesi tengah', 'sulteng', 'palu', 'poso'], []],
        ['SULBAR', 'Sulawesi Barat', -2.6718, 118.8872, ['sulawesi barat', 'sulbar', 'mamuju'], []],
        ['SULSEL', 'Sulawesi Selatan', -5.1477, 119.4327, ['sulawesi selatan', 'sulsel', 'makassar'], []],
        ['SULTRA', 'Sulawesi Tenggara', -3.9985, 122.5126, ['sulawesi tenggara', 'sultra', 'kendari'], []],
        ['MALUKU', 'Maluku', -3.6954, 128.1814, ['maluku', 'ambon'], ['maluku utara']],
        ['MALUT', 'Maluku Utara', 0.7893, 127.377, ['maluku utara', 'malut', 'ternate'], []],
        ['PAPUA', 'Papua', -2.5337, 140.7181, ['papua', 'jayapura'], ['papua barat', 'papua tengah', 'papua pegunungan', 'papua selatan', 'papua barat daya']],
        ['PAPBAR', 'Papua Barat', -0.8615, 134.062, ['papua barat', 'manokwari'], ['papua barat daya']],
        ['PAPBD', 'Papua Barat Daya', -0.8762, 131.2558, ['papua barat daya', 'sorong'], []],
        ['PAPTENG', 'Papua Tengah', -3.3667, 135.4833, ['papua tengah', 'nabire', 'mimika', 'timika'], []],
        ['PAPPEG', 'Papua Pegunungan', -4.095, 138.95, ['papua pegunungan', 'wamena', 'jayawijaya'], []],
        ['PAPSEL', 'Papua Selatan', -8.4932, 140.4018, ['papua selatan', 'merauke'], []],
    ];

    /**
     * @param  array<int>|null  $poldaIds  berita tenant yang dipakai (null = semua; hanya untuk agregasi nasional)
     * @return array{regions: array<int, array<string, mixed>>, total_articles: int, unmatched: int, days: int, definitions: array<string, string>}
     */
    public function national(?array $poldaIds): array
    {
        $key = 'simod.map.provinces.v1.' . ($poldaIds === null ? 'all' : implode('-', $poldaIds));

        return Cache::remember($key, 300, fn () => $this->build($poldaIds));
    }

    private function build(?array $poldaIds): array
    {
        $rows = MediaArticle::when($poldaIds !== null, fn ($q) => $q->whereIn('polda_id', $poldaIds))
            ->where('pub_date', '>=', now()->subDays(self::DAYS)->format('Y-m-d H:i:s'))
            ->orderByDesc('pub_date')->limit(5000)
            ->get(['id', 'title', 'summary', 'url', 'source_name', 'sentiment', 'pub_date']);

        $patterns = [];
        foreach (self::PROVINCES as [$code, , , , $keywords, $exclude]) {
            $patterns[$code] = [
                'kw' => '/(?<![\p{L}\d])(?:' . implode('|', array_map(fn ($k) => preg_quote($k, '/'), $keywords)) . ')(?![\p{L}\d])/u',
                'ex' => $exclude ? '/(?<![\p{L}\d])(?:' . implode('|', array_map(fn ($k) => preg_quote($k, '/'), $exclude)) . ')(?![\p{L}\d])/u' : null,
            ];
        }

        $hits = [];
        $unmatched = 0;
        foreach ($rows as $a) {
            $text = mb_strtolower($a->title . ' ' . mb_substr(strip_tags((string) $a->summary), 0, 400));
            $matched = false;
            foreach ($patterns as $code => $p) {
                $t = $p['ex'] ? preg_replace($p['ex'], ' ', $text) : $text;
                if (preg_match($p['kw'], $t)) {
                    $hits[$code][] = $a;
                    $matched = true;
                }
            }
            $unmatched += $matched ? 0 : 1;
        }

        $regions = [];
        foreach (self::PROVINCES as [$code, $name, $lat, $lng]) {
            $list = collect($hits[$code] ?? []);
            $total = $list->count();
            $neg = $list->where('sentiment', 'negatif')->count();
            $pos = $list->where('sentiment', 'positif')->count();
            $status = match (true) {
                $total === 0 => 'BELUM ADA DATA',
                $neg >= self::ATENSI_MIN_NEG && $neg / $total >= self::ATENSI_SHARE => 'ATENSI',
                $neg > 0 => 'WASPADA',
                default => 'KONDUSIF',
            };
            $regions[] = [
                'id' => $code, 'code' => $code, 'name' => $name, 'short_name' => $name,
                'latitude' => $lat, 'longitude' => $lng,
                'articles_count' => $total, 'negatif' => $neg, 'positif' => $pos, 'netral' => $total - $neg - $pos,
                'status' => $status,
                'reason' => $this->reason($status, $total, $neg),
                'search_q' => '"' . $name . '"',
                'articles' => $list->take(self::ARTICLES_PER_REGION)->map(fn ($a) => [
                    'title' => $a->title, 'url' => $a->url, 'source' => $a->source_name, 'sentiment' => $a->sentiment ?: 'netral',
                    'time' => FastTime::ago($a->getRawOriginal('pub_date')),
                ])->values()->all(),
            ];
        }

        return [
            'regions' => $regions,
            'total_articles' => $rows->count(),
            'unmatched' => $unmatched,
            'days' => self::DAYS,
            'definitions' => self::definitions(),
        ];
    }

    private function reason(string $status, int $total, int $neg): string
    {
        return match ($status) {
            'BELUM ADA DATA' => 'Tidak ada berita yang menyebut wilayah ini dalam ' . self::DAYS . ' hari terakhir. Ini bukan berarti aman.',
            'ATENSI' => "{$neg} dari {$total} berita bernada negatif (" . (int) round($neg / $total * 100) . '%).',
            'WASPADA' => "{$neg} berita negatif dari {$total} berita.",
            default => "{$total} berita, tidak ada yang bernada negatif.",
        };
    }

    /** @return array<string, string> */
    public static function definitions(): array
    {
        return [
            'ATENSI' => '≥ ' . self::ATENSI_MIN_NEG . ' berita negatif dan ≥ ' . (int) (self::ATENSI_SHARE * 100) . '% berita wilayah itu negatif',
            'WASPADA' => 'ada berita negatif, di bawah ambang ATENSI',
            'KONDUSIF' => 'ada berita, tidak ada yang negatif',
            'BELUM ADA DATA' => 'tidak ada berita yang menyebut wilayah ini (bukan berarti aman)',
        ];
    }
}
