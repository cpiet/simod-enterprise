<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tren pencarian publik Indonesia dari Google Trends ("Sedang Tren", RSS resmi tanpa kunci API).
 *
 * Data bersifat nasional (sama untuk semua tenant), disimpan di cache dan diperbarui oleh
 * penjadwal (`social:trends-refresh`, tiap jam) atau tombol "Perbarui" di Radar Media Sosial.
 * Bila penarikan gagal, data terakhir yang berhasil tetap ditampilkan beserta keterangan galat.
 *
 * Penanda "terkait kamtibmas" hanya pencocokan kata kunci pada judul tren + judul berita terkait
 * (bukan penilaian AI); kata yang cocok ditampilkan agar analis dapat menilai sendiri.
 */
class PublicTrendsService
{
    public const FEED_URL = 'https://trends.google.com/trending/rss?geo=ID';

    public const CACHE_KEY = 'simod.public_trends';

    /** Kata kunci kamtibmas/kepolisian. Dicocokkan per kata utuh, huruf kecil. */
    public const KAMTIBMAS_KEYWORDS = [
        'polisi', 'polri', 'polda', 'polres', 'polresta', 'polrestabes', 'polsek', 'kapolri', 'kapolda', 'kapolres',
        'brimob', 'densus', 'propam', 'bareskrim', 'tilang', 'etle', 'lalu lintas', 'kecelakaan', 'tabrakan',
        'begal', 'curanmor', 'pencurian', 'perampokan', 'pembunuhan', 'dibunuh', 'penganiayaan', 'pemerkosaan',
        'pelecehan', 'kekerasan', 'tawuran', 'bentrok', 'kerusuhan', 'demo', 'demonstrasi', 'unjuk rasa',
        'narkoba', 'sabu', 'ganja', 'judi', 'judol', 'penipuan', 'hoaks', 'hoax', 'phishing', 'pinjol',
        'peretasan', 'diretas', 'teror', 'teroris', 'bom', 'penembakan', 'ditembak', 'korupsi', 'ott', 'kpk',
        'penculikan', 'orang hilang', 'banjir', 'longsor', 'gempa', 'kebakaran', 'tsunami', 'erupsi', 'pilkada', 'pemilu',
    ];

    /**
     * Data tersimpan untuk ditampilkan. Tidak pernah memanggil jaringan.
     *
     * @return array{available: bool, fetched_at: ?string, source: string, items: array, last_error: ?string, attempted_at: ?string}
     */
    public function cached(): array
    {
        $data = Cache::get(self::CACHE_KEY);

        return is_array($data) ? $data + $this->emptyPayload() : $this->emptyPayload();
    }

    /**
     * Tarik ulang dari Google Trends. Bila gagal, data lama dipertahankan dan galat dicatat.
     *
     * @return array{success: bool, message: string, count: int}
     */
    public function refresh(): array
    {
        $previous = $this->cached();
        $now = now()->toIso8601String();

        try {
            $response = Http::timeout(15)
                ->withHeaders(['User-Agent' => 'Mozilla/5.0 (compatible; SIMOD-Enterprise/1.0)', 'Accept' => 'application/rss+xml, application/xml'])
                ->get(self::FEED_URL);

            if (! $response->successful()) {
                return $this->fail($previous, $now, "Google Trends membalas kode {$response->status()}.");
            }

            $items = $this->parse($response->body());
            if ($items === null) {
                return $this->fail($previous, $now, 'Format data Google Trends tidak dikenali.');
            }
        } catch (\Throwable $e) {
            Log::warning('Penarikan Google Trends gagal: ' . $e->getMessage());

            return $this->fail($previous, $now, 'Google Trends tidak dapat dihubungi (koneksi/timeout).');
        }

        Cache::forever(self::CACHE_KEY, [
            'available'    => true,
            'fetched_at'   => $now,
            'attempted_at' => $now,
            'source'       => $this->emptyPayload()['source'],
            'items'        => $items,
            'last_error'   => null,
        ]);

        $related = count(array_filter($items, fn ($i) => ! empty($i['kamtibmas'])));

        return [
            'success' => true,
            'message' => count($items) . " tren pencarian Indonesia diperbarui ({$related} memuat kata kunci kamtibmas).",
            'count'   => count($items),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>|null null bila XML tidak valid
     */
    public function parse(string $xml): ?array
    {
        $previous = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($doc === false || ! isset($doc->channel)) {
            return null;
        }

        $items = [];
        $rank = 0;
        foreach ($doc->channel->item as $item) {
            $title = trim((string) $item->title);
            if ($title === '') {
                continue;
            }
            $ht = $item->children('ht', true);

            $news = [];
            foreach ($ht->news_item as $n) {
                $url = trim((string) $n->news_item_url);
                if (! preg_match('#^https?://#i', $url)) {
                    continue;
                }
                $news[] = [
                    'title'  => html_entity_decode(trim((string) $n->news_item_title), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    'url'    => $url,
                    'source' => trim((string) $n->news_item_source) ?: null,
                ];
                if (count($news) >= 3) {
                    break;
                }
            }

            $traffic = trim((string) $ht->approx_traffic);
            $picture = trim((string) $ht->picture);
            $startedAt = null;
            try {
                $startedAt = trim((string) $item->pubDate) !== '' ? Carbon::parse((string) $item->pubDate)->toIso8601String() : null;
            } catch (\Throwable) {
                $startedAt = null;
            }

            $items[] = [
                'rank'        => ++$rank,
                'title'       => $title,
                'traffic'     => $traffic !== '' ? $traffic : null,
                'traffic_num' => $this->parseTraffic($traffic),
                'started_at'  => $startedAt,
                'picture'     => preg_match('#^https://[a-z0-9.-]*gstatic\.com/#i', $picture) ? $picture : null,
                'news'        => $news,
                'kamtibmas'   => $this->matchKeywords($title . ' ' . implode(' ', array_column($news, 'title'))),
                'explore_url' => 'https://trends.google.com/trends/explore?geo=ID&q=' . rawurlencode($title),
            ];
        }

        return $items;
    }

    /**
     * @return array<int, string> kata kunci kamtibmas yang cocok (maks. 3)
     */
    public function matchKeywords(string $text): array
    {
        $text = mb_strtolower($text);
        $found = [];
        foreach (self::KAMTIBMAS_KEYWORDS as $keyword) {
            if (preg_match('/(?<![\p{L}\p{N}])' . preg_quote($keyword, '/') . '(?![\p{L}\p{N}])/u', $text)) {
                $found[] = $keyword;
                if (count($found) >= 3) {
                    break;
                }
            }
        }

        return $found;
    }

    /** "2K+" → 2000, "1.000+" / "1,000+" → 1000, "100+" → 100; tidak dikenali → null. */
    private function parseTraffic(string $traffic): ?int
    {
        if (! preg_match('/^([\d.,]+)\s*([KkMm]|rb|jt)?\+?$/u', trim($traffic), $m)) {
            return null;
        }
        $number = (float) str_replace([',', '.'], '', $m[1]);
        $mult = match (strtolower($m[2] ?? '')) {
            'k', 'rb' => 1000,
            'm', 'jt' => 1000000,
            default => 1,
        };

        return (int) ($number * $mult);
    }

    private function fail(array $previous, string $now, string $message): array
    {
        Cache::forever(self::CACHE_KEY, array_merge($previous, ['last_error' => $message, 'attempted_at' => $now]));

        return [
            'success' => false,
            'message' => $message . (($previous['items'] ?? []) ? ' Data terakhir yang berhasil tetap ditampilkan.' : ''),
            'count'   => 0,
        ];
    }

    private function emptyPayload(): array
    {
        return [
            'available'    => false,
            'fetched_at'   => null,
            'attempted_at' => null,
            'source'       => 'Google Trends — Sedang Tren (Indonesia)',
            'items'        => [],
            'last_error'   => null,
        ];
    }
}
