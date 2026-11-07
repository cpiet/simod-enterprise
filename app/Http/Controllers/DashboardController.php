<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\MediaIntelligenceReport;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\SocialIntelligenceReport;
use App\Models\SocialPost;
use App\Models\Task;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the Executive Command Center Presisi Dashboard.
     */
    public function index(Request $request): Response
    {
        $allPoldas = Polda::with(['polres' => function ($q) {
            $q->where('is_active', true)->orderBy('name');
        }])->where('is_active', true)->orderBy('name')->get();

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $poldaId = $poldaId ?? ($selectedPolda?->id ?? ($allPoldas->first()?->id ?? 1));
        $isNational = $this->aggregatesNational($selectedCode);

        // 1. KPI METRICS (100% Dynamic from DB - National or Specific Polda)
        $totalArticles = MediaArticle::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->count();
        $activeFeedsCount = MediaFeed::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->where('is_active', true)->count();

        // "Aktif" = laporan yang masih berjalan (menunggu telaah, ditangani, atau tahap klarifikasi).
        // Draf, selesai (termasuk status lama resolved/closed), dan arsip tidak dihitung.
        $activeIssueStatuses = array_merge(Issue::STATUS_GROUPS['pending'], Issue::STATUS_GROUPS['active'], Issue::STATUS_GROUPS['clarified']);
        $issueScope = fn () => Issue::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId));

        $priorityIssuesCount = $issueScope()->whereIn('priority', ['critical', 'high'])->whereIn('status', $activeIssueStatuses)->count();
        $fieldReportsCount = $issueScope()->count();
        $pendingVerifyCount = $issueScope()->whereIn('status', array_merge(Issue::STATUS_GROUPS['pending'], Issue::STATUS_GROUPS['active']))->count();

        $tasksResolvedCount = Task::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->where('status', 'completed')
            ->count();
        $totalTasksCount = Task::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->count();
        // Tanpa tugas sama sekali tidak ada tingkat penyelesaian (null), bukan 100%.
        $taskCompletionRate = $totalTasksCount > 0 ? (int) round(($tasksResolvedCount / $totalTasksCount) * 100) : null;

        $polresCount = $isNational
            ? Polres::where('is_active', true)->count()
            : Polres::where('polda_id', $poldaId)->where('is_active', true)->count();
        $articles24h = MediaArticle::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))
            ->where('pub_date', '>=', now()->subDay()->format('Y-m-d H:i:s'))
            ->count();

        // 2. MATRIKS KONDUSIFITAS SATWIL (seluruhnya dari data nyata; lihat buildSatwilMatrix)
        $satwilMatrix = $this->buildSatwilMatrix($isNational, $selectedPolda, $allPoldas, $activeIssueStatuses);
        // Pin peta regional hanya untuk Polres (memiliki koordinat); mode Nasional memakai peta nasional tersendiri.
        $polresStatuses = $isNational ? [] : array_values(array_filter($satwilMatrix, fn ($r) => $r['level'] === 'polres'));
        $hasAtensiSatwil = collect($satwilMatrix)->contains('status', 'ATENSI');
        $hasWaspadaSatwil = collect($satwilMatrix)->contains('status', 'WASPADA');

        // Penilaian situasi hanya mengikuti laporan lapangan yang benar-benar ada (tidak mengklaim "kondusif" tanpa data).
        if ($fieldReportsCount === 0) {
            $overallSituation = ['text' => 'BELUM ADA LAPORAN LAPANGAN', 'color' => 'slate', 'ping' => false];
        } elseif ($hasAtensiSatwil || $priorityIssuesCount > 0) {
            $overallSituation = ['text' => 'SIAGA PRIORITAS KAMTIBMAS', 'color' => 'rose', 'ping' => true];
        } elseif ($hasWaspadaSatwil || $pendingVerifyCount > 0) {
            $overallSituation = ['text' => 'WASPADA: ADA LAPORAN AKTIF', 'color' => 'amber', 'ping' => true];
        } else {
            $overallSituation = ['text' => 'SEMUA LAPORAN TELAH DITANGANI', 'color' => 'emerald', 'ping' => false];
        }

        // 3. TOP 5 TRENDING ISSUES (100% Dynamic from DB - National or Specific Polda)
        // Isu AKTIF teratas: prioritas (kritis → rendah) lalu terbaru. (Dulu diurutkan virality_score yang selalu 0.)
        $topIssues = Issue::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->whereIn('status', $activeIssueStatuses)
            ->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
            ->latest('id')
            ->limit(5)
            ->get(['id', 'title', 'category', 'priority', 'status', 'created_at'])
            ->map(fn ($i) => ['id' => $i->id, 'title' => $i->title, 'category' => $i->category, 'priority' => $i->priority, 'status' => $i->status,
                'time' => \App\Support\FastTime::ago($i->getRawOriginal('created_at'))]);

        // 4. RADAR MEDIA SOSIAL INTELLIGENCE (Dynamic from social_posts DB)
        $socialPostsQuery = SocialPost::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId));
        $totalSocialPosts = (clone $socialPostsQuery)->count();

        $platformCounts = [
            'x'         => (clone $socialPostsQuery)->where('platform', 'x')->count(),
            'tiktok'    => (clone $socialPostsQuery)->where('platform', 'tiktok')->count(),
            'instagram' => (clone $socialPostsQuery)->where('platform', 'instagram')->count(),
            'youtube'   => (clone $socialPostsQuery)->where('platform', 'youtube')->count(),
        ];

        $socialPosCount = (clone $socialPostsQuery)->where('sentiment', 'positif')->count();
        $socialNeuCount = (clone $socialPostsQuery)->where('sentiment', 'netral')->count();
        $socialNegCount = (clone $socialPostsQuery)->where('sentiment', 'negatif')->count();

        $socialKolsCount = (clone $socialPostsQuery)->where('is_kol', true)->count();
        // null bila tidak satu pun unggahan membawa metrik (bukan 0).
        $totalSocialViews = (clone $socialPostsQuery)->whereNotNull('views_count')->exists() ? (int) (clone $socialPostsQuery)->sum('views_count') : null;
        $totalSocialLikes = (clone $socialPostsQuery)->whereNotNull('likes_count')->exists() ? (int) (clone $socialPostsQuery)->sum('likes_count') : null;

        $socialStats = [
            'total_posts'    => $totalSocialPosts,
            'platforms'      => $platformCounts,
            'platform_count' => count(array_filter($platformCounts)),
            'kol_count'      => $socialKolsCount,
            'views_count'    => $totalSocialViews,
            'likes_count'    => $totalSocialLikes,
            'sentiment'      => [
                'positif' => $totalSocialPosts > 0 ? (int) round(($socialPosCount / $totalSocialPosts) * 100) : 0,
                'netral'  => $totalSocialPosts > 0 ? (int) round(($socialNeuCount / $totalSocialPosts) * 100) : 0,
                'negatif' => $totalSocialPosts > 0 ? (int) round(($socialNegCount / $totalSocialPosts) * 100) : 0,
            ],
        ];

        // Unggahan terpopuler 7 hari: hanya yang bermetrik NYATA (akun pantauan + radar), diurutkan suka+komentar+bagikan.
        $engagement = fn ($likes, $comments, $shares) => (int) $likes + (int) $comments + (int) $shares;
        $weekAgo = now()->subDays(7);
        $fromAccounts = \App\Models\MonitoredSocialAccount::when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId))->get()
            ->flatMap(fn ($a) => collect($a->posts ?? [])->filter(fn ($p) => ! empty($p['posted_at']) && \Carbon\Carbon::parse($p['posted_at'])->gte($weekAgo) && ($p['likes'] ?? null) !== null)
                ->map(fn ($p) => ['id' => 'acc-' . md5($p['url']), 'platform' => $a->platform, 'author' => $a->display_name ?: $a->username,
                    'content' => $p['caption'], 'likes' => $p['likes'], 'comments' => $p['comments'] ?? null, 'views' => $p['views'] ?? null,
                    'url' => $p['url'], 'posted_at' => $p['posted_at'], 'engagement' => $engagement($p['likes'], $p['comments'] ?? 0, $p['shares'] ?? 0)]));
        $fromRadar = SocialPost::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->whereNotNull('likes_count')->where('posted_at', '>=', $weekAgo)
            ->orderByRaw('(COALESCE(likes_count,0) + COALESCE(comments_count,0) + COALESCE(shares_count,0)) DESC')->limit(10)->get()
            ->map(fn ($p) => ['id' => 'post-' . $p->id, 'platform' => $p->platform, 'author' => $p->author_handle ?: $p->author_name,
                'content' => $p->content, 'likes' => $p->likes_count, 'comments' => $p->comments_count, 'views' => $p->views_count,
                'url' => $p->post_url, 'posted_at' => $p->posted_at?->toIso8601String(), 'engagement' => $engagement($p->likes_count, $p->comments_count, $p->shares_count)]);
        $topViralSocialPosts = $fromAccounts->concat($fromRadar)->sortByDesc('engagement')->take(5)->values();

        // Latest 6 Real-time Social Posts Stream
        $liveSocialPosts = SocialPost::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->latest('posted_at')
            ->latest('id')
            ->limit(6)
            ->get();

        // 5. TREN SENTIMEN 7 HARI (Media Online + Medsos + Isu). Hari tanpa data tetap kosong (null), tidak diisi angka bawaan.
        $trend = $this->sentimentTrend($isNational, (int) $poldaId);
        $sentiment7Days = $trend['days'];
        $windowTotal = $trend['pos'] + $trend['neu'] + $trend['neg'];
        $avgPositivePct = $windowTotal > 0 ? (int) round(($trend['pos'] / $windowTotal) * 100) : null;

        // Indeks citra publik = pos / (pos + neg) pada 7 hari terakhir; selisih = poin terhadap 7 hari sebelumnya.
        $polarized = $trend['pos'] + $trend['neg'];
        $publicIndexScore = $polarized > 0 ? (int) round(($trend['pos'] / $polarized) * 100) : null;
        $prevPolarized = $trend['prev_pos'] + $trend['prev_neg'];
        $prevIndexScore = $prevPolarized > 0 ? (int) round(($trend['prev_pos'] / $prevPolarized) * 100) : null;
        $indexChange = ($publicIndexScore !== null && $prevIndexScore !== null) ? $publicIndexScore - $prevIndexScore : null;

        // 6. LIVE RADAR ARTICLES (Latest 6 from DB)
        $liveRadarArticles = MediaArticle::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->latest('pub_date')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'title', 'summary', 'url', 'source_name', 'sentiment', 'pub_date'])
            ->map(fn ($a) => ['id' => $a->id, 'title' => $a->title, 'summary' => $a->summary, 'url' => $a->url, 'source_name' => $a->source_name,
                'sentiment' => $a->sentiment ?: 'netral', 'time' => \App\Support\FastTime::ago($a->getRawOriginal('pub_date'))]);

        $tz = $selectedPolda->timezone ?? ($isNational ? 'Asia/Jakarta' : 'Asia/Makassar');
        $tzSuffix = $tz === 'Asia/Jakarta' ? 'WIB' : ($tz === 'Asia/Jayapura' ? 'WIT' : 'WITA');

        $metrics = [
            'total_issues'       => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'     => $pendingVerifyCount,
            'active_tasks'       => Task::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($tz)->format('d M Y, H:i:s') . ' ' . $tzSuffix,
        ];

        // 7. PETA SITUASI: tenant Divhumas/nasional → 38 provinsi dari pemberitaan 7 hari (SituationMapService);
        // tenant Polda → titik Polres dari matriks Satwil (laporan isu + berita 7 hari per Polres).
        $isDivhumasTenant = $isNational || $selectedCode === 'ALL' || $selectedPolda?->code === 'DIVHUMAS';
        $situationMap = $isDivhumasTenant
            ? ['mode' => 'national'] + app(\App\Services\SituationMapService::class)->national($isNational ? null : [(int) $poldaId])
            : ['mode' => 'regional', 'regions' => $polresStatuses, 'days' => 7, 'definitions' => [
                'ATENSI' => 'ada laporan isu aktif berprioritas tinggi/kritis',
                'WASPADA' => 'ada laporan isu aktif (prioritas lain)',
                'KONDUSIF' => 'ada laporan, seluruhnya sudah selesai/arsip',
                'BELUM ADA DATA' => 'belum ada laporan (bukan berarti aman)',
            ]];

        // 8. LATEST AI INTELLIGENCE REPORTS (Media Online & Media Sosial)
        // If national scope (selectedCode === 'ALL' or 'DIVHUMAS'), prioritize Divisi Humas Polri (Mabes) report
        $targetPoldaId = $poldaId;
        if ($isNational || $selectedCode === 'DIVHUMAS') {
            $divhumasPolda = Polda::where('code', 'DIVHUMAS')->first();
            $targetPoldaId = $divhumasPolda?->id ?? $poldaId;
        }

        $latestMediaReport = MediaIntelligenceReport::where('polda_id', $targetPoldaId)
            ->latest('report_date')
            ->latest('id')
            ->first();

        // Fallback only if no report exists for targeted polda
        if (!$latestMediaReport && $isNational) {
            $latestMediaReport = MediaIntelligenceReport::latest('report_date')->latest('id')->first();
        }

        $latestSocialReport = SocialIntelligenceReport::where('polda_id', $targetPoldaId)
            ->latest('report_date')
            ->latest('id')
            ->first();

        if (!$latestSocialReport && $isNational) {
            $latestSocialReport = SocialIntelligenceReport::latest('report_date')->latest('id')->first();
        }

        // 9. RINGKASAN ISU NASIONAL untuk Command Center daerah (baca-saja, dari laporan eksekutif Divhumas).
        // Pengecualian isolasi yang disengaja: Divhumas dipilih eksplisit oleh server (bukan input klien) dan hanya 3 field + waktu yang dikirim.
        $nationalBriefEnabled = false;
        $nationalBrief = null;
        if (! $isNational && $selectedCode !== 'DIVHUMAS') {
            $nationalBriefEnabled = (bool) (\App\Models\SecuritySetting::current(null)->share_national_brief ?? true);
            if ($nationalBriefEnabled) {
                $divId = (int) Polda::where('code', 'DIVHUMAS')->value('id');
                $nat = $divId ? MediaIntelligenceReport::where('polda_id', $divId)->latest('report_date')->latest('id')->first() : null;
                if ($nat && ($nat->top_concern || $nat->executive_brief)) {
                    $nationalBrief = [
                        'top_concern'     => $nat->top_concern,
                        'executive_brief' => $nat->executive_brief,
                        'core_decision'   => $nat->core_decision,
                        'updated_at'      => optional($nat->updated_at)->toIso8601String(),
                    ];
                }
            }
        }

        return Inertia::render('Dashboard', [
            'nationalBriefEnabled' => $nationalBriefEnabled,
            'nationalBrief'        => $nationalBrief,
            'allPoldas'            => $allPoldas,
            'selectedPolda'        => $selectedPolda,
            'selectedCode'         => $selectedCode,
            'isNational'           => $isNational,
            'situationMap'         => $situationMap,
            'latestMediaReport'    => $latestMediaReport,
            'latestSocialReport'   => $latestSocialReport,
            'kpi'                  => [
                'total_articles'       => $totalArticles,
                'active_feeds_count'   => $activeFeedsCount,
                'priority_issues'      => $priorityIssuesCount,
                'field_reports'        => $fieldReportsCount,
                'pending_verify'       => $pendingVerifyCount,
                'tasks_resolved'       => $tasksResolvedCount,
                'total_tasks'          => $totalTasksCount,
                'task_completion_rate' => $taskCompletionRate,
                'polres_count'         => $polresCount,
                'articles_24h'         => $articles24h,
            ],
            'socialStats'          => $socialStats,
            'topViralSocialPosts'  => $topViralSocialPosts,
            'liveSocialPosts'      => $liveSocialPosts,
            'satwilMatrix'         => $satwilMatrix,
            'overallSituation'     => $overallSituation,
            'topIssues'            => $topIssues,
            'sentiment7Days'       => $sentiment7Days,
            'avgPositivePct'       => $avgPositivePct,
            'liveRadarArticles'    => $liveRadarArticles,
            'publicIndex'          => [
                'score'  => $publicIndexScore,
                'status' => $publicIndexScore === null ? null : ($publicIndexScore >= 70 ? 'Positif' : ($publicIndexScore >= 50 ? 'Netral' : 'Atensi')),
                'change' => $indexChange === null ? null : (($indexChange > 0 ? '+' : '') . $indexChange . ' poin'),
                'trend'  => $indexChange === null ? null : ($indexChange > 0 ? 'up' : ($indexChange < 0 ? 'down' : 'flat')),
                'sample' => $polarized,
            ],
            'metrics'              => $metrics,
        ]);
    }

    /**
     * Matriks Kondusifitas Satwil: satu baris per Polda (mode Nasional) atau per Polres (mode Polda).
     *
     * Semua nilai dihitung dari data nyata, tanpa skor/asumsi:
     *  - Status mengikuti LAPORAN ISU AKTIF milik satwil itu (modul Manajemen Isu & Tugas):
     *      ATENSI         = ada laporan aktif berprioritas tinggi/kritis
     *      WASPADA        = ada laporan aktif (prioritas lain)
     *      KONDUSIF       = ada laporan, seluruhnya telah selesai/arsip
     *      BELUM ADA DATA = belum ada laporan sama sekali (bukan berarti aman)
     *  - Berita 7 hari: jumlah artikel (dan yang bersentimen negatif) dalam 7 hari terakhir. Mode Nasional memakai
     *    wilayah penayangan (Polda); mode Polda mencocokkan nama wilayah Polres pada judul/ringkasan berita.
     *    Angka ini informasi pendamping dan TIDAK memengaruhi status.
     *
     * @param  \Illuminate\Support\Collection<int, Polda> $allPoldas
     * @param  array<int, string> $activeStatuses
     * @return array<int, array<string, mixed>>
     */
    protected function buildSatwilMatrix(bool $isNational, ?Polda $selectedPolda, $allPoldas, array $activeStatuses): array
    {
        $quote = fn (array $values) => implode(',', array_map(fn ($s) => "'" . preg_replace('/[^a-z_]/i', '', $s) . "'", $values));
        $active = $quote($activeStatuses);
        $issueSql = "COUNT(*) as total_count, "
            . "SUM(CASE WHEN status IN ({$active}) THEN 1 ELSE 0 END) as active_count, "
            . "SUM(CASE WHEN status IN ({$active}) AND priority IN ('critical','high') THEN 1 ELSE 0 END) as priority_count, "
            . "SUM(CASE WHEN sentiment = 'positif' THEN 1 ELSE 0 END) as positive_count";
        $since = now()->subDays(7)->format('Y-m-d H:i:s');

        $classify = fn (int $total, int $priority, int $activeCount) => match (true) {
            $total === 0 => ['BELUM ADA DATA', 'slate'],
            $priority > 0 => ['ATENSI', 'rose'],
            $activeCount > 0 => ['WASPADA', 'amber'],
            default => ['KONDUSIF', 'emerald'],
        };

        $rows = [];
        $make = function (array $base, $agg, ?int $media, ?int $mediaNeg, ?string $topTitle) use ($classify) {
            $total = (int) ($agg->total_count ?? 0);
            $priority = (int) ($agg->priority_count ?? 0);
            $activeCount = (int) ($agg->active_count ?? 0);
            [$status, $color] = $classify($total, $priority, $activeCount);
            $positive = (int) ($agg->positive_count ?? 0);
            $scoreNum = $total > 0 ? (int) round(($positive / $total) * 100) : null;

            return $base + [
                'status'         => $status,
                'color'          => $color,
                'issues_count'   => $activeCount,
                'priority_count' => $priority,
                'total_issues'   => $total,
                'score'          => $scoreNum === null ? null : "{$scoreNum}%",
                'score_num'      => $scoreNum,
                'media_7d'       => $media,
                'media_negative_7d' => $mediaNeg,
                'summary'        => $topTitle ?: ($total === 0 ? 'Belum ada laporan.' : 'Tidak ada laporan aktif.'),
            ];
        };

        if ($isNational) {
            $agg = Issue::selectRaw("polda_id, {$issueSql}")->groupBy('polda_id')->get()->keyBy('polda_id');
            $media = MediaArticle::where('pub_date', '>=', $since)
                ->selectRaw("polda_id, COUNT(*) as c, SUM(CASE WHEN sentiment = 'negatif' THEN 1 ELSE 0 END) as n")
                ->groupBy('polda_id')->get()->keyBy('polda_id');
            $top = Issue::whereIn('status', $activeStatuses)->orderByDesc('virality_score')->get(['id', 'polda_id', 'title'])
                ->groupBy('polda_id')->map(fn ($g) => $g->first()->title);

            foreach ($allPoldas as $p) {
                $isMabes = $p->code === 'DIVHUMAS';
                $m = $media->get($p->id);
                $rows[] = $make([
                    'id'          => 'polda-' . $p->id,
                    'level'       => 'polda',
                    'code'        => $p->code,
                    'name'        => $isMabes ? 'Divhumas Polri (Mabes)' : $p->name,
                    'short_name'  => $isMabes ? 'Divhumas' : $p->name,
                    'switch_code' => $isMabes ? 'ALL' : $p->code,
                ], $agg->get($p->id), (int) ($m->c ?? 0), (int) ($m->n ?? 0), $top->get($p->id));
            }
        } elseif ($selectedPolda) {
            $polresList = $selectedPolda->polres->where('is_active', true)->values();
            $agg = Issue::where('polda_id', $selectedPolda->id)->whereNotNull('polres_id')
                ->selectRaw("polres_id, {$issueSql}")->groupBy('polres_id')->get()->keyBy('polres_id');
            $top = Issue::where('polda_id', $selectedPolda->id)->whereNotNull('polres_id')->whereIn('status', $activeStatuses)
                ->orderByDesc('virality_score')->get(['id', 'polres_id', 'title'])
                ->groupBy('polres_id')->map(fn ($g) => $g->first()->title);

            foreach ($polresList as $p) {
                $keyword = $p->placeKeyword();
                $media = $mediaNeg = null;
                $articles = [];
                if ($keyword !== null) {
                    $like = \App\Support\NewsSearch::like($keyword);
                    $base = MediaArticle::where('polda_id', $selectedPolda->id)->where('pub_date', '>=', $since)
                        ->where(fn ($w) => $w->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("summary LIKE ? ESCAPE '!'", [$like]));
                    $media = (clone $base)->count();
                    $mediaNeg = (clone $base)->where('sentiment', 'negatif')->count();
                    // 5 berita terbaru yang menyebut wilayah Polres ini (panel peta).
                    $articles = (clone $base)->orderByDesc('pub_date')->limit(5)->get(['title', 'url', 'source_name', 'sentiment', 'pub_date'])
                        ->map(fn ($a) => ['title' => $a->title, 'url' => $a->url, 'source' => $a->source_name, 'sentiment' => $a->sentiment ?: 'netral',
                            'time' => \App\Support\FastTime::ago($a->getRawOriginal('pub_date'))])->all();
                }

                $rows[] = $make([
                    'id'         => $p->id,
                    'level'      => 'polres',
                    'code'       => $p->code,
                    'name'       => $p->name,
                    'short_name' => $p->short_name ?: $p->name,
                    'tag_desc'   => $p->tag_desc,
                    'latitude'   => $p->latitude,
                    'longitude'  => $p->longitude,
                    'articles'   => $articles,
                    'search_q'   => $keyword !== null ? '"' . $keyword . '"' : null,
                ], $agg->get($p->id), $media, $mediaNeg, $top->get($p->id));
            }

            // Laporan tingkat Polda (tanpa Satwil) tetap terlihat agar angka matriks cocok dengan total laporan.
            $unit = Issue::where('polda_id', $selectedPolda->id)->whereNull('polres_id')->selectRaw($issueSql)->first();
            if ($unit && (int) $unit->total_count > 0) {
                $topUnit = Issue::where('polda_id', $selectedPolda->id)->whereNull('polres_id')->whereIn('status', $activeStatuses)
                    ->orderByDesc('virality_score')->value('title');
                $rows[] = $make([
                    'id' => 'polda-unit', 'level' => 'polda_unit', 'code' => $selectedPolda->code,
                    'name' => 'Tingkat Polda (tanpa Satwil)', 'short_name' => 'Tingkat Polda',
                ], $unit, null, null, $topUnit);
            }
        }

        $rank = ['ATENSI' => 0, 'WASPADA' => 1, 'KONDUSIF' => 2, 'BELUM ADA DATA' => 3];
        usort($rows, fn ($a, $b) => [$rank[$a['status']], -$a['issues_count'], $a['name']] <=> [$rank[$b['status']], -$b['issues_count'], $b['name']]);

        return $rows;
    }

    /**
     * Tren sentimen 7 hari (Media Online + Medsos + Isu) dengan 3 kueri agregat, plus hitungan 7 hari sebelumnya
     * untuk selisih indeks citra. Hari tanpa data bernilai null pada persentase (bukan angka bawaan).
     *
     * @return array{days: array<int, array<string, mixed>>, pos: int, neu: int, neg: int, prev_pos: int, prev_neg: int}
     */
    protected function sentimentTrend(bool $isNational, int $poldaId): array
    {
        $dayNames = ['Sun' => 'Min', 'Mon' => 'Sen', 'Tue' => 'Sel', 'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab'];

        $days = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $days[$date->toDateString()] = ['day' => $dayNames[$date->format('D')] ?? $date->format('D'), 'date' => $date->toDateString(), 'pos' => 0, 'neu' => 0, 'neg' => 0];
        }
        $from = array_key_first($days) . ' 00:00:00';
        $prevFrom = Carbon::parse($from)->subDays(7)->format('Y-m-d H:i:s');

        $sources = [[MediaArticle::class, 'pub_date'], [Issue::class, 'created_at'], [SocialPost::class, 'posted_at']];
        $bucket = fn (?string $s) => match ($s) { 'positif' => 'pos', 'negatif' => 'neg', default => 'neu' };
        $prev = ['pos' => 0, 'neg' => 0];

        foreach ($sources as [$model, $col]) {
            $scope = fn () => $model::query()->when(!$isNational, fn ($q) => $q->where('polda_id', $poldaId));

            foreach ($scope()->where($col, '>=', $from)->selectRaw("DATE({$col}) as d, sentiment as s, COUNT(*) as c")->groupByRaw("DATE({$col}), sentiment")->get() as $r) {
                $d = substr((string) $r->d, 0, 10);
                if (isset($days[$d])) {
                    $days[$d][$bucket($r->s)] += (int) $r->c;
                }
            }

            foreach ($scope()->where($col, '>=', $prevFrom)->where($col, '<', $from)->whereIn('sentiment', ['positif', 'negatif'])->selectRaw('sentiment as s, COUNT(*) as c')->groupBy('sentiment')->get() as $r) {
                $prev[$bucket($r->s)] += (int) $r->c;
            }
        }

        $out = [];
        $pos = $neu = $neg = 0;
        foreach ($days as $d) {
            $total = $d['pos'] + $d['neu'] + $d['neg'];
            $posPct = $total > 0 ? (int) round(($d['pos'] / $total) * 100) : null;
            $negPct = $total > 0 ? (int) round(($d['neg'] / $total) * 100) : null;
            $out[] = [
                'day'     => $d['day'],
                'date'    => $d['date'],
                'positif' => $posPct,
                'netral'  => $total > 0 ? max(0, 100 - $posPct - $negPct) : null,
                'negatif' => $negPct,
                'total'   => $total,
            ];
            $pos += $d['pos'];
            $neu += $d['neu'];
            $neg += $d['neg'];
        }

        return ['days' => $out, 'pos' => $pos, 'neu' => $neu, 'neg' => $neg, 'prev_pos' => $prev['pos'], 'prev_neg' => $prev['neg']];
    }
}
