<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Services\ApifySyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
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

        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::with(['polres'])->where('code', $selectedCode)->first();
            if (!$selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        // Base query
        $query = SocialPost::with(['polres', 'polda']);

        if ($selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
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

        // High-level Metrics for the active Polda
        $poldaScope = SocialPost::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id));
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

        // Distinct Key Opinion Leaders list
        $kolProfiles = SocialPost::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->where('is_kol', true)
            ->select('author_name', 'author_handle', 'platform', 'kol_category')
            ->distinct()
            ->get();

        // Trending Hashtags & Issues (curated and dynamic)
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

        // Settings
        $settings = SocialSetting::firstOrCreate(
            ['polda_id' => $selectedPolda?->id ?? 1],
            [
                'keywords_tracked'  => 'Polda Sulbar, Polresta Mamuju, Polisi Sulbar, Polres Majene, Polres Polman',
                'sync_frequency'    => 'every_3_hours',
                'auto_sync_enabled' => true,
                'last_synced_at'    => now()->subMinutes(15),
            ]
        );

        return Inertia::render('SocialIntelligence/Index', [
            'allPoldas'         => $allPoldas,
            'selectedPolda'     => $selectedPolda,
            'selectedCode'      => $selectedCode,
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
        $validated = $request->validate([
            'polda_id'          => 'required|exists:poldas,id',
            'apify_api_token'   => 'nullable|string|max:255',
            'keywords_tracked'  => 'nullable|string|max:1000',
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
            'subject_id'   => $validated['polda_id'],
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
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
        $poldaId = (int) $request->input('polda_id', 1);
        $result = $service->syncPolda($poldaId);

        if (!$result['success']) {
            return redirect()->back()->with('error', $result['message']);
        }

        return redirect()->back()->with('success', '✓ ' . $result['message']);
    }
}

