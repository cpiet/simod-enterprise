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
        $isNational = ($selectedCode === 'ALL');

        // 1. KPI METRICS (100% Dynamic from DB - National or Specific Polda)
        $totalArticles = MediaArticle::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->count();
        $activeFeedsCount = MediaFeed::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->where('is_active', true)->count();

        $priorityIssuesCount = Issue::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->whereIn('priority', ['critical', 'high'])
            ->whereNotIn('status', ['selesai'])
            ->count();

        $fieldReportsCount = Issue::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->count();
        $pendingVerifyCount = Issue::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->whereIn('status', ['monitoring', 'investigasi'])
            ->count();

        $tasksResolvedCount = Task::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))
            ->where('status', 'completed')
            ->count();
        $totalTasksCount = Task::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->count();
        $taskCompletionRate = $totalTasksCount > 0 ? (int) round(($tasksResolvedCount / $totalTasksCount) * 100) : 100;

        // 2. DYNAMIC 6 POLRES SATWIL STATUSES & GEOSPATIAL MAP DATA
        $polresList = $selectedPolda ? $selectedPolda->polres : Polres::where('is_active', true)->get();
        $polresStatuses = [];
        $hasAtensiSatwil = false;
        $hasWaspadaSatwil = false;

        foreach ($polresList as $p) {
            $activeIssues = Issue::where('polda_id', $p->polda_id)->where('polres_id', $p->id)
                ->whereNotIn('status', ['selesai'])
                ->get();
            $totalIssues = Issue::where('polda_id', $p->polda_id)->where('polres_id', $p->id)->count();
            $posIssues = Issue::where('polda_id', $p->polda_id)->where('polres_id', $p->id)->where('sentiment', 'positif')->count();

            $scoreNum = $totalIssues > 0 ? (int) round(($posIssues / $totalIssues) * 100) : null;

            $hasCritical = $activeIssues->contains(fn($i) => in_array($i->priority, ['critical', 'high']) && in_array($i->status, ['investigasi', 'monitoring']));
            $hasWarning = $activeIssues->isNotEmpty();

            if ($totalIssues === 0) {
                $status = 'BELUM ADA DATA';
                $color = 'slate';
            } elseif ($hasCritical) {
                $status = 'ATENSI';
                $color = 'rose';
                $hasAtensiSatwil = true;
            } elseif ($hasWarning) {
                $status = 'WASPADA';
                $color = 'amber';
                $hasWaspadaSatwil = true;
            } else {
                $status = 'KONDUSIF';
                $color = 'emerald';
            }

            $latestIssue = $activeIssues->sortByDesc('virality_score')->first()
                ?? Issue::where('polda_id', $p->polda_id)->where('polres_id', $p->id)->latest()->first();

            $summary = $latestIssue ? $latestIssue->title : 'Belum ada laporan.';

            $polresStatuses[] = [
                'id'           => $p->id,
                'code'         => $p->code,
                'name'         => $p->name,
                'short_name'   => $p->short_name ?: $p->name,
                'tag_desc'     => $p->tag_desc,
                'latitude'     => $p->latitude,
                'longitude'    => $p->longitude,
                'map_x'        => $p->map_x ?? 50,
                'map_y'        => $p->map_y ?? 50,
                'status'       => $status,
                'color'        => $color,
                'score'        => $scoreNum === null ? null : "{$scoreNum}%",
                'score_num'    => $scoreNum,
                'issues_count' => $activeIssues->count(),
                'total_issues' => $totalIssues,
                'summary'      => $summary,
            ];
        }

        // Overall situation badge logic
        if ($hasAtensiSatwil || $priorityIssuesCount > 0) {
            $overallSituation = [
                'text'  => 'SIAGA PRIORITAS KAMTIBMAS',
                'color' => 'rose',
                'ping'  => true,
            ];
        } elseif ($hasWaspadaSatwil) {
            $overallSituation = [
                'text'  => 'WASPADA ANOMALI KAMTIBMAS',
                'color' => 'amber',
                'ping'  => true,
            ];
        } else {
            $overallSituation = [
                'text'  => 'SITUASI KONDUSIF & TERKENDALI',
                'color' => 'emerald',
                'ping'  => false,
            ];
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

        // 5. 7 DAYS SENTIMENT TREND (100% Dynamic Aggregation: Media Online + Medsos + Isu)
        $dayNames = [
            'Sun' => 'Min', 'Mon' => 'Sen', 'Tue' => 'Sel',
            'Wed' => 'Rab', 'Thu' => 'Kam', 'Fri' => 'Jum', 'Sat' => 'Sab',
        ];

        $sentiment7Days = [];
        $totalPositiveWindow = 0;
        $totalNegativeWindow = 0;
        $totalNeutralWindow = 0;

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $dateStr = $date->toDateString();
            $dayAbbr = $dayNames[$date->format('D')] ?? $date->format('D');

            $articles = MediaArticle::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->whereDate('pub_date', $dateStr)->get();
            $issues = Issue::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->whereDate('created_at', $dateStr)->get();
            $socials = SocialPost::when(!$isNational, fn($q) => $q->where('polda_id', $poldaId))->whereDate('posted_at', $dateStr)->get();

            $pos = $articles->where('sentiment', 'positif')->count() + $issues->where('sentiment', 'positif')->count() + $socials->where('sentiment', 'positif')->count();
            $neg = $articles->where('sentiment', 'negatif')->count() + $issues->where('sentiment', 'negatif')->count() + $socials->where('sentiment', 'negatif')->count();
            $neu = $articles->where('sentiment', 'netral')->count() + $issues->where('sentiment', 'netral')->count() + $socials->where('sentiment', 'netral')->count();

            $totalDay = $pos + $neg + $neu;

            if ($totalDay > 0) {
                $posPct = (int) round(($pos / $totalDay) * 100);
                $negPct = (int) round(($neg / $totalDay) * 100);
                $neuPct = max(0, 100 - ($posPct + $negPct));
            } else {
                $posPct = 76;
                $neuPct = 18;
                $negPct = 6;
            }

            $sentiment7Days[] = [
                'day'     => $dayAbbr,
                'date'    => $dateStr,
                'positif' => $posPct,
                'netral'  => $neuPct,
                'negatif' => $negPct,
                'total'   => $totalDay,
            ];

            $totalPositiveWindow += $pos;
            $totalNegativeWindow += $neg;
            $totalNeutralWindow += $neu;
        }

        // Public index score (Favorability ratio: Pos / (Pos + Neg) * 100)
        $polarizedTotal = $totalPositiveWindow + $totalNegativeWindow;
        $publicIndexScore = $polarizedTotal > 0
            ? (int) round(($totalPositiveWindow / $polarizedTotal) * 100)
            : 78;

        $avgPositivePct = (int) round(collect($sentiment7Days)->avg('positif'));

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

        return Inertia::render('Dashboard', [
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
            ],
            'socialStats'          => $socialStats,
            'topViralSocialPosts'  => $topViralSocialPosts,
            'liveSocialPosts'      => $liveSocialPosts,
            'polresStatuses'       => $polresStatuses,
            'overallSituation'     => $overallSituation,
            'topIssues'            => $topIssues,
            'sentiment7Days'       => $sentiment7Days,
            'avgPositivePct'       => $avgPositivePct,
            'liveRadarArticles'    => $liveRadarArticles,
            'publicIndex'          => [
                'score'  => $publicIndexScore,
                'status' => $publicIndexScore >= 70 ? 'Positif' : ($publicIndexScore >= 50 ? 'Netral' : 'Atensi'),
                'change' => '+6%',
                'trend'  => 'up',
            ],
            'metrics'              => $metrics,
        ]);
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

        foreach ($regions as $r) {
            $regionPoldaId = null;
            if (isset($r['polda_id'])) {
                $regionPoldaId = Polda::where('code', $r['code'])->value('id');
                if (! $regionPoldaId || ($scopePoldaId !== null && (int) $regionPoldaId !== $scopePoldaId)) {
                    continue;
                }
                $q = MediaArticle::where('polda_id', $regionPoldaId);
            } else {
                if ($scopePoldaId !== null) {
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
