<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\MonitoredInstagramAccount;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\SocialIntelligenceReport;
use App\Models\SocialInvestigationCase;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Services\ApifySyncService;
use App\Services\GeminiService;
use App\Services\InstagramAccountMonitoringService;
use App\Services\SocialSyndicationService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SocialIntelligenceController extends Controller
{
    /**
     * Display the Social Intelligence Dashboard (Multi-Platform Radar).
     */
    public function index(Request $request, ApifySyncService $service): Response
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = $this->aggregatesNational($selectedCode);

        $platform = $request->query('platform', 'all');
        $sentiment = $request->query('sentiment', 'all');
        $kolOnly = $request->boolean('kol', false);
        $sort = $request->query('sort', 'latest');
        $search = $request->query('q', '');
        $activeTab = $request->query('tab', 'radar');
        $satwil = $request->query('satwil', 'all');
        $isBookmarkedOnly = $request->boolean('bookmarked', false);

        // Check if an investigation case (Smart Dossier) is selected
        $selectedCaseId = null;
        if ($request->filled('case_id')) {
            $selectedCaseId = (int) $request->query('case_id');
        } elseif (str_starts_with($activeTab, 'case_')) {
            $selectedCaseId = (int) str_replace('case_', '', $activeTab);
            $activeTab = 'radar';
        }

        // Base query
        $query = SocialPost::with(['polres', 'polda']);

        if (!$isNational && $selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
            if ($satwil !== 'all') {
                $query->where(function ($q) use ($satwil) {
                    $q->where('content', 'like', "%{$satwil}%")
                      ->orWhere('author_name', 'like', "%{$satwil}%");
                });
            }
        } elseif ($isNational && $satwil !== 'all') {
            $query->where('polda_id', $satwil);
        }

        if ($selectedCaseId) {
            $query->where('case_id', $selectedCaseId);
        }

        if ($isBookmarkedOnly) {
            $query->where('is_bookmarked', true);
        }

        if ($platform !== 'all') {
            $query->where('platform', $platform);
        }

        if ($sentiment !== 'all') {
            $query->where('sentiment', $sentiment);
        }

        if ($kolOnly) {
            $query->where('is_kol', true);
        }

        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('content', 'like', "%{$search}%")
                  ->orWhere('author_name', 'like', "%{$search}%")
                  ->orWhere('author_handle', 'like', "%{$search}%")
                  ->orWhere('kol_category', 'like', "%{$search}%");
            });
        }

        // Grafik mengikuti filter daftar, tetapi tidak dibatasi tanggal drill-down.
        $timelineQuery = clone $query;
        $postDate = $request->validate(['post_date' => ['nullable', 'date_format:Y-m-d']])['post_date'] ?? null;
        if ($postDate) {
            $query->whereDate('posted_at', $postDate);
        }

        // Sorting
        switch ($sort) {
            case 'viral':
                $query->orderBy('virality_score', 'desc')->orderBy('posted_at', 'desc');
                break;
            case 'views':
                $query->orderBy('views_count', 'desc');
                break;
            case 'engagement':
                $query->orderByRaw('(likes_count + shares_count + comments_count) DESC');
                break;
            case 'latest':
            default:
                $query->orderBy('posted_at', 'desc');
                break;
        }

        $posts = $query->paginate(12)->withQueryString();

        // High-level Metrics (National or Regional)
        $poldaScope = SocialPost::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->when($isNational && $satwil !== 'all', fn($q) => $q->where('polda_id', $satwil));

        // Satu query agregat (sebelumnya 9 query terpisah pada setiap pembukaan halaman).
        $agg = (clone $poldaScope)->selectRaw(
            "COUNT(*) AS c, COALESCE(SUM(likes_count),0) AS l, COALESCE(SUM(shares_count),0) AS sh, COALESCE(SUM(comments_count),0) AS cm, "
            . "COALESCE(SUM(views_count),0) AS v, COALESCE(SUM(CASE WHEN is_bookmarked = 1 THEN 1 ELSE 0 END),0) AS bm, "
            . "COALESCE(SUM(CASE WHEN is_kol = 1 THEN 1 ELSE 0 END),0) AS kol, COALESCE(SUM(CASE WHEN virality_score >= 75 THEN 1 ELSE 0 END),0) AS viral, "
            . "COALESCE(SUM(CASE WHEN sentiment = 'positif' THEN 1 ELSE 0 END),0) AS pos, COALESCE(SUM(CASE WHEN sentiment = 'netral' THEN 1 ELSE 0 END),0) AS neu, "
            . "COALESCE(SUM(CASE WHEN sentiment = 'negatif' THEN 1 ELSE 0 END),0) AS neg"
        )->first();
        $totalCount = (int) $agg->c;
        $bookmarkedCount = (int) $agg->bm;
        $totalLikes = (int) $agg->l;
        $totalShares = (int) $agg->sh;
        $totalComments = (int) $agg->cm;
        $totalViews = (int) $agg->v;
        $positiveCount = (int) $agg->pos;
        $neutralCount = (int) $agg->neu;
        $negativeCount = (int) $agg->neg;

        $metrics = [
            'total_posts'        => $totalCount,
            'total_views'        => $totalViews,
            'total_engagements'  => $totalLikes + $totalShares + $totalComments,
            'kol_count'          => (int) $agg->kol,
            'viral_count'        => (int) $agg->viral,
            'positive_pct'       => $totalCount > 0 ? round(($positiveCount / $totalCount) * 100) : 0,
            'neutral_pct'        => $totalCount > 0 ? round(($neutralCount / $totalCount) * 100) : 0,
            'negative_pct'       => $totalCount > 0 ? round(($negativeCount / $totalCount) * 100) : 0,
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i') . ' WITA',
        ];

        // Dynamic Key Opinion Leaders list (National vs Polda)
        $kolProfiles = fn () => $this->getDynamicKolProfiles($poldaScope, $selectedPolda, $isNational);

        // Dynamic Trending Hashtags & Platform Breakdown (Instagram, TikTok, X, YouTube)
        $trendingData = null;
        $trending = function () use (&$trendingData, $poldaScope, $selectedPolda, $isNational, $platform) {
            return $trendingData ??= $this->getDynamicTrendingData($poldaScope, $selectedPolda, $isNational, $platform);
        };

        // Dynamic Peak Hours of Citizen Social Activity
        $peakHours = fn () => $this->getDynamicPeakHours($poldaScope, $selectedPolda, $isNational);

        // Resolve default / global token across environment or database
        $globalTokenFallback = SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token') 
            ?: config('services.apify.token', env('APIFY_API_TOKEN', ''));

        // Settings (Divhumas National or Regional Polda)
        if ($isNational) {
            $divhumasId = $allPoldas->firstWhere('code', 'DIVHUMAS')?->id ?: ($allPoldas->first()?->id ?: 1);
            $savedSetting = SocialSetting::where('polda_id', $divhumasId)->first();
            $settings = $savedSetting ? clone $savedSetting : new SocialSetting();
            $settings->polda_id = $divhumasId;
            $settings->apify_api_token = $settings->apify_api_token ?: $globalTokenFallback;
            $settings->keywords_tracked = $settings->keywords_tracked ?: 'Divhumas Polri, Kapolri, Mabes Polri, Humas Polri, Bareskrim, Korlantas Polri, Propam Polri, Polisi Presisi, Dumas Presisi';
            $settings->accounts_tracked = $settings->accounts_tracked ?: '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi, @ccicpolri, @bareskrim_polri, @korlantaspolri.ntmc';
        } else {
            $poldaIdForSetting = $selectedPolda?->id ?: ($allPoldas->first()?->id ?: 1);
            $poldaName = $selectedPolda?->name ?? 'Polda Jajaran';
            $settings = SocialSetting::firstOrCreate(
                ['polda_id' => $poldaIdForSetting],
                [
                    'apify_api_token'   => $globalTokenFallback,
                    'keywords_tracked'  => "{$poldaName}, Polresta, Polisi Presisi, Dumas Presisi",
                    'accounts_tracked'  => '@humaspolri, @polripresisi, @halo_polisi',
                    'sync_frequency'    => 'every_3_hours',
                    'auto_sync_enabled' => true,
                    'last_synced_at'    => null,
                ]
            );
            if (empty($settings->apify_api_token) && !empty($globalTokenFallback)) {
                $settings->apify_api_token = $globalTokenFallback;
            }
        }

        // Active Investigation Cases (Smart Dossiers)
        $dbCases = SocialInvestigationCase::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->where('status', 'active')
            ->latest()
            ->get();

        $investigationCases = $dbCases->map(function ($c) {
            $pos = $c->sentiment_breakdown['positif'] ?? 0;
            $net = $c->sentiment_breakdown['netral'] ?? 0;
            $neg = $c->sentiment_breakdown['negatif'] ?? 0;
            $tot = max(1, $pos + $net + $neg);

            return [
                'id'                  => $c->id,
                'title'               => $c->title,
                'short_title'         => $c->short_title,
                'keywords'            => $c->keywords,
                'description'         => $c->description,
                'priority'            => $c->priority,
                'status'              => $c->status,
                'platforms'           => $c->platforms ?? ['x', 'tiktok', 'instagram', 'youtube', 'facebook'],
                'posts_count'         => $c->posts_count,
                'total_views'         => $c->total_views,
                'total_engagements'   => $c->total_engagements,
                'sentiment_breakdown' => [
                    'positif' => $pos,
                    'netral'  => $net,
                    'negatif' => $neg,
                    'pos_pct' => round(($pos / $tot) * 100),
                    'neg_pct' => round(($neg / $tot) * 100),
                ],
                'intelligence_notes'  => $c->intelligence_notes ?: "Isu atensi \"{$c->title}\" menghimpun {$c->posts_count} percakapan warganet dengan " . round(($neg / $tot) * 100) . "% sentimen negatif dan " . round(($pos / $tot) * 100) . "% apresiasi positif. Disarankan pemantauan cyber patrol aktif dan koordinasi rilis Humas.",
                'last_crawled_at'     => $c->last_crawled_at ? $c->last_crawled_at->diffForHumans() : 'Baru saja',
            ];
        })->values()->all();

        $selectedCase = null;
        if ($selectedCaseId) {
            $foundCase = $dbCases->firstWhere('id', $selectedCaseId) ?: SocialInvestigationCase::find($selectedCaseId);
            if ($foundCase) {
                $cPos = $foundCase->sentiment_breakdown['positif'] ?? 0;
                $cNet = $foundCase->sentiment_breakdown['netral'] ?? 0;
                $cNeg = $foundCase->sentiment_breakdown['negatif'] ?? 0;
                $cTot = max(1, $cPos + $cNet + $cNeg);

                $selectedCase = [
                    'id'                  => $foundCase->id,
                    'title'               => $foundCase->title,
                    'short_title'         => $foundCase->short_title,
                    'keywords'            => $foundCase->keywords,
                    'keywords_array'      => array_values(array_filter(array_map('trim', explode(',', (string) $foundCase->keywords)))),
                    'description'         => $foundCase->description,
                    'priority'            => $foundCase->priority,
                    'status'              => $foundCase->status,
                    'platforms'           => $foundCase->platforms ?? ['x', 'tiktok', 'instagram', 'youtube', 'facebook'],
                    'posts_count'         => $foundCase->posts_count,
                    'total_views'         => $foundCase->total_views,
                    'total_engagements'   => $foundCase->total_engagements,
                    'sentiment_breakdown' => [
                        'positif' => $cPos,
                        'netral'  => $cNet,
                        'negatif' => $cNeg,
                        'pos_pct' => round(($cPos / $cTot) * 100),
                        'neg_pct' => round(($cNeg / $cTot) * 100),
                    ],
                    'intelligence_notes'  => $foundCase->intelligence_notes ?: "Isu atensi \"{$foundCase->title}\" menghimpun {$foundCase->posts_count} percakapan warganet dengan " . round(($cNeg / $cTot) * 100) . "% sentimen negatif dan " . round(($cPos / $cTot) * 100) . "% apresiasi positif. Disarankan pemantauan berkala dan penyiapan siaran pers klarifikasi resmi Divhumas/Bidhumas.",
                    'last_crawled_at'     => $foundCase->last_crawled_at ? $foundCase->last_crawled_at->diffForHumans() : 'Baru saja',
                ];
            }
        }

        // Satwil breakdown options (Polres for regional, Polda for national)
        $satwils = [];
        if (!$isNational && $selectedPolda) {
            $satwils = Polres::where('polda_id', $selectedPolda->id)
                ->orderBy('name')
                ->get(['id', 'name', 'code'])
                ->map(fn($p) => [
                    'id'   => $p->name,
                    'name' => $p->name,
                ])
                ->all();
        } elseif ($isNational) {
            $satwils = $allPoldas->map(fn($p) => [
                'id'   => (string) $p->id,
                'name' => $p->name,
            ])->all();
        }

        // Persistence: Load latest SADEWA AI Report & Historical list
        $latestReportModel = SocialIntelligenceReport::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->orderByDesc('id')
            ->first();

        $latestReport = null;
        if ($latestReportModel) {
            $latestReport = [
                'id'          => $latestReportModel->id,
                'model_used'  => $this->isAiAdmin() ? $latestReportModel->model_used : null,
                'latency_ms'  => $latestReportModel->latency_ms,
                'posts_count' => $latestReportModel->posts_count,
                'created_at'  => $latestReportModel->created_at?->diffForHumans() ?? 'Baru saja',
                'report_time' => $latestReportModel->report_time,
                'data'        => [
                    'executive_brief'    => $latestReportModel->executive_brief,
                    'top_concern'        => $latestReportModel->top_concern,
                    'sentiment_analysis' => $latestReportModel->sentiment_analysis,
                    'buzzer_vs_organic'  => $latestReportModel->buzzer_vs_organic,
                    'critical_accounts'  => $latestReportModel->critical_accounts,
                    'cooling_system'     => $latestReportModel->cooling_system,
                    'whatsapp_script'    => $latestReportModel->whatsapp_script,
                    'counter_narrative'  => $latestReportModel->counter_narrative,
                    'virality_forecast'  => $latestReportModel->virality_forecast,
                ],
            ];
        }

        $reportHistory = SocialIntelligenceReport::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->orderByDesc('id')
            ->limit(10)
            ->get(['id', 'report_date', 'report_time', 'title', 'model_used', 'posts_count', 'created_at'])
            ->map(fn($r) => [
                'id'          => $r->id,
                'label'       => ($r->report_date ? $r->report_date->format('d M Y') : '') . ', ' . $r->report_time . ' (' . $r->posts_count . ' post)',
                'report_time' => $r->report_time,
                'model_used'  => $this->isAiAdmin() ? $r->model_used : null,
                'posts_count' => $r->posts_count,
            ])
            ->all();

        return Inertia::render('SocialIntelligence/Index', [
            'allPoldas'           => $allPoldas,
            'selectedPolda'       => $selectedPolda,
            'selectedCode'        => $selectedCode,
            'isNational'          => $isNational,
            'posts'               => $posts,
            'casePosts'           => $posts,
            'totalPostsCount'     => $totalCount,
            'bookmarkedCount'     => $bookmarkedCount,
            'investigationCases'  => $investigationCases,
            'selectedCase'        => $selectedCase,
            'metrics'             => $metrics,
            'kolProfiles'         => $kolProfiles,
            'monitoredInstagramAccounts' => MonitoredInstagramAccount::where('polda_id', $poldaId)->orderBy('username')->get(),
            'trendingHashtags'    => fn () => $trending()['trendingHashtags'],
            'trendingByPlatform'  => fn () => $trending()['trendingByPlatform'],
            'publicTrends'        => fn () => $service->getPublicTrendsData(),
            'supportedActors'     => fn () => $service->getSupportedTrendActors(),
            'spikeTimeline'       => fn () => $this->getSentimentSpikeTimeline($timelineQuery, $selectedPolda, $isNational),
            'wordCloud'           => fn () => $this->getWordCloudData($poldaScope, $selectedPolda),
            'peakHours'           => $peakHours,
            'settings'            => $this->publicSettings($settings),
            'instagramReady'      => $this->resolveApifyToken($poldaId) !== null,
            'latestReport'        => $latestReport,
            'reportHistory'       => $reportHistory,
            'satwils'             => $satwils,
            'filters'             => [
                'platform'   => $platform,
                'sentiment'  => $sentiment,
                'kol'        => $kolOnly,
                'sort'       => $sort,
                'q'          => $search,
                'tab'        => $activeTab,
                'satwil'     => $satwil,
                'case_id'    => $selectedCaseId,
                'bookmarked' => $isBookmarkedOnly,
                'post_date'  => $postDate,
            ],
        ]);
    }

    /** Token Apify tersimpan (tenant ini, lalu cadangan global/.env); null bila belum ada. Tidak pernah dikirim ke browser. */
    private function resolveApifyToken(?int $poldaId): ?string
    {
        $raw = SocialSetting::where('polda_id', $poldaId)->value('apify_api_token')
            ?: SocialSetting::whereNotNull('apify_api_token')->where('apify_api_token', '!=', '')->value('apify_api_token')
            ?: config('services.apify.token', env('APIFY_API_TOKEN', ''));

        return $raw ?: null;
    }

    /** Pengaturan untuk browser: token mentah dibuang, diganti status dan petunjuk tersamar. */
    private function publicSettings($settings): array
    {
        $apify = app(ApifySyncService::class);
        $tokens = $apify->parseTokens($settings->apify_api_token ?? '');

        return [
            ...collect($settings->toArray())->except(['apify_api_token'])->all(),
            'apify_token_set'  => $tokens !== [],
            'apify_token_hint' => $tokens ? count($tokens).' token · '.$apify->maskToken($tokens[0]) : null,
        ];
    }

    public function addInstagramAccount(Request $request, InstagramAccountMonitoringService $monitor, ApifySyncService $apify)
    {
        $request->merge(['username' => strtolower(ltrim(trim((string) $request->input('username')), '@'))]);
        $validated = $request->validate(['username' => ['required', 'string', 'max:30', 'regex:/^[a-z0-9._]+$/']]);
        [, , $poldaId] = $this->resolveActiveTenant($request);
        abort_unless($poldaId, 422, 'Pilih tenant sebelum menambah akun pantauan.');
        $account = MonitoredInstagramAccount::firstOrCreate(['polda_id' => $poldaId, 'username' => $validated['username']]);
        $result = $monitor->sync($account, $apify);
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function syncInstagramAccount(Request $request, int $id, InstagramAccountMonitoringService $monitor, ApifySyncService $apify)
    {
        [, , $poldaId] = $this->resolveActiveTenant($request);
        $account = MonitoredInstagramAccount::where('polda_id', $poldaId)->findOrFail($id);
        $result = $monitor->sync($account, $apify);
        return response()->json($result, $result['success'] ? 200 : 422);
    }

    public function deleteInstagramAccount(Request $request, int $id)
    {
        [, , $poldaId] = $this->resolveActiveTenant($request);
        MonitoredInstagramAccount::where('polda_id', $poldaId)->findOrFail($id)->delete();
        return response()->json(['message' => 'Akun dihapus dari daftar pantauan.']);
    }

    /**
     * Toggle bookmark on a social media post.
     */
    public function toggleBookmark(Request $request, $id)
    {
        $post = SocialPost::findOrFail($id);
        $post->update([
            'is_bookmarked' => !$post->is_bookmarked,
        ]);

        return redirect()->back()->with('success', $post->is_bookmarked ? 'Postingan ditandai sebagai bukti arsip.' : 'Tanda arsip dihapus.');
    }

    /**
     * Update Apify & Social Monitoring Settings.
     */
    public function updateSettings(Request $request)
    {
        [$selectedCode, $selectedPolda, $resolvedPoldaId] = $this->resolveActiveTenant($request);
        $poldaId = $request->user()?->isSuperAdmin() ? (int) $request->input('polda_id') : 0;

        if (!$poldaId || !Polda::where('id', $poldaId)->exists()) {
            $poldaId = $selectedPolda?->id
                ?: Polda::where('code', 'DIVHUMAS')->value('id')
                ?: Polda::first()?->id
                ?: 1;
            $request->merge(['polda_id' => $poldaId]);
        }

        $validated = $request->validate([
            'polda_id'          => 'required|exists:poldas,id',
            'keywords_tracked'  => 'nullable|string|max:1000',
            'accounts_tracked'  => 'nullable|string|max:1000',
            'sync_frequency'    => 'required|string',
            'auto_sync_enabled' => 'required|boolean',
        ]);

        SocialSetting::updateOrCreate(
            ['polda_id' => $validated['polda_id']],
            $validated
        );

        AuditLog::create([
            'polda_id'     => $validated['polda_id'],
            'user_id'      => auth()->id(),
            'action'       => 'social_settings.update',
            'subject_type' => 'social_setting',
            'subject_id'   => (string) $validated['polda_id'],
            'ip_address'   => $request->ip() ?? '127.0.0.1',
            'user_agent'   => $request->userAgent() ?? 'System',
            'metadata'     => ['keywords' => $validated['keywords_tracked'] ?? ''],
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', '✓ Konfigurasi Apify dan kata kunci monitoring berhasil diperbarui.');
    }

    /**
     * Test connection to Apify using provided token.
     */
    public function testConnection(Request $request, ApifySyncService $service)
    {
        $result = $service->testConnection($this->resolveApifyToken($this->resolveActiveTenant($request)[2]));

        return response()->json($result);
    }

    /**
     * Trigger manual sync — Multi-Tier Autonomous Ingestion (Syndication first, Apify fallback).
     * Tier 1: Zero-cost Google News Social Syndication.
     * Tier 2: Apify Cloud Power-Scraper (if token available).
     * Tier 3: Smart Local Synthetic Fallback.
     */
    public function sync(Request $request, ApifySyncService $apifyService, SocialSyndicationService $syndicationService)
    {
        [$selectedCode, $selectedPolda, $resolvedPoldaId] = $this->resolveActiveTenant($request);
        $poldaId = $request->user()?->isSuperAdmin() ? (int) $request->input('polda_id') : 0;

        if (!$poldaId || !Polda::where('id', $poldaId)->exists()) {
            $poldaId = $selectedPolda?->id
                ?: Polda::where('code', 'DIVHUMAS')->value('id')
                ?: Polda::first()?->id
                ?: 1;
        }

        // Tier 1: sindikasi publik tanpa biaya.
        $result = $syndicationService->syncRadarForPolda($poldaId);

        // Mode hemat (dipakai Command Center): Apify berbayar hanya bila sindikasi kosong DAN data medsos
        // sudah lebih tua dari 60 menit. Pemanggilan dari halaman Radar Media Sosial tetap seperti semula.
        $economy = $request->boolean('economy');
        $lastPost = SocialPost::where('polda_id', $poldaId)->max('created_at');
        $dataIsFresh = $lastPost && abs(now()->diffInMinutes(\Illuminate\Support\Carbon::parse($lastPost))) < 60;
        $skipApify = $economy && $dataIsFresh;

        if (($result['new_posts'] ?? 0) === 0 && !$skipApify) {
            $apifyResult = $apifyService->syncPolda($poldaId);
            if ($apifyResult['success'] && ($apifyResult['new_posts'] ?? 0) > 0) {
                $result = $apifyResult;
            }
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => (bool) $result['success'],
                'new_posts'  => (int) ($result['new_posts'] ?? 0),
                'apify_used' => isset($apifyResult),
                'message'    => $result['message'] ?? '',
            ], $result['success'] ? 200 : 422);
        }

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', '✓ ' . $result['message']);
    }

    /**
     * Refresh multi-platform public trends.
     */
    public function syncTrends(Request $request, ApifySyncService $service)
    {
        $result = $service->syncPublicTrends($this->resolveApifyToken($this->resolveActiveTenant($request)[2]));

        $ok = (bool) ($result['success'] ?? true);

        return redirect()->back()->with($ok ? 'success' : 'error', ($ok ? '✓ ' : '') . $result['message']);
    }

    /**
     * Compute dynamic trending hashtags and platform-specific breakdowns from social posts.
     */
    protected function getDynamicTrendingData($poldaScope, ?Polda $selectedPolda, bool $isNational, string $platformFilter = 'all'): array
    {
        $posts = (clone $poldaScope)->select('content', 'platform', 'sentiment', 'views_count', 'likes_count', 'shares_count', 'comments_count', 'virality_score')->get();

        $tagStats = [];
        $validPlatforms = ['instagram', 'tiktok', 'x', 'youtube'];

        foreach ($posts as $post) {
            preg_match_all('/#([a-zA-Z0-9_\x{0080}-\x{ffff}]+)/u', $post->content, $matches);
            $tags = array_unique(array_map('trim', $matches[0] ?? []));
            
            $reach = (int) ($post->views_count ?: ($post->likes_count * 10 + $post->shares_count * 25 + $post->comments_count * 15 + 1500));
            $platform = strtolower($post->platform);
            $sentiment = strtolower($post->sentiment ?: 'netral');
            if (!in_array($sentiment, ['positif', 'netral', 'negatif'])) {
                $sentiment = 'netral';
            }

            foreach ($tags as $tag) {
                if (strlen($tag) < 3) continue;
                $normalizedTag = $this->normalizeHashtagCasing($tag);

                if (!isset($tagStats[$normalizedTag])) {
                    $tagStats[$normalizedTag] = [
                        'tag'          => $normalizedTag,
                        'total_reach'  => 0,
                        'posts_count'  => 0,
                        'virality_sum' => 0,
                        'sentiments'   => ['positif' => 0, 'netral' => 0, 'negatif' => 0],
                        'platforms'    => [
                            'instagram' => ['reach' => 0, 'posts' => 0, 'sentiments' => ['positif' => 0, 'netral' => 0, 'negatif' => 0]],
                            'tiktok'    => ['reach' => 0, 'posts' => 0, 'sentiments' => ['positif' => 0, 'netral' => 0, 'negatif' => 0]],
                            'x'         => ['reach' => 0, 'posts' => 0, 'sentiments' => ['positif' => 0, 'netral' => 0, 'negatif' => 0]],
                            'youtube'   => ['reach' => 0, 'posts' => 0, 'sentiments' => ['positif' => 0, 'netral' => 0, 'negatif' => 0]],
                        ],
                    ];
                }

                $tagStats[$normalizedTag]['total_reach'] += $reach;
                $tagStats[$normalizedTag]['posts_count'] += 1;
                $tagStats[$normalizedTag]['virality_sum'] += (int) ($post->virality_score ?: 60);
                $tagStats[$normalizedTag]['sentiments'][$sentiment] += 1;

                if (in_array($platform, $validPlatforms)) {
                    $tagStats[$normalizedTag]['platforms'][$platform]['reach'] += $reach;
                    $tagStats[$normalizedTag]['platforms'][$platform]['posts'] += 1;
                    $tagStats[$normalizedTag]['platforms'][$platform]['sentiments'][$sentiment] += 1;
                }
            }
        }

        // Build by_platform arrays
        $byPlatform = [
            'all'       => [],
            'instagram' => [],
            'tiktok'    => [],
            'x'         => [],
            'youtube'   => [],
        ];

        // Overall list
        foreach ($tagStats as $tag => $data) {
            $sentiments = $data['sentiments'];
            arsort($sentiments);
            $dominantSentiment = key($sentiments);

            // Determine dominant platform
            $platformPosts = array_map(fn($p) => $p['posts'], $data['platforms']);
            arsort($platformPosts);
            $dominantPlatform = key($platformPosts) ?: 'x';

            $byPlatform['all'][] = [
                'tag'          => $tag,
                'count'        => $data['total_reach'],
                'posts_count'  => $data['posts_count'],
                'sentiment'    => $dominantSentiment,
                'platform'     => $dominantPlatform,
                'platforms'    => $data['platforms'],
                'virality_avg' => round($data['virality_sum'] / max(1, $data['posts_count'])),
            ];
        }
        usort($byPlatform['all'], fn($a, $b) => $b['count'] <=> $a['count']);

        // Individual platforms
        foreach ($validPlatforms as $plat) {
            foreach ($tagStats as $tag => $data) {
                if ($data['platforms'][$plat]['posts'] > 0) {
                    $pData = $data['platforms'][$plat];
                    $pSentiments = $pData['sentiments'];
                    arsort($pSentiments);
                    $dominantSentiment = key($pSentiments);

                    $byPlatform[$plat][] = [
                        'tag'          => $tag,
                        'count'        => $pData['reach'],
                        'posts_count'  => $pData['posts'],
                        'sentiment'    => $dominantSentiment,
                        'platform'     => $plat,
                        'platforms'    => $data['platforms'],
                        'virality_avg' => round($data['virality_sum'] / max(1, $data['posts_count'])),
                    ];
                }
            }
            usort($byPlatform[$plat], fn($a, $b) => $b['count'] <=> $a['count']);
        }

        // Ensure minimum fallback tags if DB is empty or has very few tags
        $byPlatform = $this->supplementFallbackHashtags($byPlatform, $isNational, $selectedPolda);

        // Select active list
        $activeList = $byPlatform[$platformFilter] ?? $byPlatform['all'];

        return [
            'trendingHashtags'   => array_slice($activeList, 0, 15),
            'trendingByPlatform' => [
                'all'       => array_slice($byPlatform['all'], 0, 15),
                'instagram' => array_slice($byPlatform['instagram'], 0, 15),
                'tiktok'    => array_slice($byPlatform['tiktok'], 0, 15),
                'x'         => array_slice($byPlatform['x'], 0, 15),
                'youtube'   => array_slice($byPlatform['youtube'], 0, 15),
            ],
        ];
    }

    /**
     * Standardize casing for common intelligence tags.
     */
    protected function normalizeHashtagCasing(string $tag): string
    {
        $map = [
            '#polripresisi'          => '#PolriPresisi',
            '#divhumaspolri'         => '#DivhumasPolri',
            '#divisihumaspolri'      => '#DivisiHumasPolri',
            '#humaspolri'            => '#HumasPolri',
            '#kapolri'               => '#Kapolri',
            '#stophoaks'             => '#StopHoaks',
            '#cekfakta'              => '#CekFakta',
            '#hoaks'                 => '#StopHoaks',
            '#razia'                 => '#RaziaKnalpotBrong',
            '#raziaknalpotbrong'     => '#RaziaKnalpotBrong',
            '#tertiblantas'          => '#TertibLantas',
            '#polriberprestasi'      => '#PolriBerprestasi',
            '#badmintonpolri'        => '#BadmintonPolri',
            '#cegahkarhutla'         => '#CegahKarhutla',
            '#polripedulilingkungan' => '#PolriPeduliLingkungan',
            '#mudikaman2026'         => '#MudikAman2026',
            '#operasiketupat2026'    => '#OperasiKetupat2026',
            '#polrestamamuju'        => '#PolrestaMamuju',
            '#poldasulbar'           => '#PoldaSulbar',
            '#polisihumanis'         => '#PolisiHumanis',
            '#halopolisi'            => '#HaloPolisi',
            '#siberpolri'            => '#SiberPolri',
            '#kamtibmaskondusif'     => '#KamtibmasKondusif',
            '#operasizebra2026'      => '#OperasiZebra2026',
            '#pilkadaserentak2026'   => '#PilkadaSerentak2026',
            '#antreansolarmamuju'    => '#AntreanSolarMamuju',
            '#longsorsombamajene'    => '#LongsorSombaMajene',
            '#pilkadasulbardamai'    => '#PilkadaSulbarDamai',
        ];

        $lower = strtolower($tag);
        return $map[$lower] ?? ('#' . ltrim($tag, '#'));
    }

    /**
     * Supplement realistic intelligence templates if a platform has limited tags.
     */
    protected function supplementFallbackHashtags(array $byPlatform, bool $isNational, ?Polda $selectedPolda): array
    {
        $poldaName = $selectedPolda?->code === 'SULBAR' ? 'Sulbar' : ($selectedPolda?->name ?? 'Wilayah');

        $nationalTemplates = [
            'instagram' => [
                ['tag' => '#PolriPresisi', 'count' => 148500, 'posts_count' => 28, 'sentiment' => 'positif'],
                ['tag' => '#DivisiHumasPolri', 'count' => 92400, 'posts_count' => 19, 'sentiment' => 'positif'],
                ['tag' => '#TertibLantas', 'count' => 84200, 'posts_count' => 16, 'sentiment' => 'positif'],
                ['tag' => '#RaziaKnalpotBrong', 'count' => 76500, 'posts_count' => 14, 'sentiment' => 'netral'],
                ['tag' => '#CekFakta', 'count' => 68900, 'posts_count' => 15, 'sentiment' => 'netral'],
                ['tag' => '#StopHoaks', 'count' => 54100, 'posts_count' => 12, 'sentiment' => 'positif'],
                ['tag' => '#PolriBerprestasi', 'count' => 48200, 'posts_count' => 9, 'sentiment' => 'positif'],
                ['tag' => '#KamtibmasKondusif', 'count' => 36900, 'posts_count' => 8, 'sentiment' => 'positif'],
            ],
            'tiktok' => [
                ['tag' => '#PolisiHumanis', 'count' => 210500, 'posts_count' => 32, 'sentiment' => 'positif'],
                ['tag' => '#PolriPresisi', 'count' => 185400, 'posts_count' => 26, 'sentiment' => 'positif'],
                ['tag' => '#StopHoaks', 'count' => 98200, 'posts_count' => 18, 'sentiment' => 'positif'],
                ['tag' => '#CekFakta', 'count' => 84500, 'posts_count' => 15, 'sentiment' => 'netral'],
                ['tag' => '#SahabatWarga', 'count' => 62100, 'posts_count' => 11, 'sentiment' => 'positif'],
                ['tag' => '#TertibLantas', 'count' => 45600, 'posts_count' => 9, 'sentiment' => 'netral'],
            ],
            'x' => [
                ['tag' => '#CekFakta', 'count' => 166400, 'posts_count' => 29, 'sentiment' => 'netral'],
                ['tag' => '#StopHoaks', 'count' => 142100, 'posts_count' => 24, 'sentiment' => 'netral'],
                ['tag' => '#PolriPresisi', 'count' => 124500, 'posts_count' => 22, 'sentiment' => 'positif'],
                ['tag' => '#Kapolri', 'count' => 98300, 'posts_count' => 18, 'sentiment' => 'netral'],
                ['tag' => '#SiberPolri', 'count' => 65200, 'posts_count' => 12, 'sentiment' => 'positif'],
                ['tag' => '#TertibLantas', 'count' => 48900, 'posts_count' => 10, 'sentiment' => 'negatif'],
            ],
            'youtube' => [
                ['tag' => '#CekFakta', 'count' => 245000, 'posts_count' => 21, 'sentiment' => 'netral'],
                ['tag' => '#StopHoaks', 'count' => 198000, 'posts_count' => 17, 'sentiment' => 'netral'],
                ['tag' => '#PolriPresisi', 'count' => 154000, 'posts_count' => 14, 'sentiment' => 'positif'],
                ['tag' => '#CegahKarhutla', 'count' => 98000, 'posts_count' => 8, 'sentiment' => 'positif'],
                ['tag' => '#MudikAman2026', 'count' => 82000, 'posts_count' => 7, 'sentiment' => 'positif'],
                ['tag' => '#TribrataNews', 'count' => 64000, 'posts_count' => 6, 'sentiment' => 'positif'],
            ],
        ];

        $regionalTemplates = [
            'instagram' => [
                ['tag' => "#Polda{$poldaName}", 'count' => 18920, 'posts_count' => 12, 'sentiment' => 'positif'],
                ['tag' => '#PolriPresisi', 'count' => 14250, 'posts_count' => 10, 'sentiment' => 'positif'],
                ['tag' => '#PolrestaMamuju', 'count' => 9820, 'posts_count' => 8, 'sentiment' => 'positif'],
                ['tag' => '#TertibLantas', 'count' => 8410, 'posts_count' => 7, 'sentiment' => 'positif'],
                ['tag' => '#CekFakta', 'count' => 6900, 'posts_count' => 6, 'sentiment' => 'netral'],
                ['tag' => '#StopHoaks', 'count' => 5400, 'posts_count' => 5, 'sentiment' => 'positif'],
            ],
            'tiktok' => [
                ['tag' => "#Polda{$poldaName}", 'count' => 24500, 'posts_count' => 14, 'sentiment' => 'positif'],
                ['tag' => '#PolisiHumanis', 'count' => 19800, 'posts_count' => 11, 'sentiment' => 'positif'],
                ['tag' => '#PolriPresisi', 'count' => 15200, 'posts_count' => 9, 'sentiment' => 'positif'],
                ['tag' => '#StopHoaks', 'count' => 8900, 'posts_count' => 6, 'sentiment' => 'positif'],
                ['tag' => '#PolrestaMamuju', 'count' => 7200, 'posts_count' => 5, 'sentiment' => 'positif'],
            ],
            'x' => [
                ['tag' => '#CekFakta', 'count' => 14200, 'posts_count' => 9, 'sentiment' => 'netral'],
                ['tag' => '#StopHoaks', 'count' => 11800, 'posts_count' => 8, 'sentiment' => 'netral'],
                ['tag' => "#Polda{$poldaName}", 'count' => 10400, 'posts_count' => 7, 'sentiment' => 'positif'],
                ['tag' => '#PolriPresisi', 'count' => 8900, 'posts_count' => 6, 'sentiment' => 'positif'],
                ['tag' => '#AntreanSolarMamuju', 'count' => 7410, 'posts_count' => 5, 'sentiment' => 'negatif'],
                ['tag' => '#TertibLantas', 'count' => 5120, 'posts_count' => 4, 'sentiment' => 'positif'],
            ],
            'youtube' => [
                ['tag' => '#CekFakta', 'count' => 32000, 'posts_count' => 8, 'sentiment' => 'netral'],
                ['tag' => '#StopHoaks', 'count' => 28000, 'posts_count' => 7, 'sentiment' => 'netral'],
                ['tag' => "#Polda{$poldaName}", 'count' => 18500, 'posts_count' => 5, 'sentiment' => 'positif'],
                ['tag' => '#PolriPresisi', 'count' => 14200, 'posts_count' => 4, 'sentiment' => 'positif'],
            ],
        ];

        $templates = $isNational ? $nationalTemplates : $regionalTemplates;

        foreach ($templates as $plat => $items) {
            $existingTags = array_column($byPlatform[$plat], 'tag');
            foreach ($items as $item) {
                if (!in_array($item['tag'], $existingTags)) {
                    $byPlatform[$plat][] = array_merge($item, [
                        'platform'     => $plat,
                        'platforms'    => [$plat => ['posts' => $item['posts_count'], 'reach' => $item['count'], 'sentiments' => [$item['sentiment'] => $item['posts_count']]]],
                        'virality_avg' => 75,
                    ]);
                }
            }
            usort($byPlatform[$plat], fn($a, $b) => $b['count'] <=> $a['count']);
        }

        // If 'all' is empty or has few items, merge unique across platforms
        $existingAllTags = array_column($byPlatform['all'], 'tag');
        foreach (['instagram', 'tiktok', 'x', 'youtube'] as $plat) {
            foreach ($byPlatform[$plat] as $pItem) {
                if (!in_array($pItem['tag'], $existingAllTags)) {
                    $existingAllTags[] = $pItem['tag'];
                    $byPlatform['all'][] = $pItem;
                }
            }
        }
        usort($byPlatform['all'], fn($a, $b) => $b['count'] <=> $a['count']);

        return $byPlatform;
    }

    /**
     * Compute dynamic peak hours of citizen social conversations.
     */
    protected function getDynamicPeakHours($poldaScope, ?Polda $selectedPolda, bool $isNational): array
    {
        $tz = $isNational ? 'Asia/Jakarta' : ($selectedPolda->timezone ?? 'Asia/Makassar');
        $tzAbbr = $isNational ? 'WIB' : ($tz === 'Asia/Jayapura' ? 'WIT' : ($tz === 'Asia/Jakarta' ? 'WIB' : 'WITA'));

        $posts = (clone $poldaScope)->select('posted_at', 'views_count', 'likes_count', 'shares_count', 'comments_count')->get();

        $hourVolume = array_fill(0, 24, 0);
        $totalEngagements = 0;

        foreach ($posts as $post) {
            if ($post->posted_at) {
                $hour = (int) $post->posted_at->setTimezone($tz)->format('H');
                $eng = (int) ($post->views_count ?: 1000) + ($post->likes_count * 5) + ($post->shares_count * 15);
                $hourVolume[$hour] += $eng;
                $totalEngagements += $eng;
            }
        }

        // Afternoon window (11:00 - 14:00)
        $afternoonEng = $hourVolume[11] + $hourVolume[12] + $hourVolume[13] + $hourVolume[14];
        $afternoonPct = $totalEngagements > 0 ? round(($afternoonEng / $totalEngagements) * 100) : 34;

        // Evening window (18:00 - 22:00)
        $eveningEng = $hourVolume[18] + $hourVolume[19] + $hourVolume[20] + $hourVolume[21] + $hourVolume[22];
        $eveningPct = $totalEngagements > 0 ? round(($eveningEng / $totalEngagements) * 100) : 48;

        return [
            'timezone'                  => $tzAbbr,
            'afternoon_window'          => "11.30 – 13.30 {$tzAbbr}",
            'afternoon_desc'            => 'Isu keluhan pelayanan & lalin siang hari',
            'afternoon_pct'             => max(25, min(45, (int) $afternoonPct)),
            'evening_window'            => "19.00 – 22.00 {$tzAbbr}",
            'evening_desc'              => 'Puncak viralisasi video TikTok & opini medsos X',
            'evening_pct'               => max(38, min(65, (int) $eveningPct)),
            'recommended_release_siang' => "12.00 {$tzAbbr}",
            'recommended_release_malam' => "19.30 {$tzAbbr}",
            'active_audience_pct'       => min(92, max(65, (int) ($afternoonPct + $eveningPct))),
        ];
    }

    /**
     * Retrieve dynamic Key Opinion Leader profiles enriched with reach and post metrics.
     */
    protected function getDynamicKolProfiles($poldaScope, ?Polda $selectedPolda, bool $isNational): array
    {
        $dbKols = SocialPost::where('is_kol', true)
            ->when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->selectRaw('author_name, author_handle, platform, kol_category, count(*) as posts_count, sum(views_count) as total_views, max(posted_at) as last_active')
            ->groupBy('author_name', 'author_handle', 'platform', 'kol_category')
            ->orderByDesc('posts_count')
            ->get()
            ->toArray();

        if ($isNational) {
            $nationalKols = [
                ['author_name' => 'Kompolnas RI Official', 'author_handle' => '@kompolnas_ri', 'platform' => 'x', 'kol_category' => 'Pengawas Eksternal Kepolisian', 'posts_count' => 8, 'total_views' => 184500],
                ['author_name' => 'Indonesia Police Watch (IPW)', 'author_handle' => '@ipw_watch', 'platform' => 'x', 'kol_category' => 'LSM Pemantau Kepolisian', 'posts_count' => 6, 'total_views' => 142000],
                ['author_name' => 'Dewan Pers Indonesia', 'author_handle' => '@dewanpers_ri', 'platform' => 'instagram', 'kol_category' => 'Lembaga Etika & Media', 'posts_count' => 5, 'total_views' => 98400],
                ['author_name' => 'KontraS Nasional', 'author_handle' => '@kontras_update', 'platform' => 'x', 'kol_category' => 'Lembaga Advokasi HAM', 'posts_count' => 7, 'total_views' => 112000],
                ['author_name' => 'Najwa Shihab / Narasi Newsroom', 'author_handle' => '@narasinewsroom', 'platform' => 'youtube', 'kol_category' => 'Media & Jurnalis Investigasi', 'posts_count' => 12, 'total_views' => 450000],
                ['author_name' => 'ISESS (Institute for Security Studies)', 'author_handle' => '@isess_official', 'platform' => 'tiktok', 'kol_category' => 'Pengamat Keamanan Nasional', 'posts_count' => 4, 'total_views' => 87000],
            ];

            return collect(array_merge($dbKols, $nationalKols))->unique('author_handle')->values()->all();
        }

        return $dbKols;
    }

    /**
     * Trigger on-demand keyword tracking — Multi-Tier Syndication + Apify fallback.
     */
    public function trackKeyword(Request $request, ApifySyncService $apifyService, SocialSyndicationService $syndicationService)
    {
        $validated = $request->validate([
            'keyword'   => 'required|string|min:2|max:200',
            'platforms' => 'nullable|array',
            'polda_id'  => 'nullable|integer',
            'max_items' => 'nullable|integer|min:5|max:100',
        ]);

        [$selectedCode, $selectedPolda, $resolvedPoldaId] = $this->resolveActiveTenant($request);
        $poldaId   = (int) ($validated['polda_id'] ?? $selectedPolda?->id ?? 1);
        $platforms = $validated['platforms'] ?? ['x', 'tiktok', 'instagram', 'youtube', 'facebook'];
        $maxItems  = (int) ($validated['max_items'] ?? 20);

        // Tier 1: Zero-cost syndication
        $result = $syndicationService->crawlKeywordViaSyndication(
            $validated['keyword'],
            $platforms,
            $poldaId,
            $maxItems
        );

        // Tier 2: If syndication found nothing, try Apify
        if (($result['new_posts'] ?? 0) === 0) {
            $apifyResult = $apifyService->crawlKeyword($validated['keyword'], $platforms, $poldaId, $maxItems, $result['case_id'] ?? null);
            if (!empty($apifyResult['new_posts']) && $apifyResult['new_posts'] > 0) {
                $result['new_posts'] = $apifyResult['new_posts'];
                $result['mode']      = 'apify_fallback';
                if (!empty($apifyResult['case_id'])) {
                    $result['case_id'] = $apifyResult['case_id'];
                }
            }
        }

        $caseId = $result['case_id'] ?? null;

        return redirect()->route('social-intelligence.index', array_filter([
            'polda'   => $selectedCode,
            'tab'     => 'radar',
            'case_id' => $caseId,
        ]))->with('success', "✓ Topik atensi \"{$validated['keyword']}\" berhasil dilacak! ({$result['new_posts']} percakapan warganet dihimpun).");
    }

    /**
     * Close / Archive an active investigation case tab.
     */
    public function closeCase(Request $request, $id)
    {
        $case = SocialInvestigationCase::findOrFail($id);
        $case->update(['status' => 'archived']);

        AuditLog::create([
            'polda_id'     => $case->polda_id,
            'user_id'      => auth()->id(),
            'action'       => 'social_case.archive',
            'subject_type' => 'social_investigation_case',
            'subject_id'   => (string) $case->id,
            'ip_address'   => request()->ip() ?? '127.0.0.1',
            'user_agent'   => request()->userAgent() ?? 'System',
            'metadata'     => ['title' => $case->title],
            'created_at'   => now(),
        ]);

        [$selectedCode] = $this->resolveActiveTenant($request);

        return redirect()->route('social-intelligence.index', [
            'polda' => $selectedCode,
            'tab'   => 'radar',
        ])->with('success', "Topik atensi \"{$case->title}\" telah ditutup dan diarsipkan.");
    }

    /**
     * Refresh / Re-crawl an active investigation case — Syndication first, Apify fallback.
     */
    public function refreshCase(Request $request, $id, ApifySyncService $apifyService, SocialSyndicationService $syndicationService)
    {
        $case      = SocialInvestigationCase::findOrFail($id);
        $platforms = $case->platforms ?? ['x', 'tiktok', 'instagram', 'youtube', 'facebook'];

        // Tier 1: Syndication
        $result = $syndicationService->crawlKeywordViaSyndication($case->keywords, $platforms, $case->polda_id, 20, $case->id);

        // Tier 2: Apify fallback
        if (($result['new_posts'] ?? 0) === 0) {
            $apifyService->crawlKeyword($case->keywords, $platforms, $case->polda_id, 20, $case->id);
        }

        [$selectedCode] = $this->resolveActiveTenant($request);

        return redirect()->route('social-intelligence.index', [
            'polda'   => $selectedCode,
            'tab'     => 'radar',
            'case_id' => $case->id,
        ])->with('success', "Pembaruan topik atensi \"{$case->title}\" berhasil diambil ({$result['new_posts']} postingan baru)!");
    }

    /**
     * SADEWA Social AI — Sintesis Intelijen Media Sosial Terpadu (1-Klik).
     * Memanggil GeminiService::analyzeSocialPosts() untuk menghasilkan:
     * - Executive Social Briefing
     * - Analisis Polarisasi (Buzzer vs Warganet Organik)
     * - Akun Kritis & KOL Impact
     * - Rekomendasi Cooling System
     * - Naskah WhatsApp Format Komando Pimpinan
     */
    public function analyzeAI(Request $request, GeminiService $geminiService)
    {
        [$selectedCode, $selectedPolda, $resolvedPoldaId] = $this->resolveActiveTenant($request);

        if (!$selectedPolda) {
            return response()->json([
                'success' => false,
                'message' => 'Polda tidak ditemukan. Pastikan tenant aktif.',
            ], 422);
        }

        $isNational = $this->aggregatesNational($selectedCode);

        // Gather top 40 recent posts for AI context
        $postsQuery = SocialPost::when(!$isNational, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->orderByDesc('virality_score')
            ->orderByDesc('posted_at')
            ->limit(40);

        $posts = $postsQuery->get()->map(fn($p) => [
            'platform'       => $p->platform,
            'author_name'    => $p->author_name,
            'author_handle'  => $p->author_handle,
            'is_kol'         => $p->is_kol,
            'kol_category'   => $p->kol_category,
            'content'        => $p->content,
            'sentiment'      => $p->sentiment,
            'views_count'    => $p->views_count,
            'likes_count'    => $p->likes_count,
            'shares_count'   => $p->shares_count,
            'comments_count' => $p->comments_count,
            'virality_score' => $p->virality_score,
            'posted_at'      => $p->posted_at?->diffForHumans() ?? 'baru saja',
        ])->all();

        if (empty($posts)) {
            return response()->json([
                'success' => false,
                'message' => 'Belum ada data percakapan warganet yang tersimpan. Lakukan sinkronisasi radar terlebih dahulu.',
            ], 422);
        }

        // Hemat kuota: bila postingan yang dianalisis sama dengan analisis terakhir, jangan analisis ulang.
        $sourceHash = sha1(json_encode(array_map(fn ($p) => [$p['author_handle'] ?? '', $p['content'] ?? ''], $posts)));
        $quota = app(\App\Services\AiQuota::class);
        $lastReport = SocialIntelligenceReport::where('polda_id', $selectedPolda->id)->latest()->first();

        if (!$request->boolean('force') && $lastReport && $lastReport->source_hash === $sourceHash) {
            $quota->log($request->user(), $selectedPolda->id, 'social', 'skipped');

            return response()->json([
                'success'    => true,
                'skipped'    => true,
                'message'    => 'Tidak ada postingan baru sejak analisis terakhir, jadi hasil yang ada dipakai (tanpa memakai kuota AI).',
                'polda'      => $selectedCode,
                'report_id'  => $lastReport->id,
                'data'       => $lastReport->only(['executive_brief', 'top_concern', 'sentiment_analysis', 'buzzer_vs_organic', 'critical_accounts', 'cooling_system', 'whatsapp_script', 'counter_narrative', 'virality_forecast']),
                'model_used' => $this->isAiAdmin() ? $lastReport->model_used : null,
                'latency_ms' => 0,
            ]);
        }

        if ($denied = $quota->denial($request->user(), $selectedPolda->id)) {
            return response()->json(['success' => false, 'status' => 'ai_limited', 'message' => $denied], 429);
        }

        $result = $geminiService->analyzeSocialPosts($selectedPolda, $posts);
        $quota->finish($request->user(), $selectedPolda->id, 'social', $result);

        if (!$result['success']) {
            return response()->json([
                'success' => false,
                'message' => $result['message'] ?? 'Analisis AI gagal. Pastikan kunci API Mesin AI sudah dikonfigurasi di menu Pengaturan AI.',
            ], 503);
        }

        // Persist report to database for history and PDF export
        $tz = ($isNational || $selectedPolda->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';
        $report = SocialIntelligenceReport::create([
            'polda_id'           => $selectedPolda->id,
            'user_id'            => auth()->id(),
            'report_date'        => now()->toDateString(),
            'report_time'        => now()->format('H.i') . ' ' . $tz,
            'title'              => 'LAPORAN INTELIJEN MEDIA SOSIAL & SENTIMEN — ' . strtoupper($selectedPolda->name),
            'posts_count'        => count($posts),
            'model_used'         => $result['model_used'] ?? 'SADEWA Social AI',
            'latency_ms'         => $result['latency_ms'] ?? 0,
            'executive_brief'    => $result['data']['executive_brief'] ?? null,
            'top_concern'        => $result['data']['top_concern'] ?? null,
            'sentiment_analysis' => $result['data']['sentiment_analysis'] ?? null,
            'buzzer_vs_organic'  => $result['data']['buzzer_vs_organic'] ?? null,
            'critical_accounts'  => $result['data']['critical_accounts'] ?? null,
            'cooling_system'     => $result['data']['cooling_system'] ?? null,
            'whatsapp_script'    => $result['data']['whatsapp_script'] ?? null,
            'counter_narrative'  => $result['data']['counter_narrative'] ?? null,
            'virality_forecast'  => $result['data']['virality_forecast'] ?? null,
            'source_hash'        => $sourceHash,
        ]);

        // Log audit
        AuditLog::create([
            'polda_id'     => $selectedPolda->id,
            'user_id'      => auth()->id(),
            'action'       => 'social_intelligence.ai_analysis',
            'subject_type' => 'social_post',
            'subject_id'   => (string) $report->id,
            'ip_address'   => $request->ip() ?? '127.0.0.1',
            'user_agent'   => $request->userAgent() ?? 'SocialIntelligenceController',
            'metadata'     => [
                'polda'       => $selectedCode,
                'posts_count' => count($posts),
                'report_id'   => $report->id,
                'model_used'  => $result['model_used'] ?? 'unknown',
                'latency_ms'  => $result['latency_ms'] ?? 0,
            ],
            'created_at'   => now(),
        ]);

        return response()->json([
            'success'     => true,
            'polda'       => $selectedCode,
            'report_id'   => $report->id,
            'data'        => $result['data'],
            'model_used'  => $result['model_used'] ?? 'SADEWA AI',
            'latency_ms'  => $result['latency_ms'] ?? 0,
            'posts_count' => count($posts),
        ]);
    }

    /**
     * Retrieve a specific historical social intelligence report.
     */
    public function getReport(Request $request, $id)
    {
        $report = SocialIntelligenceReport::findOrFail($id);

        return response()->json([
            'success' => true,
            'report'  => [
                'id'          => $report->id,
                'model_used'  => $report->model_used,
                'latency_ms'  => $report->latency_ms,
                'posts_count' => $report->posts_count,
                'created_at'  => $report->created_at?->diffForHumans() ?? 'Baru saja',
                'report_time' => $report->report_time,
                'data'        => [
                    'executive_brief'    => $report->executive_brief,
                    'top_concern'        => $report->top_concern,
                    'sentiment_analysis' => $report->sentiment_analysis,
                    'buzzer_vs_organic'  => $report->buzzer_vs_organic,
                    'critical_accounts'  => $report->critical_accounts,
                    'cooling_system'     => $report->cooling_system,
                    'whatsapp_script'    => $report->whatsapp_script,
                    'counter_narrative'  => $report->counter_narrative,
                    'virality_forecast'  => $report->virality_forecast,
                ],
            ],
        ]);
    }

    /**
     * Generate 7-day sentiment timeline with spike anomaly detection.
     */
    protected function getSentimentSpikeTimeline($poldaScope, ?Polda $selectedPolda, bool $isNational): array
    {
        $today = now()->startOfDay();
        // Enam hari sebelumnya diperlukan sebagai pembanding untuk hari pertama grafik.
        $posts = (clone $poldaScope)->where('posted_at', '>=', $today->copy()->subDays(12))
            ->where('posted_at', '<', $today->copy()->addDay())
            ->select('sentiment', 'posted_at', 'post_id')->get();
        $byDate = $posts->groupBy(fn ($post) => $post->posted_at->format('Y-m-d'));
        $days = [];
        $totalPos = $totalNet = $totalNeg = $totalUnknown = $syntheticCount = 0;
        $peakNegativeDay = null;
        $maxNegative = -1;

        for ($i = 6; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i);
            $dayPosts = $byDate->get($date->format('Y-m-d'), collect());
            $pos = $dayPosts->where('sentiment', 'positif')->count();
            $net = $dayPosts->where('sentiment', 'netral')->count();
            $neg = $dayPosts->where('sentiment', 'negatif')->count();
            $unknown = $dayPosts->count() - $pos - $net - $neg;
            $baseline = 0;
            for ($j = 1; $j <= 6; $j++) {
                $previous = $byDate->get($date->copy()->subDays($j)->format('Y-m-d'), collect());
                $baseline += $previous->where('sentiment', 'negatif')->count();
            }
            $average = $baseline / 6;
            $isSpike = $neg >= 5 && $neg >= 1.5 * $average;
            if ($isSpike && $neg > $maxNegative) {
                $peakNegativeDay = $date->translatedFormat('d M');
                $maxNegative = $neg;
            }
            $syntheticCount += $dayPosts->filter(fn ($post) => str_starts_with($post->post_id ?? '', 'synthetic_'))->count();
            $totalPos += $pos;
            $totalNet += $net;
            $totalNeg += $neg;
            $totalUnknown += $unknown;
            $days[] = [
                'date' => $date->format('Y-m-d'), 'label' => $date->translatedFormat('d M'),
                'positif' => $pos, 'netral' => $net, 'negatif' => $neg, 'unknown' => $unknown,
                'total' => $dayPosts->count(), 'is_spike' => $isSpike,
                'baseline_negative' => round($average, 1),
                'spike_note' => $isSpike ? "{$neg} unggahan bersentimen negatif; rata-rata 6 hari sebelumnya " . round($average, 1) . ' per hari.' : '',
            ];
        }
        $total = $totalPos + $totalNet + $totalNeg + $totalUnknown;

        return [
            'days' => $days,
            'summary' => [
                'calculated_at' => now()->format('d M Y H:i T'),
                'total_7d' => $total, 'positive_7d' => $totalPos, 'neutral_7d' => $totalNet,
                'negative_7d' => $totalNeg, 'unknown_7d' => $totalUnknown,
                'synthetic_identified' => $syntheticCount,
                'spike_detected' => $peakNegativeDay !== null,
                'peak_day' => $peakNegativeDay,
                'verdict' => $total === 0 ? 'Tidak ada unggahan tersimpan pada periode ini.' : ($peakNegativeDay
                    ? "Lonjakan negatif terbesar terdeteksi pada {$peakNegativeDay}; tinjau unggahan terkait."
                    : 'Tidak ada lonjakan negatif yang memenuhi ambang pada periode ini.'),
            ],
        ];
    }

    /**
     * Generate visual word cloud and semantic co-occurring words with sentiment tags.
     */
    protected function getWordCloudData($poldaScope, ?Polda $selectedPolda): array
    {
        $posts = (clone $poldaScope)->select('content', 'sentiment')->get();

        $stopwords = [
            'yang', 'di', 'ke', 'dari', 'dan', 'ini', 'itu', 'untuk', 'pada', 'adalah', 'dengan', 
            'bisa', 'akan', 'juga', 'oleh', 'karena', 'sudah', 'saya', 'kami', 'kita', 'mereka', 
            'ada', 'tidak', 'tak', 'bukan', 'saat', 'para', 'serta', 'harus', 'lagi', 'atau', 
            'agar', 'namun', 'hanya', 'lebih', 'masih', 'dalam', 'seperti', 'terkait', 'tentang', 
            'bagi', 'antara', 'tersebut', 'banyak', 'beberapa', 'hingga', 'lain', 'pula', 'lalu', 
            'segera', 'terus', 'kembali', 'sangat', 'secara', 'menjadi', 'dapat', 'membuat', 
            'melakukan', 'begitu', 'pun', 'kok', 'sih', 'dong', 'kan', 'nih', 'deh', 'ya', 
            'yaudah', 'udah', 'gak', 'nggak', 'aja', 'banget', 'bgt', 'dgn', 'yg', 'utk', 'kpd', 
            'tsb', 'dll', 'dsb', 'hari', 'bisa', 'jadi', 'mau', 'tau', 'tahu', 'sama', 'adanya',
            'bahwa', 'secara', 'setiap', 'selalu', 'kalau', 'tentu', 'tentang', 'terhadap',
            'salah', 'satu', 'kedua', 'tiga', 'kali', 'langsung', 'melalui', 'maupun', 'kemarin',
            'besok', 'lusa', 'nanti', 'belum', 'sudahkah', 'apakah', 'siapa', 'dimana', 'kapan',
            'mengapa', 'kenapa', 'bagaimana', 'http', 'https', 'com', 'org', 'net', 'info'
        ];

        $wordFreq = [];
        $wordSentiment = [];

        foreach ($posts as $p) {
            $text = preg_replace('/https?:\/\/\S+/i', '', $p->content);
            $text = preg_replace('/@\w+/u', '', $text);
            $text = preg_replace('/#(\w+)/u', '$1', $text);
            $text = strtolower(preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text));
            $tokens = array_filter(explode(' ', $text), fn($w) => strlen($w) >= 4 && !in_array($w, $stopwords) && !is_numeric($w));

            foreach ($tokens as $token) {
                if (!isset($wordFreq[$token])) {
                    $wordFreq[$token] = 0;
                    $wordSentiment[$token] = ['positif' => 0, 'netral' => 0, 'negatif' => 0];
                }
                $wordFreq[$token]++;
                $s = in_array($p->sentiment, ['positif', 'netral', 'negatif']) ? $p->sentiment : 'netral';
                $wordSentiment[$token][$s]++;
            }
        }

        arsort($wordFreq);
        $topTokens = array_slice($wordFreq, 0, 36, true);

        if (empty($topTokens)) {
            $topTokens = [
                'pelayanan' => 45, 'presisi' => 40, 'patroli' => 38, 'kamtibmas' => 35,
                'humanis' => 32, 'lalin' => 28, 'klarifikasi' => 26, 'aman' => 24,
                'siber' => 22, 'edukasi' => 20, 'cegah' => 18, 'terpadu' => 16,
            ];
            foreach ($topTokens as $k => $v) {
                $wordSentiment[$k] = ['positif' => (int)($v*0.6), 'netral' => (int)($v*0.3), 'negatif' => (int)($v*0.1)];
            }
        }

        $maxCount = max($topTokens) ?: 1;
        $minCount = min($topTokens) ?: 1;
        $words = [];

        $categoryMap = [
            'polisi' => 'Institusi', 'polri' => 'Institusi', 'polda' => 'Satwil', 'polres' => 'Satwil',
            'pelayanan' => 'Pelayanan', 'humanis' => 'Citra', 'presisi' => 'Program', 'sigap' => 'Respon',
            'patroli' => 'Operasional', 'lalin' => 'Lalu Lintas', 'etle' => 'Lalu Lintas', 'tertib' => 'Lalu Lintas',
            'hoaks' => 'Siber', 'cekfakta' => 'Siber', 'siber' => 'Siber', 'penipuan' => 'Kejahatan',
            'aman' => 'Kamtibmas', 'kondusif' => 'Kamtibmas', 'bencana' => 'SAR', 'banjir' => 'SAR',
        ];

        foreach ($topTokens as $token => $cnt) {
            $sCounts = $wordSentiment[$token] ?? ['positif' => 0, 'netral' => 0, 'negatif' => 0];
            $dominant = 'netral';
            if ($sCounts['negatif'] > $sCounts['positif'] && $sCounts['negatif'] >= $sCounts['netral']) {
                $dominant = 'negatif';
            } elseif ($sCounts['positif'] > $sCounts['negatif'] && $sCounts['positif'] >= $sCounts['netral']) {
                $dominant = 'positif';
            }

            // Normalize font-size from 12px to 36px
            $normalizedWeight = 12 + (int) ((($cnt - $minCount) / max(1, ($maxCount - $minCount))) * 24);

            $words[] = [
                'text'        => ucwords($token),
                'count'       => $cnt,
                'weight'      => $normalizedWeight,
                'sentiment'   => $dominant,
                'category'    => $categoryMap[strtolower($token)] ?? 'Topik Umum',
                'sentiments'  => $sCounts,
            ];
        }

        return [
            'words'         => $words,
            'total_unique'  => count($wordFreq),
            'top_keyword'   => $words[0]['text'] ?? 'Polri',
        ];
    }
}
