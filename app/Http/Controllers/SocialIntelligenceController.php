<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Polda;
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
    public function index(Request $request): Response
    {
        $selectedCode = $request->query('polda', 'SULBAR');
        $platform = $request->query('platform', 'all');
        $sentiment = $request->query('sentiment', 'all');
        $kolOnly = $request->boolean('kol', false);
        $sort = $request->query('sort', 'latest');
        $search = $request->query('q', '');
        $activeTab = $request->query('tab', 'radar');
        $satwil = $request->query('satwil', 'all');

        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;
        $isNational = ($selectedCode === 'ALL');

        if (!$isNational) {
            $selectedPolda = Polda::with(['polres'])->where('code', $selectedCode)->first();
            if (!$selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

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

        // Distinct Key Opinion Leaders list (National vs Polda)
        if ($isNational) {
            $dbKols = SocialPost::where('is_kol', true)
                ->select('author_name', 'author_handle', 'platform', 'kol_category')
                ->distinct()
                ->get()
                ->toArray();

            $nationalKols = [
                ['author_name' => 'Kompolnas RI Official', 'author_handle' => '@kompolnas_ri', 'platform' => 'x', 'kol_category' => 'Pengawas Eksternal Kepolisian'],
                ['author_name' => 'Indonesia Police Watch (IPW)', 'author_handle' => '@ipw_watch', 'platform' => 'x', 'kol_category' => 'LSM Pemantau Kepolisian'],
                ['author_name' => 'Dewan Pers Indonesia', 'author_handle' => '@dewanpers_ri', 'platform' => 'instagram', 'kol_category' => 'Lembaga Etika & Media'],
                ['author_name' => 'KontraS Nasional', 'author_handle' => '@kontras_update', 'platform' => 'x', 'kol_category' => 'Lembaga Advokasi HAM'],
                ['author_name' => 'Najwa Shihab / Narasi Newsroom', 'author_handle' => '@narasinewsroom', 'platform' => 'youtube', 'kol_category' => 'Media & Jurnalis Investigasi'],
                ['author_name' => 'ISESS (Institute for Security Studies)', 'author_handle' => '@isess_official', 'platform' => 'tiktok', 'kol_category' => 'Pengamat Keamanan Nasional'],
            ];

            $kolProfiles = collect(array_merge($nationalKols, $dbKols))->unique('author_handle')->values()->all();
        } else {
            $kolProfiles = SocialPost::where('polda_id', $selectedPolda->id)
                ->where('is_kol', true)
                ->select('author_name', 'author_handle', 'platform', 'kol_category')
                ->distinct()
                ->get();
        }

        // Trending Hashtags & Issues (National vs Regional Sulbar)
        if ($isNational) {
            $trendingHashtags = [
                ['tag' => '#PolriPresisi', 'count' => 148500, 'sentiment' => 'positif', 'platform' => 'x'],
                ['tag' => '#DivhumasPolri', 'count' => 92400, 'sentiment' => 'positif', 'platform' => 'instagram'],
                ['tag' => '#HumasPolri', 'count' => 78100, 'sentiment' => 'positif', 'platform' => 'tiktok'],
                ['tag' => '#Kapolri', 'count' => 65300, 'sentiment' => 'netral', 'platform' => 'x'],
                ['tag' => '#PilkadaSerentak2026', 'count' => 54800, 'sentiment' => 'netral', 'platform' => 'tiktok'],
                ['tag' => '#SiberPolri', 'count' => 41200, 'sentiment' => 'positif', 'platform' => 'youtube'],
                ['tag' => '#KamtibmasKondusif', 'count' => 36900, 'sentiment' => 'positif', 'platform' => 'instagram'],
                ['tag' => '#HaloPolisi', 'count' => 28500, 'sentiment' => 'positif', 'platform' => 'x'],
                ['tag' => '#StopHoaks', 'count' => 24100, 'sentiment' => 'positif', 'platform' => 'tiktok'],
                ['tag' => '#OperasiZebra2026', 'count' => 19800, 'sentiment' => 'netral', 'platform' => 'instagram'],
            ];
        } else {
            $trendingHashtags = [
                ['tag' => '#PolriPresisi', 'count' => 12450, 'sentiment' => 'positif', 'platform' => 'x'],
                ['tag' => '#PoldaSulbar', 'count' => 8920, 'sentiment' => 'positif', 'platform' => 'instagram'],
                ['tag' => '#AntreanSolarMamuju', 'count' => 7410, 'sentiment' => 'negatif', 'platform' => 'x'],
                ['tag' => '#PolrestaMamuju', 'count' => 5230, 'sentiment' => 'positif', 'platform' => 'tiktok'],
                ['tag' => '#LongsorSombaMajene', 'count' => 4600, 'sentiment' => 'netral', 'platform' => 'tiktok'],
                ['tag' => '#PilkadaSulbarDamai', 'count' => 3890, 'sentiment' => 'positif', 'platform' => 'instagram'],
                ['tag' => '#TertibKnalpotBrong', 'count' => 3120, 'sentiment' => 'positif', 'platform' => 'x'],
                ['tag' => '#SengketaLahanPasangkayu', 'count' => 2450, 'sentiment' => 'netral', 'platform' => 'x'],
            ];
        }

        // Settings (Divhumas National or Regional Polda)
        if ($isNational) {
            $poldaIdForSetting = $allPoldas->first()?->id ?? 5;
            $savedSetting = SocialSetting::where('polda_id', $poldaIdForSetting)->first();
            $settings = $savedSetting ? clone $savedSetting : new SocialSetting();
            $settings->polda_id = $poldaIdForSetting;
            $settings->keywords_tracked = $settings->keywords_tracked ?: 'Divhumas Polri, Kapolri, Mabes Polri, Humas Polri, Bareskrim, Korlantas Polri, Propam Polri, Polisi Presisi, Dumas Presisi';
            $settings->accounts_tracked = $settings->accounts_tracked ?: '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi, @ccicpolri, @bareskrim_polri, @korlantaspolri.ntmc';
        } else {
            $poldaIdForSetting = $selectedPolda?->id ?? 5;
            $settings = SocialSetting::firstOrCreate(
                ['polda_id' => $poldaIdForSetting],
                [
                    'keywords_tracked'  => 'Polda Sulbar, Polresta Mamuju, Polisi Sulbar, Polres Majene, Polres Polman',
                    'accounts_tracked'  => '@poldasulbar, @polresta_mamuju_official, @tribratanews_sulbar, @sulbar_update',
                    'sync_frequency'    => 'every_3_hours',
                    'auto_sync_enabled' => true,
                    'last_synced_at'    => now()->subMinutes(15),
                ]
            );
        }

        return Inertia::render('SocialIntelligence/Index', [
            'allPoldas'         => $allPoldas,
            'selectedPolda'     => $selectedPolda,
            'selectedCode'      => $selectedCode,
            'isNational'        => $isNational,
            'posts'             => $posts,
            'metrics'           => $metrics,
            'kolProfiles'       => $kolProfiles,
            'trendingHashtags'  => $trendingHashtags,
            'settings'          => $settings,
            'filters'           => [
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
        $poldaId = $request->input('polda_id');
        if (!$poldaId || !Polda::where('id', $poldaId)->exists()) {
            $sulbar = Polda::where('code', 'SULBAR')->first();
            $poldaId = $sulbar ? $sulbar->id : Polda::first()?->id ?? 5;
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
        $poldaId = (int) $request->input('polda_id');
        if (!$poldaId || !Polda::where('id', $poldaId)->exists()) {
            $sulbar = Polda::where('code', 'SULBAR')->first();
            $poldaId = $sulbar ? $sulbar->id : Polda::first()?->id ?? 5;
        }

        $result = $service->syncPolda($poldaId);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', '✓ ' . $result['message']);
    }
}

