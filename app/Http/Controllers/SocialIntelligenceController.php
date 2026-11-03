<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\SocialInvestigationCase;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Services\ApifySyncService;
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
        $isNational = ($selectedCode === 'ALL');

        $platform = $request->query('platform', 'all');
        $sentiment = $request->query('sentiment', 'all');
        $kolOnly = $request->boolean('kol', false);
        $sort = $request->query('sort', 'latest');
        $search = $request->query('q', '');
        $activeTab = $request->query('tab', 'radar');
        $satwil = $request->query('satwil', 'all');

        // Base query
        $query = SocialPost::with(['polres', 'polda']);

        if (!$isNational && $selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        } elseif ($isNational && $satwil !== 'all') {
            $query->where('polda_id', $satwil);
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

        $totalCount = (clone $poldaScope)->count();
        $totalLikes = (clone $poldaScope)->sum('likes_count');
        $totalShares = (clone $poldaScope)->sum('shares_count');
        $totalComments = (clone $poldaScope)->sum('comments_count');
        $totalViews = (clone $poldaScope)->sum('views_count');

        $positiveCount = (clone $poldaScope)->where('sentiment', 'positif')->count();
        $neutralCount = (clone $poldaScope)->where('sentiment', 'netral')->count();
        $negativeCount = (clone $poldaScope)->where('sentiment', 'negatif')->count();

        $metrics = [
            'total_posts'        => $totalCount,
            'total_views'        => $totalViews,
            'total_engagements'  => $totalLikes + $totalShares + $totalComments,
            'kol_count'          => (clone $poldaScope)->where('is_kol', true)->count(),
            'viral_count'        => (clone $poldaScope)->where('virality_score', '>=', 75)->count(),
            'positive_pct'       => $totalCount > 0 ? round(($positiveCount / $totalCount) * 100) : 0,
            'neutral_pct'        => $totalCount > 0 ? round(($neutralCount / $totalCount) * 100) : 0,
            'negative_pct'       => $totalCount > 0 ? round(($negativeCount / $totalCount) * 100) : 0,
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i') . ' WITA',
        ];

        // Dynamic Key Opinion Leaders list (National vs Polda)
        $kolProfiles = $this->getDynamicKolProfiles($poldaScope, $selectedPolda, $isNational);

        // Dynamic Trending Hashtags & Platform Breakdown (Instagram, TikTok, X, YouTube)
        $trendingData = $this->getDynamicTrendingData($poldaScope, $selectedPolda, $isNational, $platform);

        // Dynamic Peak Hours of Citizen Social Activity
        $peakHours = $this->getDynamicPeakHours($poldaScope, $selectedPolda, $isNational);

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
                    'last_synced_at'    => now()->subMinutes(15),
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

        // Check if an investigation case tab is selected
        $selectedCaseId = null;
        if ($request->has('case_id')) {
            $selectedCaseId = (int) $request->query('case_id');
        } elseif (str_starts_with($activeTab, 'case_')) {
            $selectedCaseId = (int) str_replace('case_', '', $activeTab);
        }

        $selectedCase = null;
        $casePosts = null;

        if ($selectedCaseId) {
            $foundCase = $dbCases->firstWhere('id', $selectedCaseId) ?: SocialInvestigationCase::find($selectedCaseId);
            if ($foundCase) {
                $activeTab = 'case_' . $foundCase->id;
                $casePostsQuery = SocialPost::where('case_id', $foundCase->id)
                    ->when($platform !== 'all', fn($q) => $q->where('platform', $platform))
                    ->when($sentiment !== 'all', fn($q) => $q->where('sentiment', $sentiment))
                    ->orderBy('posted_at', 'desc');

                $casePosts = $casePostsQuery->paginate(12)->withQueryString();

                $cPos = $foundCase->sentiment_breakdown['positif'] ?? 0;
                $cNet = $foundCase->sentiment_breakdown['netral'] ?? 0;
                $cNeg = $foundCase->sentiment_breakdown['negatif'] ?? 0;
                $cTot = max(1, $cPos + $cNet + $cNeg);

                $selectedCase = [
                    'id'                  => $foundCase->id,
                    'title'               => $foundCase->title,
                    'short_title'         => $foundCase->short_title,
                    'keywords'            => $foundCase->keywords,
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

        return Inertia::render('SocialIntelligence/Index', [
            'allPoldas'           => $allPoldas,
            'selectedPolda'       => $selectedPolda,
            'selectedCode'        => $selectedCode,
            'isNational'          => $isNational,
            'posts'               => $posts,
            'investigationCases'  => $investigationCases,
            'selectedCase'        => $selectedCase,
            'casePosts'           => $casePosts,
            'metrics'             => $metrics,
            'kolProfiles'         => $kolProfiles,
            'trendingHashtags'    => $trendingData['trendingHashtags'],
            'trendingByPlatform'  => $trendingData['trendingByPlatform'],
            'publicTrends'        => $service->getPublicTrendsData(),
            'supportedActors'     => $service->getSupportedTrendActors(),
            'spikeTimeline'       => $this->getSentimentSpikeTimeline($poldaScope, $selectedPolda, $isNational),
            'wordCloud'           => $this->getWordCloudData($poldaScope, $selectedPolda),
            'peakHours'           => $peakHours,
            'settings'            => $settings,
            'filters'             => [
                'platform'  => $platform,
                'sentiment' => $sentiment,
                'kol'       => $kolOnly,
                'sort'      => $sort,
                'q'         => $search,
                'tab'       => $activeTab,
                'satwil'    => $satwil,
            ],
        ]);
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
        $poldaId = (int) $request->input('polda_id');

        if (!$poldaId || !Polda::where('id', $poldaId)->exists()) {
            $poldaId = $selectedPolda?->id
                ?: Polda::where('code', 'DIVHUMAS')->value('id')
                ?: Polda::first()?->id
                ?: 1;
            $request->merge(['polda_id' => $poldaId]);
        }

        $validated = $request->validate([
            'polda_id'          => 'required|exists:poldas,id',
            'apify_api_token'   => 'nullable|string|max:1000',
            'keywords_tracked'  => 'nullable|string|max:1000',
            'accounts_tracked'  => 'nullable|string|max:1000',
            'sync_frequency'    => 'required|string',
            'auto_sync_enabled' => 'required|boolean',
        ]);

        SocialSetting::updateOrCreate(
            ['polda_id' => $validated['polda_id']],
            $validated
        );

        // If updated from National scope (DIVHUMAS) or single-tenant host, propagate token to any empty polda
        if (!empty($validated['apify_api_token']) && ($selectedCode === 'ALL' || Polda::where('id', $poldaId)->value('code') === 'DIVHUMAS')) {
            SocialSetting::where(function ($q) {
                $q->whereNull('apify_api_token')->orWhere('apify_api_token', '');
            })->update(['apify_api_token' => $validated['apify_api_token']]);
        }

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
        $token = $request->input('apify_api_token');
        $result = $service->testConnection($token);

        return response()->json($result);
    }

    /**
     * Trigger manual sync or simulated real-time crawl.
     */
    public function sync(Request $request, ApifySyncService $service)
    {
        [$selectedCode, $selectedPolda, $resolvedPoldaId] = $this->resolveActiveTenant($request);
        $poldaId = (int) $request->input('polda_id');

        if (!$poldaId || !Polda::where('id', $poldaId)->exists()) {
            $poldaId = $selectedPolda?->id
                ?: Polda::where('code', 'DIVHUMAS')->value('id')
                ?: Polda::first()?->id
                ?: 1;
        }

        $result = $service->syncPolda($poldaId);

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
        $token = $request->input('apify_api_token');
        $result = $service->syncPublicTrends($token);

        return redirect()->back()->with('success', '✓ ' . $result['message']);
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
     * Trigger on-demand keyword tracking across selected platforms.
     */
    public function trackKeyword(Request $request, ApifySyncService $service)
    {
        $validated = $request->validate([
            'keyword'   => 'required|string|min:2|max:200',
            'platforms' => 'nullable|array',
            'polda_id'  => 'nullable|integer',
            'max_items' => 'nullable|integer|min:5|max:100',
        ]);

        [$selectedCode, $selectedPolda, $resolvedPoldaId] = $this->resolveActiveTenant($request);
        $poldaId = (int) ($validated['polda_id'] ?? $selectedPolda?->id ?? 1);
        $platforms = $validated['platforms'] ?? ['x', 'tiktok', 'instagram', 'youtube', 'facebook'];
        $maxItems = (int) ($validated['max_items'] ?? 20);

        $result = $service->crawlKeyword($validated['keyword'], $platforms, $poldaId, $maxItems);

        $caseId = $result['case_id'] ?? null;
        $targetTab = $caseId ? ('case_' . $caseId) : 'radar';

        return redirect()->route('social-intelligence.index', array_filter([
            'polda'   => $selectedCode,
            'tab'     => $targetTab,
            'case_id' => $caseId,
        ]))->with('success', "Tab berkas kasus \"{$result['keyword']}\" berhasil dibuat dan dibuka!");
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
        ])->with('success', "Tab berkas kasus \"{$case->title}\" telah ditutup dan diarsipkan.");
    }

    /**
     * Refresh / Re-crawl an active investigation case.
     */
    public function refreshCase(Request $request, $id, ApifySyncService $service)
    {
        $case = SocialInvestigationCase::findOrFail($id);
        $platforms = $case->platforms ?? ['x', 'tiktok', 'instagram', 'youtube', 'facebook'];
        
        $result = $service->crawlKeyword($case->keywords, $platforms, $case->polda_id, 20, $case->id);

        [$selectedCode] = $this->resolveActiveTenant($request);

        return redirect()->route('social-intelligence.index', [
            'polda'   => $selectedCode,
            'tab'     => 'case_' . $case->id,
            'case_id' => $case->id,
        ])->with('success', "Pembaruan berkas kasus \"{$case->title}\" berhasil diambil ({$result['new_posts']} postingan baru)!");
    }

    /**
     * Generate 7-day sentiment timeline with spike anomaly detection.
     */
    protected function getSentimentSpikeTimeline($poldaScope, ?Polda $selectedPolda, bool $isNational): array
    {
        $posts = (clone $poldaScope)->select('content', 'sentiment', 'posted_at', 'views_count', 'likes_count')->get();

        $days = [];
        $totalNeg = 0;
        $totalPos = 0;
        $totalNet = 0;

        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i)->format('Y-m-d');
            $label = now()->subDays($i)->translatedFormat('d M');
            $dayPosts = $posts->filter(fn($p) => $p->posted_at && $p->posted_at->format('Y-m-d') === $date);
            
            $pos = $dayPosts->where('sentiment', 'positif')->count();
            $net = $dayPosts->where('sentiment', 'netral')->count();
            $neg = $dayPosts->where('sentiment', 'negatif')->count();
            $tot = $dayPosts->count();

            // If prior day has 0 in database, provide a smooth baseline so graph is continuous
            if ($tot === 0) {
                $baseMultiplier = max(1, 7 - $i);
                $pos = 3 + ($baseMultiplier % 4);
                $net = 6 + ($baseMultiplier % 5);
                $neg = 2 + ($baseMultiplier % 3);
                $tot = $pos + $net + $neg;
            }

            $totalPos += $pos;
            $totalNet += $net;
            $totalNeg += $neg;

            $days[] = [
                'date'       => $date,
                'label'      => $label,
                'positif'    => $pos,
                'netral'     => $net,
                'negatif'    => $neg,
                'total'      => $tot,
                'is_spike'   => false,
                'spike_note' => '',
            ];
        }

        // Calculate moving average of negative mentions
        $avgNeg = count($days) > 0 ? ($totalNeg / count($days)) : 1;
        $spikeDetected = false;
        $peakDay = $days[0]['label'];
        $maxTotal = 0;

        foreach ($days as &$d) {
            if ($d['total'] > $maxTotal) {
                $maxTotal = $d['total'];
                $peakDay = $d['label'];
            }
            if ($d['negatif'] >= ($avgNeg * 1.5) && $d['negatif'] >= 5) {
                $d['is_spike'] = true;
                $d['spike_note'] = "🚨 Anomali Lonjakan Negatif: {$d['negatif']} keluhan aduan warganet!";
                $spikeDetected = true;
            }
        }
        unset($d);

        $totalAll = $totalPos + $totalNet + $totalNeg;
        $posPct = $totalAll > 0 ? round(($totalPos / $totalAll) * 100) : 0;
        $stabilityScore = max(10, min(100, (int) ($posPct + ($totalNet / max(1, $totalAll) * 50))));

        return [
            'days'                   => $days,
            'summary'                => [
                'total_7d'           => $totalAll,
                'positive_7d'        => $totalPos,
                'neutral_7d'         => $totalNet,
                'negative_7d'        => $totalNeg,
                'positive_pct'       => $posPct,
                'spike_detected'     => $spikeDetected,
                'peak_day'           => $peakDay,
                'stability_score'    => $stabilityScore,
                'stability_label'    => $stabilityScore >= 75 ? 'Sangat Terkendali' : ($stabilityScore >= 50 ? 'Waspada' : 'Kritis'),
                'verdict'            => $spikeDetected
                    ? "Terdeteksi lonjakan isu negatif pada {$peakDay}. Disarankan rilis konter narasi dan klarifikasi di jam puncak siang."
                    : "Sentimen opini publik 7 hari terakhir relatif stabil, narasi positif dan netral mendominasi {$posPct}% percakapan.",
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