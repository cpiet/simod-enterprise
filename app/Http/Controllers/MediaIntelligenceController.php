<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarning;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Issue;
use App\Models\PressRelease;
use App\Services\RssFeedService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MediaIntelligenceController extends Controller
{
    /**
     * Display the 6-Tab Media Intelligence Command Center.
     */
    public function index(Request $request): Response
    {
        $allPoldas = Polda::with(['polres' => function ($q) {
            $q->where('is_active', true)->orderBy('name');
        }])->where('is_active', true)->orderBy('name')->get();

        $selectedCode = $request->query('polda', 'SULBAR');
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = $allPoldas->firstWhere('code', $selectedCode);
            if (!$selectedPolda && $allPoldas->isNotEmpty()) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda->code;
            }
        }

        // Query Early Warnings scoped to active Polda
        $ewsQuery = EarlyWarning::query();
        if ($selectedPolda) {
            $ewsQuery->where('polda_id', $selectedPolda->id);
        }
        $earlyWarnings = $ewsQuery->orderByDesc('level')->get();

        // Query Media Feeds
        $feedsQuery = MediaFeed::where('is_active', true);
        if ($selectedPolda) {
            $feedsQuery->where('polda_id', $selectedPolda->id);
        }
        $mediaFeeds = $feedsQuery->get();

        // Fetch Real Live RSS Feeds from All Configured Portals
        $forceRefresh = $request->boolean('refresh', false);
        $rssService = new RssFeedService();
        $liveArticles = $rssService->fetchAggregatedFeeds($mediaFeeds->toArray(), $forceRefresh);

        $sampleArticles = !empty($liveArticles) ? array_slice($liveArticles, 0, 36) : [];

        // Query Issues scoped to active Polda
        $issuesQuery = Issue::with(['polres'])->latest();
        if ($selectedPolda) {
            $issuesQuery->where('polda_id', $selectedPolda->id);
        }
        $issues = $issuesQuery->limit(10)->get();

        // Query Press Releases scoped to active Polda
        $pressReleasesQuery = PressRelease::with(['polres'])->latest();
        if ($selectedPolda) {
            $pressReleasesQuery->where('polda_id', $selectedPolda->id);
        }
        $pressReleases = $pressReleasesQuery->limit(5)->get();

        // System metrics
        $metrics = [
            'total_poldas'   => $allPoldas->count(),
            'total_polres'   => Polres::where('is_active', true)->count(),
            'active_ews'     => $earlyWarnings->count(),
            'ews_red'        => $earlyWarnings->where('level', 'red')->count(),
            'ews_yellow'     => $earlyWarnings->where('level', 'yellow')->count(),
            'ews_green'      => $earlyWarnings->where('level', 'green')->count(),
            'total_issues'   => $issues->count(),
            'hoax_debunked'  => $issues->where('is_hoax', true)->count(),
            'server_wita'    => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return Inertia::render('MediaIntelligence/Index', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'earlyWarnings'  => $earlyWarnings,
            'mediaFeeds'     => $mediaFeeds,
            'sampleArticles' => $sampleArticles,
            'issues'         => $issues,
            'pressReleases'  => $pressReleases,
            'metrics'        => $metrics,
        ]);
    }
}
