<?php

namespace App\Services;

use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\RssSyncLog;
use App\Support\FastTime;
use App\Support\NewsSearch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daftar berita Radar Media Online di sisi server: pencarian atas seluruh arsip, filter periode/portal/kategori/
 * sentimen, penggabungan berita ganda lintas portal, paginasi, facet (hitungan filter), serta KPI & kesegaran data.
 *
 * Cakupan data ditentukan oleh `$poldaIds` yang diberikan pemanggil dari `resolveActiveTenant()`:
 *  - Polda biasa  : satu id Polda (terkunci tenant).
 *  - Nasional/ALL : seluruh Polda aktif (hanya Mabes/Super Admin yang dapat berada di mode ini).
 *
 * Facet dihitung dengan seluruh filter AKTIF KECUALI filternya sendiri, sehingga angka pada dropdown selalu
 * sama dengan jumlah kartu yang tampil saat opsi itu dipilih (paritas UI 100%).
 */
class MediaArticleListing
{
    public const PERIODS = ['all', 'today', '24h', '7d', '30d', 'custom'];

    /** @var array<int, array<string, mixed>> Topik Atensi aktif (id => data) untuk filter & pencocokan topik. */
    protected array $topics = [];

    /** @var Collection<int, Polda>|null */
    protected ?Collection $poldaMeta = null;

    /**
     * @param array<int, int> $poldaIds
     */
    public function __construct(
        protected array $poldaIds,
        protected bool $national = false,
        protected string $timezone = 'Asia/Makassar',
    ) {
        $this->poldaIds = array_values(array_unique(array_map('intval', $poldaIds)));
    }

    /** @param array<int, array<string, mixed>> $topics */
    public function setTopics(array $topics): static
    {
        $this->topics = $topics;

        return $this;
    }

    /**
     * Filter bawaan: seluruh arsip, berita ganda digabung.
     *
     * @return array<string, mixed>
     */
    public static function defaultFilters(): array
    {
        return [
            'q' => '', 'period' => 'all', 'from' => null, 'to' => null, 'sort' => 'newest',
            'portal' => '', 'category' => '', 'sentiment' => '', 'bookmarks' => false, 'topic' => 0,
            'wilayah' => 0, 'group' => true, 'page' => 1, 'per_page' => 15,
        ];
    }

    /**
     * Baca & sanitasi filter dari query string. Nilai tak valid diabaikan (tidak pernah memicu galat).
     *
     * @param array<int, int> $allowedPoldaIds Polda yang boleh difilter (hanya relevan pada mode nasional)
     * @return array<string, mixed>
     */
    public static function filtersFromRequest(Request $request, array $allowedPoldaIds = []): array
    {
        $f = self::defaultFilters();

        $f['q'] = trim(mb_substr((string) $request->query('q', ''), 0, 200));

        $period = (string) $request->query('period', 'all');
        $f['period'] = in_array($period, self::PERIODS, true) ? $period : 'all';
        $f['from'] = self::validDate($request->query('from'));
        $f['to'] = self::validDate($request->query('to'));
        if ($f['period'] === 'custom' && ! $f['from'] && ! $f['to']) {
            $f['period'] = 'all';
        }
        if ($f['period'] !== 'custom') {
            $f['from'] = $f['to'] = null;
        }

        $f['sort'] = $request->query('sort') === 'relevance' ? 'relevance' : 'newest';

        $f['portal'] = trim(mb_substr((string) $request->query('portal', ''), 0, 120));
        $f['category'] = trim(mb_substr((string) $request->query('category', ''), 0, 80));

        $sentiment = (string) $request->query('sentiment', '');
        $f['sentiment'] = in_array($sentiment, ['positif', 'netral', 'negatif'], true) ? $sentiment : '';
        if ($request->boolean('redzone')) {
            $f['sentiment'] = 'negatif';
        }

        $f['bookmarks'] = $request->boolean('bookmarks');
        $f['topic'] = max(0, (int) $request->query('topic', 0));

        $wilayah = (int) $request->query('wilayah', 0);
        $f['wilayah'] = in_array($wilayah, $allowedPoldaIds, true) ? $wilayah : 0;

        $f['group'] = $request->has('group') ? $request->boolean('group') : true;
        $f['page'] = max(1, (int) $request->query('page', 1));
        $f['per_page'] = min(60, max(6, (int) $request->query('per_page', 15)));

        return $f;
    }

    private static function validDate(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && strtotime($value) !== false ? $value : null;
    }

    /** Kueri dasar: seluruh artikel dalam cakupan tenant, tanpa filter apa pun. */
    public function scoped(): Builder
    {
        return MediaArticle::query()->whereIn('media_articles.polda_id', $this->poldaIds);
    }

    /**
     * Kueri terfilter. `$except` melewati satu kelompok filter (dipakai untuk menghitung facet).
     * Filter internal `_after` / `_before` (waktu UTC `Y-m-d H:i:s`) dipakai untuk KPI.
     *
     * @param array<string, mixed> $f
     */
    public function filtered(array $f, ?string $except = null): Builder
    {
        $q = $this->scoped();

        if ($except !== 'q') {
            NewsSearch::apply($q, NewsSearch::parse((string) ($f['q'] ?? '')));
        }
        if ($except !== 'period') {
            $this->applyPeriod($q, $f);
        }
        if (! empty($f['_after'])) {
            $q->where('media_articles.pub_date', '>=', $f['_after']);
        }
        if (! empty($f['_before'])) {
            $q->where('media_articles.pub_date', '<', $f['_before']);
        }

        $portal = (string) ($f['portal'] ?? '');
        if ($except !== 'portal' && $portal !== '') {
            if (str_contains(mb_strtoupper($portal), 'SEBARAN INTERNET')) {
                $this->whereInternet($q);
            } else {
                $q->whereRaw('UPPER(media_articles.source_name) = ?', [mb_strtoupper($portal)]);
            }
        }

        $category = (string) ($f['category'] ?? '');
        if ($except !== 'category' && $category !== '') {
            $q->where('media_articles.category', $category);
        }

        $sentiment = (string) ($f['sentiment'] ?? '');
        if ($except !== 'sentiment' && $sentiment !== '') {
            if ($sentiment === 'netral') {
                $q->where(fn (Builder $w) => $w->where('media_articles.sentiment', 'netral')->orWhereNull('media_articles.sentiment'));
            } else {
                $q->where('media_articles.sentiment', $sentiment);
            }
        }

        if ($except !== 'bookmarks' && ! empty($f['bookmarks'])) {
            $q->where('media_articles.is_bookmarked', true);
        }

        if ($except !== 'wilayah' && $this->national && ! empty($f['wilayah'])) {
            $q->where('media_articles.polda_id', (int) $f['wilayah']);
        }

        if ($except !== 'topic' && ! empty($f['topic'])) {
            $this->applyTopic($q, (int) $f['topic']);
        }

        if ($except !== 'group' && ! empty($f['group'])) {
            $this->collapseDuplicates($q);
        }

        return $q;
    }

    protected function whereInternet(Builder $q): void
    {
        $q->where(fn (Builder $w) => $w->whereNull('media_articles.feed_id')->orWhere('media_articles.url', 'like', '%news.google.com%'));
    }

    /** @param array<string, mixed> $f */
    protected function applyPeriod(Builder $q, array $f): void
    {
        $fmt = 'Y-m-d H:i:s';
        $period = (string) ($f['period'] ?? 'all');

        $after = match ($period) {
            'today' => now($this->timezone)->startOfDay()->utc(),
            '24h' => now()->subHours(24),
            '7d' => now()->subDays(7),
            '30d' => now()->subDays(30),
            default => null,
        };
        if ($after instanceof CarbonInterface) {
            $q->where('media_articles.pub_date', '>=', $after->format($fmt));
        }

        if ($period === 'custom') {
            if (! empty($f['from'])) {
                $q->where('media_articles.pub_date', '>=', now($this->timezone)->setDateFrom(new \DateTimeImmutable($f['from']))->startOfDay()->utc()->format($fmt));
            }
            if (! empty($f['to'])) {
                $q->where('media_articles.pub_date', '<=', now($this->timezone)->setDateFrom(new \DateTimeImmutable($f['to']))->endOfDay()->utc()->format($fmt));
            }
        }
    }

    protected function applyTopic(Builder $q, int $topicId): void
    {
        $topic = $this->topics[$topicId] ?? null;
        if ($topic === null) {
            $q->whereRaw('1 = 0'); // topik di luar cakupan tenant: tidak menghasilkan apa pun

            return;
        }

        $keywords = array_values(array_filter((array) ($topic['keywords_array'] ?? []), fn ($k) => mb_strlen((string) $k) >= 3));
        $manualIds = DB::table('media_article_topic')->where('media_topic_id', $topicId)->pluck('media_article_id')->all();

        $q->where(function (Builder $w) use ($keywords, $manualIds) {
            $w->whereRaw('1 = 0');
            if ($manualIds !== []) {
                $w->orWhereIn('media_articles.id', $manualIds);
            }
            foreach ($keywords as $kw) {
                $like = NewsSearch::like((string) $kw);
                $w->orWhereRaw("media_articles.title LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("media_articles.summary LIKE ? ESCAPE '!'", [$like]);
            }
        });
    }

    /**
     * Gabungkan berita ganda (judul ternormalisasi sama) menjadi satu baris perwakilan (yang terbaru diingest).
     * Baris tanpa `title_hash` tidak pernah digabung.
     */
    protected function collapseDuplicates(Builder $q): void
    {
        $representatives = (clone $q)->toBase()
            ->selectRaw('MAX(media_articles.id)')
            ->groupByRaw('COALESCE(media_articles.title_hash, CAST(media_articles.id AS CHAR))');

        $q->whereIn('media_articles.id', $representatives);
    }

    /**
     * Satu halaman artikel + metadata paginasi.
     *
     * @param array<string, mixed> $f
     * @return array{articles: array<int, array<string, mixed>>, meta: array<string, mixed>, terms: array<int, string>}
     */
    public function page(array $f): array
    {
        $parsed = NewsSearch::parse((string) $f['q']);
        $q = $this->filtered($f);

        $total = (clone $q)->count();
        $perPage = (int) $f['per_page'];
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, (int) $f['page']), $lastPage);

        if ($f['sort'] === 'relevance' && $parsed['include'] !== []) {
            [$sql, $bindings] = NewsSearch::relevance($parsed);
            $q->selectRaw("media_articles.*, ({$sql}) as relevance_score", $bindings)->orderByDesc('relevance_score');
        } else {
            $q->select('media_articles.*');
        }

        $rows = $q->orderByDesc('media_articles.pub_date')
            ->orderByDesc('media_articles.id')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return [
            'articles' => $this->format($rows, ! empty($f['group'])),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'last_page' => $lastPage,
                'per_page' => $perPage,
                'from' => $total === 0 ? 0 : (($page - 1) * $perPage) + 1,
                'to' => min($total, $page * $perPage),
            ],
            'terms' => $parsed['include'],
        ];
    }

    /**
     * @param Collection<int, MediaArticle> $rows
     * @return array<int, array<string, mixed>>
     */
    protected function format(Collection $rows, bool $grouped): array
    {
        if ($rows->isEmpty()) {
            return [];
        }

        $pivot = DB::table('media_article_topic')
            ->whereIn('media_article_id', $rows->pluck('id')->all())
            ->get()
            ->groupBy('media_article_id');

        $siblings = collect();
        if ($grouped) {
            $hashes = $rows->pluck('title_hash')->filter()->unique()->values()->all();
            if ($hashes !== []) {
                $siblings = $this->scoped()->whereIn('media_articles.title_hash', $hashes)
                    ->get(['media_articles.id', 'media_articles.title_hash', 'media_articles.source_name', 'media_articles.url'])
                    ->groupBy('title_hash');
            }
        }

        $poldas = $this->national ? $this->poldaMeta() : collect();

        return $rows->map(function (MediaArticle $a) use ($pivot, $siblings, $poldas) {
            $manualIds = $pivot->get($a->id, collect())->pluck('media_topic_id')->map(fn ($id) => (int) $id)->all();
            $text = mb_strtolower($a->title . ' ' . $a->summary);

            $matchedIds = [];
            $matched = [];
            foreach ($this->topics as $tid => $t) {
                $hit = in_array($tid, $manualIds, true);
                if (! $hit) {
                    foreach ((array) ($t['keywords_array'] ?? []) as $kw) {
                        if ($kw !== '' && str_contains($text, $kw)) {
                            $hit = true;
                            break;
                        }
                    }
                }
                if ($hit) {
                    $matchedIds[] = $tid;
                    $matched[] = ['id' => $tid, 'title' => $t['title'], 'priority' => $t['priority']];
                }
            }

            $others = $a->title_hash ? $siblings->get($a->title_hash, collect())->where('id', '!=', $a->id)->values() : collect();

            return [
                'id' => (string) $a->id,
                'title' => $a->title,
                'source_name' => strtoupper((string) $a->source_name),
                'category' => $a->category,
                'category_badge' => strtoupper((string) $a->category),
                'time_ago' => FastTime::ago($a->getRawOriginal('created_at')),
                'pub_date' => FastTime::stamp($a->getRawOriginal('pub_date')) . ' WITA',
                'summary' => $a->summary ?: 'Cuplikan berita pantauan intelijen media belum tersedia.',
                'image_url' => $a->image_url,
                'link' => $a->url,
                'sentiment' => $a->sentiment ?: 'netral',
                // Alasan label (kata/pola yang cocok) untuk popup; aturan sama dengan NewsSentiment::detect().
                'sentiment_reason' => \App\Support\NewsSentiment::explain($a->title, $a->summary),
                'is_bookmarked' => (bool) $a->is_bookmarked,
                'feed_id' => $a->feed_id,
                'source_id' => $a->feed_id ?? 0,
                'matched_topic_ids' => $matchedIds,
                'matched_topics' => $matched,
                'manual_topic_ids' => $manualIds,
                'polda_id' => $a->polda_id,
                'polda_code' => $this->national ? ($poldas->get($a->polda_id)?->code) : null,
                'dupe_count' => $others->count(),
                'also_in' => $others->unique(fn ($o) => strtoupper((string) $o->source_name))
                    ->take(6)
                    ->map(fn ($o) => ['source_name' => strtoupper((string) $o->source_name), 'link' => $o->url])
                    ->values()
                    ->all(),
            ];
        })->all();
    }

    /** @return Collection<int, Polda> */
    protected function poldaMeta(): Collection
    {
        return $this->poldaMeta ??= Polda::whereIn('id', $this->poldaIds)->get(['id', 'code', 'name'])->keyBy('id');
    }

    /**
     * Hitungan filter. Tiap facet memakai semua filter aktif kecuali dirinya sendiri.
     *
     * @param array<string, mixed> $f
     * @param array<int, string> $registeredPortals nama portal (feed) terdaftar dalam cakupan; tampil walau 0 berita
     * @return array<string, mixed>
     */
    public function facets(array $f, array $registeredPortals = []): array
    {
        $count = fn (Builder $q) => (clone $q)->toBase()->selectRaw('COUNT(*) as c')->value('c');

        $portalBase = $this->filtered($f, 'portal');
        $portalRows = (clone $portalBase)->toBase()
            ->selectRaw('UPPER(media_articles.source_name) as src, COUNT(*) as c')
            ->groupByRaw('UPPER(media_articles.source_name)')
            ->pluck('c', 'src')
            ->all();

        // Daftar portal = portal TERDAFTAR (selalu tampil, walau 0 berita). Sumber lain (mis. hasil crawl Google News)
        // dijangkau lewat opsi "Sebaran Internet" agar dropdown tidak membengkak menjadi ratusan nama.
        $portals = [];
        foreach ($registeredPortals as $name) {
            $portals[mb_strtoupper(trim((string) $name))] = 0;
        }
        foreach ($portalRows as $name => $c) {
            if ($registeredPortals === [] || array_key_exists((string) $name, $portals)) {
                $portals[(string) $name] = (int) $c;
            }
        }
        ksort($portals);

        $internet = (int) $count((clone $portalBase)->where(fn (Builder $w) => $w->whereNull('media_articles.feed_id')->orWhere('media_articles.url', 'like', '%news.google.com%')));

        $categories = (clone $this->filtered($f, 'category'))->toBase()
            ->selectRaw('media_articles.category as name, COUNT(*) as c')
            ->groupBy('media_articles.category')
            ->pluck('c', 'name')
            ->all();
        ksort($categories);

        $sentRows = (clone $this->filtered($f, 'sentiment'))->toBase()
            ->selectRaw("COALESCE(media_articles.sentiment, 'netral') as s, COUNT(*) as c")
            ->groupByRaw("COALESCE(media_articles.sentiment, 'netral')")
            ->pluck('c', 's')
            ->all();

        $facets = [
            'all' => (int) $count($portalBase),
            'portals' => collect($portals)->map(fn ($c, $n) => ['name' => $n, 'count' => $c])->values()->all(),
            'internet' => $internet,
            'categories' => collect($categories)->map(fn ($c, $n) => ['name' => (string) $n, 'count' => (int) $c])->values()->all(),
            'sentiments' => [
                'positif' => (int) ($sentRows['positif'] ?? 0),
                'netral' => (int) ($sentRows['netral'] ?? 0),
                'negatif' => (int) ($sentRows['negatif'] ?? 0),
            ],
            'bookmarks' => (int) $count($this->filtered($f, 'bookmarks')->where('media_articles.is_bookmarked', true)),
            'wilayah' => [],
        ];

        if ($this->national) {
            $byPolda = (clone $this->filtered($f, 'wilayah'))->toBase()
                ->selectRaw('media_articles.polda_id as pid, COUNT(*) as c')
                ->groupBy('media_articles.polda_id')
                ->pluck('c', 'pid')
                ->all();
            $meta = $this->poldaMeta();
            foreach ($byPolda as $pid => $c) {
                $p = $meta->get((int) $pid);
                $facets['wilayah'][] = ['id' => (int) $pid, 'code' => $p?->code, 'name' => $p?->name ?? ('Polda #' . $pid), 'count' => (int) $c];
            }
            usort($facets['wilayah'], fn ($a, $b) => $b['count'] <=> $a['count']);
        }

        return $facets;
    }

    /** Jumlah berita per Topik Atensi (id => jumlah), memakai aturan pencocokan & penggabungan yang sama dengan daftar. */
    public function topicCounts(): array
    {
        $counts = [];
        foreach (array_keys($this->topics) as $tid) {
            $counts[$tid] = (int) $this->filtered(array_merge(self::defaultFilters(), ['topic' => $tid]))->count();
        }

        return $counts;
    }

    /**
     * KPI ringkas & tren 7 hari (tanpa filter pengguna; berita ganda sudah digabung).
     *
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $def = self::defaultFilters();
        $fmt = 'Y-m-d H:i:s';
        $a24 = now()->subDay()->format($fmt);
        $a48 = now()->subDays(2)->format($fmt);

        $in24 = array_merge($def, ['_after' => $a24]);
        $prev24 = array_merge($def, ['_after' => $a48, '_before' => $a24]);

        $trend = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = now()->subDays($i)->format('Y-m-d');
            $trend[$day] = ['date' => $day, 'label' => date('d M', strtotime($day)), 'total' => 0, 'negatif' => 0];
        }
        $rows = $this->filtered(array_merge($def, ['_after' => array_key_first($trend) . ' 00:00:00']))->toBase()
            ->selectRaw("DATE(media_articles.pub_date) as d, COUNT(*) as c, SUM(CASE WHEN media_articles.sentiment = 'negatif' THEN 1 ELSE 0 END) as n")
            ->groupByRaw('DATE(media_articles.pub_date)')
            ->get();
        foreach ($rows as $r) {
            $day = substr((string) $r->d, 0, 10);
            if (isset($trend[$day])) {
                $trend[$day]['total'] = (int) $r->c;
                $trend[$day]['negatif'] = (int) $r->n;
            }
        }

        return [
            'total' => $this->filtered($def)->count(),
            'last24' => $this->filtered($in24)->count(),
            'prev24' => $this->filtered($prev24)->count(),
            'negative24' => $this->filtered(array_merge($in24, ['sentiment' => 'negatif']))->count(),
            'negative_prev24' => $this->filtered(array_merge($prev24, ['sentiment' => 'negatif']))->count(),
            'bookmarks' => $this->scoped()->where('media_articles.is_bookmarked', true)->count(),
            'trend7' => array_values($trend),
        ];
    }

    /**
     * Bahan layar "Situasi Hari Ini" untuk pimpinan: berita negatif terbaru 24 jam & portal paling aktif.
     * Seluruhnya dari data nyata di basis data (bukan hasil AI).
     *
     * @return array<string, mixed>
     */
    public function situation(int $limit = 3): array
    {
        $def = self::defaultFilters();
        $in24 = array_merge($def, ['_after' => now()->subDay()->format('Y-m-d H:i:s')]);

        $negative = $this->filtered(array_merge($in24, ['sentiment' => 'negatif']))
            ->orderByDesc('media_articles.pub_date')
            ->limit($limit)
            ->get(['media_articles.id', 'media_articles.title', 'media_articles.source_name', 'media_articles.url', 'media_articles.pub_date', 'media_articles.created_at']);

        $sources = $this->filtered($in24)->toBase()
            ->selectRaw('UPPER(media_articles.source_name) as src, COUNT(*) as c')
            ->groupByRaw('UPPER(media_articles.source_name)')
            ->orderByDesc('c')
            ->limit(3)
            ->get();

        return [
            'top_negative' => $negative->map(fn ($a) => [
                'id' => (string) $a->id,
                'title' => $a->title,
                'source_name' => strtoupper((string) $a->source_name),
                'link' => $a->url,
                'ago' => FastTime::ago($a->getRawOriginal('pub_date')),
            ])->all(),
            'top_sources' => $sources->map(fn ($r) => ['name' => (string) $r->src, 'count' => (int) $r->c])->all(),
        ];
    }

    /**
     * Kesegaran data: kapan sinkron berhasil terakhir dan kapan berita terbaru terbit.
     * Tingkat (hijau/kuning/merah) dihitung di browser dari waktu ini agar tetap benar saat halaman dibiarkan terbuka.
     *
     * @return array<string, mixed>
     */
    public function freshness(): array
    {
        $lastSync = RssSyncLog::query()
            ->where('status', 'success')
            ->where(fn ($w) => $w->whereIn('polda_id', $this->poldaIds)->orWhereNull('polda_id'))
            ->max('created_at');
        $lastIngest = $this->scoped()->max('media_articles.created_at');
        $newestPub = $this->scoped()->max('media_articles.pub_date');

        $updated = collect([$lastSync, $lastIngest])->filter()->map(fn ($v) => strtotime((string) $v))->max();

        $iso = fn (?int $ts) => $ts ? gmdate('c', $ts) : null;
        $newestTs = $newestPub ? strtotime((string) $newestPub . ' UTC') : null;
        $updatedTs = $updated ? (int) $updated : null;

        return [
            'updated_at' => $iso($updatedTs),
            'updated_ago' => $updatedTs ? FastTime::ago(gmdate('Y-m-d H:i:s', $updatedTs)) : null,
            'newest_article_at' => $iso($newestTs),
            'newest_article_ago' => $newestPub ? FastTime::ago((string) $newestPub) : null,
        ];
    }
}
