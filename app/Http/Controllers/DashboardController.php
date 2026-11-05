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
        $topIssues = Issue::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->orderByDesc('virality_score')
            ->limit(5)
            ->get();

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
        $totalSocialViews = (clone $socialPostsQuery)->sum('views_count');
        $totalSocialLikes = (clone $socialPostsQuery)->sum('likes_count');

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

        // Top 5 Viral Social Media Posts
        $topViralSocialPosts = SocialPost::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->orderByDesc('virality_score')
            ->limit(5)
            ->get();

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
            ->get();

        $tz = $selectedPolda->timezone ?? ($isNational ? 'Asia/Jakarta' : 'Asia/Makassar');
        $tzSuffix = $tz === 'Asia/Jakarta' ? 'WIB' : ($tz === 'Asia/Jayapura' ? 'WIT' : 'WITA');

        $metrics = [
            'total_issues'       => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'     => $pendingVerifyCount,
            'active_tasks'       => Task::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($tz)->format('d M Y, H:i:s') . ' ' . $tzSuffix,
        ];

        // 7. PETA NUSANTARA INDONESIA (Agregasi Berita Berbasis Database untuk Mabes & Nasional)
        // Data peta nasional bersifat global dan berat (puluhan query LIKE); cache singkat 60 detik.
        $nationalMapData = Cache::remember('simod.map.geographic.v2.'.($isNational ? 'national' : $poldaId), 60, fn () => $this->buildNationalMapData($isNational ? null : (int) $poldaId));

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
            'nationalMapData'      => $nationalMapData,
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
            'polresStatuses'       => $polresStatuses,
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
                if ($keyword !== null) {
                    $like = \App\Support\NewsSearch::like($keyword);
                    $base = MediaArticle::where('polda_id', $selectedPolda->id)->where('pub_date', '>=', $since)
                        ->where(fn ($w) => $w->whereRaw("title LIKE ? ESCAPE '!'", [$like])->orWhereRaw("summary LIKE ? ESCAPE '!'", [$like]));
                    $media = (clone $base)->count();
                    $mediaNeg = (clone $base)->where('sentiment', 'negatif')->count();
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
                    'map_x'      => $p->map_x ?? 50,
                    'map_y'      => $p->map_y ?? 50,
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

    /**
     * Membangun Agregasi Peta Situasi Kamtibmas Nusantara (Indonesia Map)
     * Mengelompokkan berita aktual dari basis data media_articles ke 11 wilayah/Polda jajaran utama.
     */
    protected function buildNationalMapData(?int $scopePoldaId = null): array
    {
        $regions = [
            [
                'code'           => 'DIVHUMAS',
                'latitude'       => -6.2088,
                'longitude'      => 106.8456,
                'name'           => 'Mabes Polri & Metro Jaya',
                'short_name'     => 'Mabes / Metro Jaya',
                'region'         => 'DKI Jakarta & Nasional',
                'polda_id'       => 10,
                'map_x'          => 29,
                'map_y'          => 69,
            ],
            [
                'code'           => 'SULBAR',
                'latitude'       => -2.6718,
                'longitude'      => 118.8872,
                'name'           => 'Polda Sulawesi Barat',
                'short_name'     => 'Polda Sulbar',
                'region'         => 'Sulawesi Barat (6 Polres)',
                'polda_id'       => 5,
                'map_x'          => 52,
                'map_y'          => 52,
            ],
            [
                'code'           => 'SULSEL',
                'latitude'       => -5.1477,
                'longitude'      => 119.4327,
                'name'           => 'Polda Sulawesi Selatan',
                'short_name'     => 'Polda Sulsel',
                'region'         => 'Sulawesi Selatan (Makassar)',
                'polda_id'       => 7,
                'map_x'          => 52,
                'map_y'          => 67,
            ],
            [
                'code'           => 'SULTRA',
                'latitude'       => -3.9985,
                'longitude'      => 122.5126,
                'name'           => 'Polda Sulawesi Tenggara',
                'short_name'     => 'Polda Sultra',
                'region'         => 'Sulawesi Tenggara (Kendari)',
                'polda_id'       => 8,
                'map_x'          => 58,
                'map_y'          => 63,
            ],
            [
                'code'           => 'GORONTALO',
                'latitude'       => 0.5435,
                'longitude'      => 123.0568,
                'name'           => 'Polda Gorontalo & Sulut',
                'short_name'     => 'Polda Gorontalo',
                'region'         => 'Gorontalo & Teluk Tomini',
                'polda_id'       => 6,
                'map_x'          => 58,
                'map_y'          => 31,
            ],
            [
                'code'           => 'KALTIM',
                'latitude'       => -0.5022,
                'longitude'      => 117.1537,
                'name'           => 'Polda Kaltim & IKN Nusantara',
                'short_name'     => 'Polda Kaltim / IKN',
                'region'         => 'Kalimantan Timur & IKN',
                'polda_id'       => 9,
                'map_x'          => 45,
                'map_y'          => 44,
            ],
            [
                'code'           => 'SUMUT',
                'latitude'       => 3.5952,
                'longitude'      => 98.6722,
                'name'           => 'Polda Sumut & Aceh',
                'short_name'     => 'Polda Sumut',
                'region'         => 'Sumatera Bagian Utara',
                'keyword'        => 'Sumat',
                'extra_keywords' => ['Aceh', 'Medan'],
                'map_x'          => 12,
                'map_y'          => 27,
            ],
            [
                'code'           => 'JATENG',
                'latitude'       => -6.9667,
                'longitude'      => 110.4167,
                'name'           => 'Polda Jateng & DIY',
                'short_name'     => 'Polda Jateng',
                'region'         => 'Jawa Tengah (Semarang/Solo)',
                'keyword'        => 'Jawa Tengah',
                'extra_keywords' => ['Semarang', 'Sukoharjo', 'Solo', 'Yogyakarta'],
                'map_x'          => 36,
                'map_y'          => 73,
            ],
            [
                'code'           => 'JATIM',
                'latitude'       => -7.2575,
                'longitude'      => 112.7521,
                'name'           => 'Polda Jawa Timur',
                'short_name'     => 'Polda Jatim',
                'region'         => 'Jawa Timur & Madura',
                'keyword'        => 'Jawa Timur',
                'extra_keywords' => ['Surabaya', 'Malang'],
                'map_x'          => 41,
                'map_y'          => 74,
            ],
            [
                'code'           => 'BALI',
                'latitude'       => -8.65,
                'longitude'      => 115.2167,
                'name'           => 'Polda Bali & NTB/NTT',
                'short_name'     => 'Polda Bali / Nusra',
                'region'         => 'Bali & Nusa Tenggara',
                'keyword'        => 'Bali',
                'extra_keywords' => ['Mataram', 'Kupang', 'Lombok'],
                'map_x'          => 48,
                'map_y'          => 78,
            ],
            [
                'code'           => 'PAPUA',
                'latitude'       => -2.5337,
                'longitude'      => 140.7181,
                'name'           => 'Polda Papua & Jajaran',
                'short_name'     => 'Polda Papua',
                'region'         => 'Papua & Papua Barat',
                'keyword'        => 'Papua',
                'extra_keywords' => ['Jayapura', 'Sorong', 'Merauke'],
                'map_x'          => 84,
                'map_y'          => 50,
            ],
        ];

        $results = [];

        // Tenant Divhumas: pin Mabes + wilayah yang dihitung dari kata kunci pada pemberitaan Divhumas sendiri;
        // pin tenant Polda lain (mis. Sulbar) TIDAK ditampilkan agar data Polda tidak bocor ke tampilan Divhumas.
        $divhumasId = (int) Polda::where('code', 'DIVHUMAS')->value('id');
        $divhumasMode = $scopePoldaId !== null && $scopePoldaId === $divhumasId;

        foreach ($regions as $r) {
            $regionPoldaId = null;
            if (isset($r['polda_id'])) {
                $regionPoldaId = Polda::where('code', $r['code'])->value('id');
                if (! $regionPoldaId || ($scopePoldaId !== null && (int) $regionPoldaId !== $scopePoldaId)) {
                    continue;
                }
                $q = MediaArticle::where('polda_id', $regionPoldaId);
            } else {
                if ($scopePoldaId !== null && ! $divhumasMode) {
                    continue;
                }
                $regionPoldaId = Polda::where('code', 'DIVHUMAS')->value('id');
                if (! $regionPoldaId) {
                    continue;
                }
                $kws = array_merge([$r['keyword']], $r['extra_keywords'] ?? []);
                $q = MediaArticle::where('polda_id', $regionPoldaId)->where(function ($sub) use ($kws) {
                    foreach ($kws as $kw) {
                        $sub->orWhere('title', 'like', "%{$kw}%")->orWhere('summary', 'like', "%{$kw}%");
                    }
                });
            }

            // Satu query agregat menggantikan 4 query count terpisah per wilayah.
            $agg = (clone $q)->selectRaw("count(*) as total, coalesce(sum(case when sentiment = 'positif' then 1 else 0 end), 0) as pos, coalesce(sum(case when sentiment = 'negatif' then 1 else 0 end), 0) as neg, coalesce(sum(case when sentiment = 'netral' then 1 else 0 end), 0) as neu")->first();
            $total = (int) $agg->total;
            $pos = (int) $agg->pos;
            $neg = (int) $agg->neg;
            $neu = (int) $agg->neu;
            $latest = $total > 0 ? (clone $q)->latest('pub_date')->first() : null;

            $posPct = $total > 0 ? (int) round(($pos / $total) * 100) : null;
            $negPct = $total > 0 ? (int) round(($neg / $total) * 100) : null;
            $neuPct = $total > 0 ? max(0, 100 - ($posPct + $negPct)) : null;

            // Periksa Isu Terkait di Database
            $issuesCount = Issue::where(function ($iq) use ($r, $regionPoldaId) {
                if (isset($r['polda_id'])) {
                    $iq->where('polda_id', $regionPoldaId);
                } else {
                    $iq->where('polda_id', $regionPoldaId)->where('title', 'like', "%{$r['name']}%");
                }
            })->whereNotIn('status', ['selesai'])->count();

            // Status Kamtibmas / Opini
            if ($total === 0 && $issuesCount === 0) {
                $status = 'BELUM ADA DATA';
                $color = 'slate';
            } elseif (($negPct !== null && $negPct >= 15) || $issuesCount > 2) {
                $status = 'ATENSI';
                $color = 'rose';
            } elseif ($negPct >= 8 || $total > 40 || $issuesCount > 0) {
                $status = 'WASPADA';
                $color = 'amber';
            } else {
                $status = 'KONDUSIF';
                $color = 'emerald';
            }

            $results[] = [
                'id'              => $r['code'],
                'code'            => $r['code'],
                'name'            => $r['name'],
                'short_name'      => $r['short_name'],
                'region'          => $r['region'],
                'latitude'        => $r['latitude'],
                'longitude'       => $r['longitude'],
                'basis'           => isset($r['polda_id']) ? 'Tenant Polda' : 'Perkiraan kata kunci berita',
                'map_x'           => $r['map_x'],
                'map_y'           => $r['map_y'],
                'articles_count'  => $total,
                'issues_count'    => $issuesCount,
                'sentiment'       => [
                    'positif' => $posPct,
                    'netral'  => $neuPct,
                    'negatif' => $negPct,
                ],
                'score'           => $posPct === null ? null : "{$posPct}%",
                'status'          => $status,
                'color'           => $color,
                'latest_headline' => $latest ? $latest->title : null,
                'latest_source'   => $latest ? $latest->source_name : null,
                'pub_date'        => $latest?->pub_date ? Carbon::parse($latest->pub_date)->diffForHumans() : null,
            ];
        }

        return $results;
    }
}
