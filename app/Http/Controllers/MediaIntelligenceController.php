<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\EarlyWarning;
use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\MediaIntelligenceReport;
use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\PressRelease;
use App\Services\GeminiService;
use App\Services\RssFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

        if ($selectedCode === 'ALL' || $selectedCode === 'DIVHUMAS') {
            $selectedPolda = $allPoldas->firstWhere('code', 'DIVHUMAS') ?? Polda::where('code', 'DIVHUMAS')->first();
            $selectedCode = 'ALL';
        } else {
            $selectedPolda = $allPoldas->firstWhere('code', $selectedCode);
            if (!$selectedPolda && $allPoldas->isNotEmpty()) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda->code;
            }
        }

        if (!$selectedPolda) {
            $selectedPolda = $allPoldas->first() ?? Polda::first();
        }

        // Active Polda ID fallback
        $poldaId = $selectedPolda ? $selectedPolda->id : ($allPoldas->first()?->id ?? 1);

        // Media Feeds for this Polda
        $mediaFeeds = MediaFeed::where('polda_id', $poldaId)->orderBy('name')->get();

        // If user requests live sync/refresh, scrape now
        $forceRefresh = $request->boolean('refresh', false);
        $rssService = new RssFeedService();

        if ($forceRefresh && $mediaFeeds->isNotEmpty()) {
            $liveArticles = $rssService->fetchAggregatedFeeds($mediaFeeds->toArray(), true);

            // Group by source to ensure fair quota per feed (up to 25 articles per feed)
            // This prevents high-volume national portals from crowding out regional feeds
            $bySource = [];
            foreach ($liveArticles as $item) {
                $srcKey = strtoupper($item['source_name'] ?? 'MEDIA ONLINE');
                $bySource[$srcKey][] = $item;
            }

            foreach ($bySource as $srcKey => $items) {
                foreach (array_slice($items, 0, 25) as $item) {
                    MediaArticle::updateOrCreate(
                        ['url' => $item['link']],
                        [
                            'polda_id'    => $poldaId,
                            'feed_id'     => $item['source_id'] ?: null,
                            'title'       => $item['title'],
                            'source_name' => strtoupper($item['source_name']),
                            'category'    => $item['category'],
                            'image_url'   => $item['image_url'] ?: null,
                            'summary'     => $item['summary'],
                            'pub_date'    => !empty($item['pub_date']) ? date('Y-m-d H:i:s', strtotime($item['pub_date'])) : now(),
                            'sentiment'   => $item['sentiment'] ?? 'netral',
                        ]
                    );
                }
            }
        }

        // Load active Media Topics (Smart Dossier Atensi Pimpinan)
        $dbTopics = MediaTopic::where('polda_id', $poldaId)
            ->where('status', 'active')
            ->latest()
            ->get();

        // Load articles from database (up to 1,500 latest articles)
        $dbArticles = MediaArticle::where('polda_id', $poldaId)
            ->latest('pub_date')
            ->latest('id')
            ->limit(1500)
            ->get();

        $articleIds = $dbArticles->pluck('id')->toArray();
        $manualArticleTopics = DB::table('media_article_topic')
            ->whereIn('media_article_id', $articleIds)
            ->get()
            ->groupBy('media_article_id');

        $topicsMap = [];
        foreach ($dbTopics as $t) {
            $topicsMap[$t->id] = [
                'id'             => $t->id,
                'title'          => $t->title,
                'keywords'       => $t->keywords,
                'keywords_array' => $t->getKeywordsArray(),
                'description'    => $t->description,
                'priority'       => $t->priority,
                'status'         => $t->status,
                'created_at'     => $t->created_at?->format('d M Y, H:i'),
                'articles_count' => 0,
            ];
        }

        // Format articles for frontend with topic matching
        $articles = $dbArticles->map(function ($a) use (&$topicsMap, $manualArticleTopics) {
            $manualTopicIds = $manualArticleTopics->get($a->id, collect())
                ->pluck('media_topic_id')
                ->map(fn($id) => (int)$id)
                ->toArray();

            $matchedTopicIds = [];
            $matchedTopics = [];
            $textToSearch = mb_strtolower($a->title . ' ' . $a->summary);

            foreach ($topicsMap as $tId => &$tData) {
                $isMatch = false;
                if (in_array($tId, $manualTopicIds, true)) {
                    $isMatch = true;
                } else {
                    foreach ($tData['keywords_array'] as $kw) {
                        if ($kw !== '' && str_contains($textToSearch, $kw)) {
                            $isMatch = true;
                            break;
                        }
                    }
                }

                if ($isMatch) {
                    $matchedTopicIds[] = $tId;
                    $matchedTopics[] = [
                        'id'       => $tId,
                        'title'    => $tData['title'],
                        'priority' => $tData['priority'],
                    ];
                    $tData['articles_count']++;
                }
            }
            unset($tData);

            return [
                'id'                => (string) $a->id,
                'title'             => $a->title,
                'source_name'       => strtoupper($a->source_name),
                'category'          => $a->category,
                'category_badge'    => strtoupper($a->category),
                'time_ago'          => $a->created_at ? $a->created_at->diffForHumans() : 'Baru saja',
                'pub_date'          => $a->pub_date ? $a->pub_date->format('d M Y, H.i') . ' WITA' : now()->format('d M Y, H.i') . ' WITA',
                'summary'           => $a->summary ?: 'Cuplikan berita pantauan intelijen media belum tersedia.',
                'image_url'         => $a->image_url,
                'link'              => $a->url,
                'sentiment'         => $a->sentiment ?: 'netral',
                'is_bookmarked'     => (bool) $a->is_bookmarked,
                'matched_topic_ids' => $matchedTopicIds,
                'matched_topics'    => $matchedTopics,
                'manual_topic_ids'  => $manualTopicIds,
            ];
        })->toArray();

        // Portal Summary with counts directly from database for registered feeds
        $dbPortalCounts = MediaArticle::where('polda_id', $poldaId)
            ->selectRaw('UPPER(source_name) as src_name, COUNT(*) as cnt')
            ->groupBy('src_name')
            ->pluck('cnt', 'src_name')
            ->toArray();

        // Strictly ensure ONLY the registered active feeds for this Polda are shown in filter
        $mergedPortalCounts = [];
        foreach ($mediaFeeds as $feed) {
            $feedUpper = strtoupper($feed->name);
            $mergedPortalCounts[$feedUpper] = $dbPortalCounts[$feedUpper] ?? 0;
        }
        ksort($mergedPortalCounts);

        $portalsList = [
            ['name' => 'Semua Portal', 'count' => count($articles)],
        ];
        foreach ($mergedPortalCounts as $name => $count) {
            $portalsList[] = ['name' => $name, 'count' => $count];
        }

        // Category Summary with counts
        $categoryGroup = [];
        foreach ($articles as $art) {
            $cat = $art['category'];
            $categoryGroup[$cat] = ($categoryGroup[$cat] ?? 0) + 1;
        }

        $categoriesList = [
            ['name' => 'Semua Kategori', 'count' => count($articles)],
        ];
        foreach ($categoryGroup as $cat => $count) {
            $categoriesList[] = ['name' => $cat, 'count' => $count];
        }

        // Query Early Warnings scoped to active Polda
        $earlyWarnings = EarlyWarning::where('polda_id', $poldaId)->orderByDesc('level')->get();

        // Query Issues scoped to active Polda
        $issues = Issue::with(['polres'])->where('polda_id', $poldaId)->latest()->limit(10)->get();

        // Query Press Releases
        $pressReleases = PressRelease::with(['polres'])->where('polda_id', $poldaId)->latest()->limit(5)->get();

        // Fetch or Seed Latest AI Intelligence Report
        $report = MediaIntelligenceReport::where('polda_id', $poldaId)->latest()->first();
        if (!$report) {
            $report = $this->seedDefaultReport($poldaId, $selectedPolda);
        }

        // Dynamic AI Prompt Template (Tenant-Aware)
        $aiPromptTemplate = $this->generateDynamicPrompt($selectedPolda);

        // Gemini AI Settings & Key Status
        $geminiService = new GeminiService();
        $rawApiKey = $geminiService->getApiKey($selectedPolda->id);
        $aiSettingModel = AiSetting::where('polda_id', $selectedPolda->id)->first();
        $aiSetting = [
            'has_api_key'     => !empty($rawApiKey),
            'masked_api_key'  => $geminiService->maskApiKey($rawApiKey),
            'gemini_model'    => $aiSettingModel?->gemini_model ?? $geminiService->getModel($selectedPolda->id),
            'is_auto_analyze' => (bool) ($aiSettingModel?->is_auto_analyze ?? false),
            'last_analyzed_at'=> $aiSettingModel?->last_analyzed_at ? $aiSettingModel->last_analyzed_at->diffForHumans() : null,
            'last_status'     => $aiSettingModel?->last_status,
        ];

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
            'allPoldas'        => $allPoldas,
            'selectedPolda'    => $selectedPolda,
            'selectedCode'     => $selectedCode,
            'earlyWarnings'    => $earlyWarnings,
            'mediaFeeds'       => $mediaFeeds,
            'sampleArticles'   => $articles,
            'topics'           => array_values($topicsMap),
            'portalsList'      => $portalsList,
            'categoriesList'   => $categoriesList,
            'report'           => $report,
            'aiPromptTemplate' => $aiPromptTemplate,
            'aiSetting'        => $aiSetting,
            'issues'           => $issues,
            'pressReleases'    => $pressReleases,
            'metrics'          => $metrics,
        ]);
    }

    /**
     * Tempel cepat hasil analisis dari ChatGPT / AI eksternal.
     */
    public function storeAiBriefing(Request $request): RedirectResponse
    {
        $rawInput = (string) $request->input('raw_input', '');
        $poldaCode = $request->query('polda', 'SULBAR');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $polda = Polda::where('code', $searchCode)->first() ?? Polda::first();

        if (empty(trim($rawInput))) {
            return back()->with('error', 'Teks salinan hasil analisis ChatGPT tidak boleh kosong.');
        }

        // Coba decode JSON langsung atau bersihkan ```json
        $parsed = json_decode($rawInput, true);
        if (!$parsed && preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawInput, $matches)) {
            $parsed = json_decode($matches[1], true);
        }

        if (!$parsed) {
            $isNat = ($polda->code === 'DIVHUMAS');
            $tz = ($isNat || $polda->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';
            // Text based fallback
            $parsed = [
                'title'           => 'LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK ' . ($isNat ? 'NASIONAL - DIVISI HUMAS POLRI' : ($polda->name ?? 'POLDA')),
                'report_date'     => now()->format('Y-m-d'),
                'report_time'     => now()->format('H.i') . ' ' . $tz,
                'executive_brief' => mb_substr($rawInput, 0, 500),
                'top_concern'     => 'Hasil analisis mandiri intelijen media dari ChatGPT.',
                'sentiment_national' => ['positif' => 65, 'netral' => 25, 'negatif' => 10],
                'sentiment_satwil'   => ['positif' => 60, 'netral' => 30, 'negatif' => 10],
            ];
        }

        // Calculate sentiments safely
        $calcSentiment = function ($s, array $issues = []) {
            $pos = isset($s['positif']) ? (int) $s['positif'] : (isset($s['positive']) ? (int) $s['positive'] : null);
            $net = isset($s['netral']) ? (int) $s['netral'] : (isset($s['neutral']) ? (int) $s['neutral'] : null);
            $neg = isset($s['negatif']) ? (int) $s['negatif'] : (isset($s['negative']) ? (int) $s['negative'] : null);

            if (($pos === null || $pos === 0) && ($net === null || $net === 0) && ($neg === null || $neg === 0) && !empty($issues)) {
                $total = count($issues);
                $pCount = 0; $nCount = 0; $ngCount = 0;
                foreach ($issues as $iss) {
                    $sent = strtolower($iss['sentiment'] ?? '');
                    if (str_contains($sent, 'positif')) $pCount++;
                    elseif (str_contains($sent, 'negatif')) $ngCount++;
                    else $nCount++;
                }
                $pos = (int) round(($pCount / $total) * 100);
                $neg = (int) round(($ngCount / $total) * 100);
                $net = max(0, 100 - ($pos + $neg));
            }

            return [
                'positif' => $pos !== null ? $pos : 60,
                'netral'  => $net !== null ? $net : 30,
                'negatif' => $neg !== null ? $neg : 10,
            ];
        };

        $natIssues = $parsed['national_issues'] ?? [];
        $sulIssues = $parsed['regional_issues'] ?? $parsed['satwil_issues'] ?? $parsed['sulbar_issues'] ?? [];

        $isNational = ($polda->code === 'DIVHUMAS');
        $tz = ($isNational || $polda->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';

        $snapshotSatwilStatus = $parsed['snapshot_satwil_status'] ?? $parsed['snapshot_regional_status'] ?? $parsed['snapshot_sulbar_status'] ?? 'HIJAU - KONDUSIF';
        $snapshotSatwilDesc = $parsed['snapshot_satwil_desc'] ?? $parsed['snapshot_regional_desc'] ?? $parsed['snapshot_sulbar_desc']
            ?? ($isNational ? 'Dinamika kamtibmas dan sentimen opini publik di 34 Polda jajaran terpantau aman dan kondusif.' : 'Situasi kamtibmas kewilayahan terpantau aman dan terkendali.');

        $sentimentSatwilRaw = $parsed['sentiment_satwil'] ?? $parsed['sentiment_regional'] ?? $parsed['sentiment_sulbar'] ?? [];

        MediaIntelligenceReport::create([
            'polda_id'                 => $polda->id,
            'report_date'              => $parsed['report_date'] ?? now()->format('Y-m-d'),
            'report_time'              => $parsed['report_time'] ?? (now()->format('H.i') . ' ' . $tz),
            'title'                    => $parsed['title'] ?? ('LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK ' . ($isNational ? 'NASIONAL - DIVISI HUMAS POLRI' : $polda->name)),
            'snapshot_national_status' => $parsed['snapshot_national_status'] ?? 'HIJAU - KONDUSIF',
            'snapshot_national_desc'   => $parsed['snapshot_national_desc'] ?? 'Dinamika isu nasional Polri stabil dengan pengawasan intensif ruang siber dan keterbukaan informasi.',
            'snapshot_sulbar_status'   => $snapshotSatwilStatus,
            'snapshot_sulbar_desc'     => $snapshotSatwilDesc,
            'top_concern'              => $parsed['top_concern'] ?? 'Perhatian khusus pimpinan terhadap situasi Harkamtibmas terkini.',
            'executive_brief'          => $parsed['executive_brief'] ?? $rawInput,
            'core_decision'            => $parsed['core_decision'] ?? ($isNational ? 'Instruksikan amplifikasi rilis satu pintu dan orkestrasi 34 Polda jajaran.' : 'Tingkatkan patroli gabungan dan klarifikasi satu pintu oleh Kabid Humas.'),
            'sentiment_national'       => $calcSentiment($parsed['sentiment_national'] ?? [], $natIssues),
            'sentiment_sulbar'         => $calcSentiment($sentimentSatwilRaw, $sulIssues),
            'priority_responses'       => $parsed['priority_responses'] ?? [
                'response_0_2h' => ['title' => 'Validasi Fakta', 'action' => 'Koordinasi satu pintu dengan Satker/Polda terkait.'],
                'response_2_6h' => ['title' => 'Konsolidasi & Klarifikasi', 'action' => 'Siapkan rilis pers dan materi counter narasi terpadu.'],
                'response_24h'  => ['title' => 'Amplifikasi Citra Positif', 'action' => 'Masifikasi publikasi humanis dan keterbukaan informasi Polri Presisi.'],
            ],
            'national_issues'          => $natIssues,
            'sulbar_issues'            => $sulIssues,
            'early_warnings'           => $parsed['early_warnings'] ?? [],
            'strategic_contents'       => $parsed['strategic_contents'] ?? [],
            'agenda_setting'           => $parsed['agenda_setting'] ?? [],
            'recommendations'          => $parsed['recommendations'] ?? [],
            'raw_input'                => $rawInput,
        ]);

        return back()->with('success', 'Laporan Intelijen AI berhasil diurai dan tersaji secara terpadu di semua tab!');
    }

    /**
     * Tambah Sumber RSS Baru
     */
    public function storeRssSource(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'polda_id' => 'required|exists:poldas,id',
            'name'     => 'required|string|max:100',
            'url'      => 'required|url|max:255',
            'category' => 'nullable|string|max:50',
        ]);

        MediaFeed::updateOrCreate(
            ['polda_id' => $validated['polda_id'], 'url' => $validated['url']],
            [
                'name'      => $validated['name'],
                'category'  => $validated['category'] ?? 'Regional Sulselbar',
                'is_active' => true,
            ]
        );

        return back()->with('success', "Sumber RSS [{$validated['name']}] berhasil ditambahkan!");
    }

    /**
     * Toggle status aktif/nonaktif Sumber RSS
     */
    public function toggleRssSource($id): RedirectResponse
    {
        $feed = MediaFeed::findOrFail($id);
        $feed->is_active = !$feed->is_active;
        $feed->save();

        $status = $feed->is_active ? 'diaktifkan' : 'dinonaktifkan';
        return back()->with('success', "Sumber RSS [{$feed->name}] berhasil {$status}.");
    }

    /**
     * Hapus Sumber RSS
     */
    public function deleteRssSource($id): RedirectResponse
    {
        $feed = MediaFeed::findOrFail($id);
        $feed->delete();

        return back()->with('success', "Sumber RSS [{$feed->name}] berhasil dihapus.");
    }

    /**
     * Uji kelayakan URL feed RSS secara realtime
     */
    public function testRssSource(Request $request): JsonResponse
    {
        $url = $request->input('url');
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return response()->json(['valid' => false, 'message' => 'Format URL tidak valid.'], 422);
        }

        $start = microtime(true);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 6);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SIMOD-Enterprise-Validator/2.0');
        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latency = round((microtime(true) - $start) * 1000);

        if ($httpCode >= 200 && $httpCode < 400 && !empty($body)) {
            $isXml = str_contains($body, '<rss') || str_contains($body, '<feed') || str_contains($body, '<channel');
            preg_match_all('/<(item|entry)[\s>]/i', $body, $matches);
            $itemsCount = count($matches[0] ?? []);

            return response()->json([
                'valid'       => $isXml,
                'status'      => $httpCode,
                'items_count' => $itemsCount,
                'latency_ms'  => $latency,
                'message'     => $isXml ? "Feed XML Valid ({$itemsCount} berita terdeteksi, {$latency}ms)" : "URL dapat diakses namun format XML/RSS tidak terdeteksi",
            ]);
        }

        return response()->json([
            'valid'      => false,
            'status'     => $httpCode,
            'latency_ms' => $latency,
            'message'    => "Gagal menghubungi URL feed (HTTP {$httpCode}, {$latency}ms)",
        ], 400);
    }

    /**
     * Jalankan analisis intelijen media otomatis menggunakan Google Gemini Pro.
     */
    public function analyzeGemini(Request $request): RedirectResponse|JsonResponse
    {
        $poldaCode = $request->query('polda', 'SULBAR');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $polda = Polda::where('code', $searchCode)->first() ?? Polda::first();

        $geminiService = new GeminiService();
        $apiKey = $geminiService->getApiKey($polda->id);

        if (empty($apiKey)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status'  => 'missing_api_key',
                    'message' => 'Google Gemini API Key belum diatur. Silakan masukkan API Key Anda pada pengaturan Gemini.',
                ], 422);
            }
            return back()->with('error', 'Google Gemini API Key belum diatur. Silakan masukkan API Key Anda.');
        }

        // Fetch latest articles for this Polda (up to 30)
        $articles = MediaArticle::where('polda_id', $polda->id)
            ->latest('pub_date')
            ->latest('id')
            ->limit(30)
            ->get()
            ->map(function ($a) {
                return [
                    'title'       => $a->title,
                    'source_name' => $a->source_name,
                    'category'    => $a->category,
                    'pub_date'    => $a->pub_date ? $a->pub_date->format('d M Y, H.i') : '',
                    'time_ago'    => $a->created_at ? $a->created_at->diffForHumans() : '',
                    'summary'     => $a->summary,
                    'link'        => $a->url,
                ];
            })
            ->toArray();

        $promptTemplate = $this->generateDynamicPrompt($polda);
        $model = $geminiService->getModel($polda->id);

        $result = $geminiService->analyzeMediaArticles($polda, $articles, $promptTemplate, $apiKey, $model);

        $aiSetting = AiSetting::forPolda($polda->id);

        if (!$result['success']) {
            $aiSetting->update([
                'last_status' => 'error',
                'last_error'  => $result['message'] ?? 'Gagal memproses analisis Gemini Pro.',
            ]);

            if ($request->wantsJson()) {
                return response()->json($result, 500);
            }
            return back()->with('error', $result['message']);
        }

        $parsed = $result['data'];
        $rawText = $result['raw_text'] ?? json_encode($parsed);

        // Process sentiments & map keys
        $calcSentiment = function ($s, array $issues = []) {
            $pos = isset($s['positif']) ? (int) $s['positif'] : (isset($s['positive']) ? (int) $s['positive'] : null);
            $net = isset($s['netral']) ? (int) $s['netral'] : (isset($s['neutral']) ? (int) $s['neutral'] : null);
            $neg = isset($s['negatif']) ? (int) $s['negatif'] : (isset($s['negative']) ? (int) $s['negative'] : null);

            if (($pos === null || $pos === 0) && ($net === null || $net === 0) && ($neg === null || $neg === 0) && !empty($issues)) {
                $total = count($issues);
                $pCount = 0; $nCount = 0; $ngCount = 0;
                foreach ($issues as $iss) {
                    $sent = strtolower($iss['sentiment'] ?? '');
                    if (str_contains($sent, 'positif')) $pCount++;
                    elseif (str_contains($sent, 'negatif')) $ngCount++;
                    else $nCount++;
                }
                $pos = (int) round(($pCount / $total) * 100);
                $neg = (int) round(($ngCount / $total) * 100);
                $net = max(0, 100 - ($pos + $neg));
            }

            return [
                'positif' => $pos !== null ? $pos : 65,
                'netral'  => $net !== null ? $net : 25,
                'negatif' => $neg !== null ? $neg : 10,
            ];
        };

        $natIssues = $parsed['national_issues'] ?? [];
        $sulIssues = $parsed['regional_issues'] ?? $parsed['satwil_issues'] ?? $parsed['sulbar_issues'] ?? [];

        $isNational = ($polda->code === 'DIVHUMAS');
        $tz = ($isNational || $polda->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';

        $snapshotSatwilStatus = $parsed['snapshot_satwil_status'] ?? $parsed['snapshot_regional_status'] ?? $parsed['snapshot_sulbar_status'] ?? 'HIJAU - KONDUSIF';
        $snapshotSatwilDesc = $parsed['snapshot_satwil_desc'] ?? $parsed['snapshot_regional_desc'] ?? $parsed['snapshot_sulbar_desc']
            ?? ($isNational ? 'Dinamika kamtibmas dan sentimen opini publik di 34 Polda jajaran terpantau aman dan kondusif.' : 'Situasi kamtibmas kewilayahan terpantau aman dan terkendali.');

        $sentimentSatwilRaw = $parsed['sentiment_satwil'] ?? $parsed['sentiment_regional'] ?? $parsed['sentiment_sulbar'] ?? [];

        $report = MediaIntelligenceReport::create([
            'polda_id'                 => $polda->id,
            'report_date'              => $parsed['report_date'] ?? now()->format('Y-m-d'),
            'report_time'              => $parsed['report_time'] ?? (now()->format('H.i') . ' ' . $tz),
            'title'                    => $parsed['title'] ?? ('LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK ' . ($isNational ? 'NASIONAL - DIVISI HUMAS POLRI' : $polda->name)),
            'snapshot_national_status' => $parsed['snapshot_national_status'] ?? 'HIJAU - KONDUSIF',
            'snapshot_national_desc'   => $parsed['snapshot_national_desc'] ?? 'Dinamika isu nasional Polri stabil dengan pengawasan intensif ruang siber dan keterbukaan informasi.',
            'snapshot_sulbar_status'   => $snapshotSatwilStatus,
            'snapshot_sulbar_desc'     => $snapshotSatwilDesc,
            'top_concern'              => $parsed['top_concern'] ?? 'Perhatian khusus pimpinan terhadap situasi Harkamtibmas terkini.',
            'executive_brief'          => $parsed['executive_brief'] ?? 'Situasi kamtibmas terpantau kondusif.',
            'core_decision'            => $parsed['core_decision'] ?? ($isNational ? 'Instruksikan amplifikasi rilis satu pintu dan orkestrasi 34 Polda jajaran.' : 'Tingkatkan patroli gabungan dan klarifikasi satu pintu oleh Kabid Humas.'),
            'sentiment_national'       => $calcSentiment($parsed['sentiment_national'] ?? [], $natIssues),
            'sentiment_sulbar'         => $calcSentiment($sentimentSatwilRaw, $sulIssues),
            'priority_responses'       => $parsed['priority_responses'] ?? [
                'response_0_2h' => ['title' => 'Validasi Fakta', 'action' => 'Koordinasi satu pintu dengan Satker/Polda terkait.'],
                'response_2_6h' => ['title' => 'Konsolidasi & Klarifikasi', 'action' => 'Siapkan rilis pers dan materi counter narasi terpadu.'],
                'response_24h'  => ['title' => 'Amplifikasi Citra Positif', 'action' => 'Masifikasi publikasi humanis dan keterbukaan informasi Polri Presisi.'],
            ],
            'national_issues'          => $natIssues,
            'sulbar_issues'            => $sulIssues,
            'early_warnings'           => $parsed['early_warnings'] ?? [],
            'strategic_contents'       => $parsed['strategic_contents'] ?? [],
            'agenda_setting'           => $parsed['agenda_setting'] ?? [],
            'recommendations'          => $parsed['recommendations'] ?? [],
            'raw_input'                => $rawText,
        ]);

        $aiSetting->update([
            'last_analyzed_at' => now(),
            'last_status'      => 'success',
            'last_error'       => null,
        ]);

        $modelUsed = $result['model_used'] ?? $model;

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => "Analisis otomatis Google Gemini ({$modelUsed}) berhasil diperbarui!",
                'report'  => $report,
                'model'   => $modelUsed,
            ]);
        }

        return back()->with('success', "Analisis intelijen otomatis Gemini Pro ({$modelUsed}) berhasil diperbarui!");
    }

    /**
     * Simpan / Perbarui Pengaturan Google Gemini API.
     */
    public function updateGeminiSettings(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'gemini_api_key'  => 'nullable|string',
            'gemini_model'    => 'required|string|in:gemini-3.5-flash-lite,gemini-flash-lite-latest,gemini-3.8-flash,gemini-3.1-pro-preview,gemini-2.5-pro,gemini-1.5-pro,gemini-2.5-flash,gemini-1.5-flash',
            'is_auto_analyze' => 'nullable|boolean',
        ]);

        $poldaCode = $request->query('polda', 'SULBAR');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $polda = Polda::where('code', $searchCode)->first() ?? Polda::first();

        $aiSetting = AiSetting::forPolda($polda->id);

        $updateData = [
            'gemini_model'    => $validated['gemini_model'],
            'is_auto_analyze' => (bool) ($validated['is_auto_analyze'] ?? false),
        ];

        if (array_key_exists('gemini_api_key', $validated) && !empty(trim((string) $validated['gemini_api_key']))) {
            $updateData['gemini_api_key'] = trim($validated['gemini_api_key']);
        }

        $aiSetting->update($updateData);

        if ($request->boolean('run_analysis_now')) {
            return $this->analyzeGemini($request);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Pengaturan Google Gemini Pro berhasil disimpan.',
            ]);
        }

        return back()->with('success', 'Pengaturan Google Gemini Pro berhasil disimpan.');
    }

    /**
     * Uji koneksi ke Google Gemini API.
     */
    public function testGeminiConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');
        $model = $request->input('model', 'gemini-2.5-pro');

        $poldaCode = $request->query('polda', 'SULBAR');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $polda = Polda::where('code', $searchCode)->first() ?? Polda::first();

        $geminiService = new GeminiService();
        $apiKey = $apiKey ?: $geminiService->getApiKey($polda->id);

        $result = $geminiService->testConnection($apiKey, $model);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Generate dynamic AI Prompt based on selected Polda.
     */
    protected function generateDynamicPrompt(?Polda $polda): string
    {
        $isNational = ($polda?->code === 'DIVHUMAS');
        if ($isNational) {
            return <<<PROMPT
ROLE & POSISI STRATEGIS:
Bertindak sebagai Perwira Menengah / Senior Media Intelligence Analyst & Strategic Public Relations Advisor untuk Kapolri, Wakapolri, dan Kadivhumas Polri di Mabes Polri (Divisi Humas Polri - Komando & Orkestrasi Nasional).

DOKTRIN & PRINSIP UTAMA ANALISIS KEHUMASAN POLRI:
1. HUMANIS & BEREMPATI: Mengedepankan keselamatan dan kemaslahatan masyarakat (Salus Populi Suprema Lex Esto). Berempati mendalam pada keresahan publik, mengutamakan pendekatan persuasif, edukatif, perlindungan korban, dan restorative justice.
2. TRANSPARAN & AKUNTABEL: Keterbukaan informasi publik secara jujur dan berimbang. Tidak bersikap defensif, tidak menutupi fakta atau potensi kekeliruan prosedur anggota di lapangan (jika ada oknum), serta selalu memaparkan tindakan korektif nyata, komitmen Propam, dan kepastian hukum berkeadilan.
3. OBJEKTIF BERBASIS FAKTA EMPIRIS (5W+1H): Seluruh analisa wajib berpijak kokoh pada verifikasi kronologi riil, data intelijen, keterangan saksi, barang bukti forensik, serta rujukan resmi portal media nasional terpercaya. Bukan asumsi, rumor, atau framing sepihak.
4. TAJAM, PREVENTIF & SOLUTIF (ACTIONABLE): Pimpinan (Kapolri & Kadivhumas) sangat intens terhadap kualitas analisa. Hindari bahasa normatif atau klise. Bedah akar masalah (root cause) dan berikan langkah taktis berjenjang (0-2 jam, 2-6 jam, 24 jam).

TUGAS INTELIJEN HARIAN & MANDAT PIMPINAN:
Lakukan pemantauan intelijen 24 jam terhadap media siber dan opini publik skala nasional (WIB) dengan fokus mutlak pada dua pilar:

PILAR 1: SITUASI KAMTIBMAS NASIONAL (FISIK & SIBER):
- Kamtibmas Fisik di Lapangan: Stabilitas keamanan di 34 Polda Jajaran, potensi konflik horizontal/komunal, unjuk rasa berskala besar, kerusuhan, sengketa agraria/buruh, aksi terorisme/radikalisme, dan kejahatan konvensional menonjol.
- Kamtibmas Siber & Persepsi Publik: Dinamika ruang digital, pergerakan narasi propaganda liar, provokasi kebencian bernuansa SARA, kampanye disinformasi terstruktur, polarisasi politik, serta upaya delegitimasi institusi penegak hukum.

PILAR 2: KAJIAN RESIKO & DAMPAK ESKALASI JIKA TIDAK DIMITIGASI (UNMITIGATED RISK ASSESSMENT) — ATENSI INTENS PIMPINAN:
Pimpinan SANGAT INTENS mengenai hal ini. Untuk setiap isu sensitif dan pada Early Warning System (EWS), Anda WAJIB membedah proyeksi skenario terburuk jika tidak dimitigasi segera:
- Dampak Jangka Pendek (0–6 Jam Tanpa Mitigasi): Viralitas liar di medsos, framing sepihak buzzer menguasai algoritma, netizen menuduh Polri lambat atau menutup-nutupi kasus, hilangnya inisiatif informasi resmi (information vacuum).
- Dampak Jangka Menengah (6–24 Jam Tanpa Mitigasi): Media massa arus utama mengutip pernyataan kritis pengamat/LSM, terbentuknya framing delegitimasi, potensi mobilisasi massa aksi turun ke jalan di Polda/Polres, sentimen anjlok ke Zona Merah.
- Dampak Jangka Panjang (>24 Jam Tanpa Mitigasi): Krisis kepercayaan publik nasional (institutional trust crisis), polarisasi sosial meluas, hingga potensi gesekan fisik atau gangguan kamtibmas nyata.

SUMBER DATA:
Telusuri 22 Portal Media Online Nasional Terverifikasi (Antara News, Detikcom, CNN Indonesia, CNBC Indonesia, Liputan6, SINDOnews, Republika, JPNN, Kumparan, BBC Indonesia, dll.), Google News, X (Twitter), Instagram, TikTok, Facebook, YouTube, serta Tribratanews Polri. Prioritaskan informasi 24 jam terakhir dan sertakan tautan sumber serta waktu publikasi (WIB).

KETENTUAN PENTING:
- Angka sentimen WAJIB berupa angka bulat (integer) persen dan TIDAK BOLEH bernilai null (contoh: positif: 65, netral: 25, negatif: 10, total harus 100%).
- Berikan narasi tegas, presisi, berwibawa, humanis, dan berstandar Mabes Polri pada executive_brief, snapshot, dan respon taktis.

OUTPUT WAJIB:
Sajikan laporan HANYA dalam blok JSON murni terstruktur (di dalam blok ```json ... ```) tanpa teks pendahuluan atau penutup agar langsung terurai otomatis dan tersaji di SIMOD HUMAS MABES POLRI:

{
  "title": "LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK NASIONAL - DIVISI HUMAS POLRI",
  "report_date": "YYYY-MM-DD",
  "report_time": "HH.MM WIB",
  "top_concern": "Peringatan dini paling mendesak bagi Kapolri & Kadivhumas mengenai titik rawan kamtibmas dan potensi eskalasi krisis reputasi hari ini...",
  "executive_brief": "Paragraf eksekutif berwibawa dan tajam (maksimal 250 kata) merangkum: (1) Situasi riil kamtibmas nasional & 34 Polda jajaran berdasarkan fakta empiris, (2) Kajian risiko dan dampak fatal jika lambat dimitigasi, (3) Arahan komunikasi publik yang humanis dan transparan...",
  "snapshot_national_status": "HIJAU - KONDUSIF / KUNING - WASPADA / MERAH - ATENSI",
  "snapshot_national_desc": "Uraian 1-2 kalimat narasi kunci yang menggerakkan sentimen nasional Polri hari ini...",
  "snapshot_satwil_status": "HIJAU - KONDUSIF / KUNING - WASPADA / MERAH - ATENSI",
  "snapshot_satwil_desc": "Uraian 1-2 kalimat narasi kunci terkait stabilitas kamtibmas dan dinamika 34 Polda jajaran hari ini...",
  "sentiment_national": { "positif": 65, "netral": 25, "negatif": 10 },
  "sentiment_satwil": { "positif": 60, "netral": 30, "negatif": 10 },
  "priority_responses": {
    "response_0_2h": {
      "title": "Validasi Fakta & Tindak Cepat Berempati",
      "action": "Aksi taktis 0-2 jam koordinasi Biro Penmas dengan Satker Mabes / Polda terkait untuk validasi data riil 5W+1H, pendampingan empati humanis, dan penetapan satu pintu juru bicara resmi..."
    },
    "response_2_6h": {
      "title": "Keterbukaan Informasi & Rilis Transparan",
      "action": "Aksi 2-6 jam menyusun rilis pers terbuka, infografis kronologi berbasis barang bukti riil, doorstop pimpinan, dan arahan amplifikasi ke 34 Kabid Humas Polda..."
    },
    "response_24h": {
      "title": "Mitigasi Komprehensif & Pemulihan Public Trust",
      "action": "Aksi 24 jam masifikasi konten edukatif humanis Polri Presisi serentak melalui seluruh akun dinas 34 Polda dan 500+ Polres jajaran, evaluasi pergerakan sentimen publik..."
    }
  },
  "national_issues": [
    {
      "title": "Judul Isu / Berita Strategis Nasional 1",
      "source": "Nama Media Nasional | HH.MM WIB",
      "url": "https://tautan-berita-sumber.com",
      "fact": "Salinan ringkasan isi berita dan fakta peristiwa nasional secara lengkap berbasis 5W+1H...",
      "kamtibmas_impact": "Dampak terhadap stabilitas kamtibmas fisik dan ketertiban ruang siber nasional...",
      "unmitigated_risk": "Kajian risiko eskalasi jika tidak ada respon kehumasan cepat dalam 6-24 jam...",
      "sentiment": "Positif/Netral/Negatif",
      "virality": "Tinggi/Sedang/Rendah",
      "risk": "Hijau/Kuning/Merah"
    }
  ],
  "regional_issues": [
    {
      "title": "Judul Isu Menonjol Polda Jajaran (Contoh: Polda Metro Jaya / Polda Jabar / dll.)",
      "source": "Nama Media / Humas Polda | HH.MM WIB",
      "url": "https://tautan-berita-sumber.com",
      "fact": "Salinan ringkasan fakta peristiwa di tingkat kewilayahan/Polda jajaran...",
      "kamtibmas_impact": "Dampak terhadap stabilitas kewilayahan...",
      "unmitigated_risk": "Potensi meluas menjadi isu nasional jika tidak dimitigasi segera...",
      "sentiment": "Positif/Netral/Negatif",
      "virality": "Tinggi/Sedang/Rendah",
      "risk": "Hijau/Kuning/Merah"
    }
  ],
  "early_warnings": [
    {
      "level": "MERAH / KUNING",
      "title": "Isu Negatif / Ancaman Kamtibmas Berisiko Nasional",
      "fact": "Fakta empiris peristiwa dan pemicu eskalasi sentimen publik secara luas...",
      "kamtibmas_aspect": "Dimensi Kamtibmas Terancam (Keamanan Fisik / Polarisasi Siber / Citra Institusi / Gesekan Sosial)",
      "risk": "Potensi dampak langsung terhadap opini publik dan legitimasi institusi Polri...",
      "unmitigated_risk": "KAJIAN DAMPAK FATAL JIKA TIDAK DIMITIGASI (0-6 jam: viralitas liar; 6-24 jam: demonstrasi/krisis kepercayaan; >24 jam: delegitimasi penegakan hukum)...",
      "response_0_2h": "Respon taktis Mabes Polri 0-2 jam (verifikasi, empati pimpinan, satu pintu juru bicara)...",
      "response_2_6h": "Konsolidasi data terbuka, infografis fakta, dan doorstop pimpinan 2-6 jam...",
      "response_6_24h": "Amplifikasi klarifikasi, evaluasi sentimen, dan asistensi kewilayahan 24 jam...",
      "pic": "Biro Penmas / Biro Multimedia Divhumas Polri"
    }
  ],
  "strategic_contents": [
    {
      "no": 1,
      "label": "HERO / EDUKASI / KLARIFIKASI",
      "title": "Judul / Tema Konten Publikasi Nasional",
      "format": "Press Conference / Video Reel Humanis / Carousel / Infografis Edukasi",
      "platform": "TV Nasional / Portal Media / Instagram @divisihumaspolri / TikTok / YouTube",
      "target": "Masyarakat umum, netizen, akademisi, media massa nasional",
      "priority": "Segera / 24 Jam"
    }
  ],
  "agenda_setting": [
    {
      "day": "Hari, Tgl",
      "time": "HH.MM WIB",
      "theme": "Tema Orkestrasi Pemberitaan Terjadwal",
      "format": "Rilis Media & Amplifikasi Medsos",
      "goal": "Penguatan Public Trust terhadap Keterbukaan Informasi dan Pelayanan Presisi",
      "pic": "Divhumas Polri & 34 Bidhumas Polda"
    }
  ],
  "recommendations": [
    {
      "target": "1 | 0-24 jam",
      "issue": "Isu Prioritas Nasional",
      "action": "Instruksi taktis komando Divhumas: pendekatan humanis, rilis transparan berbasis fakta, dan orkestrasi 34 Polda",
      "pic": "Kadivhumas / Karo Penmas / Kabid Humas Jajaran",
      "indicator": "Sentimen positif >80%, spekulasi hoaks teredam tuntas, situasi kamtibmas tetap kondusif"
    }
  ]
}

BERIKUT DATA BERITA / POSTINGAN UNTUK DIANALISIS:
[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]
PROMPT;
        }

        $name = $polda?->name ?? 'Polda Sulawesi Barat';
        $short = $polda?->code === 'SULBAR' ? 'Polda Sulbar' : ($polda?->name ?? 'Polda');
        $tz = ($polda?->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';
        $polresList = $polda && $polda->polres && $polda->polres->isNotEmpty()
            ? $polda->polres->pluck('name')->implode(', ')
            : 'Polresta Mamuju, Polres Majene, Polres Polewali Mandar, Polres Mamasa, Polres Pasangkayu, Polres Mamuju Tengah';

        return <<<PROMPT
ROLE & POSISI STRATEGIS:
Bertindak sebagai Senior Media Intelligence Analyst dan Strategic Public Relations Advisor untuk Kapolda dan Kabid Humas {$name} ({$short}).

DOKTRIN & PRINSIP UTAMA ANALISIS KEHUMASAN POLRI:
1. HUMANIS & BEREMPATI: Mengedepankan keselamatan dan perlindungan masyarakat. Mengutamakan pendekatan dialogis, persuasif, mendengarkan keluhan warga, dan restorative justice.
2. TRANSPARAN & AKUNTABEL: Keterbukaan informasi berbasis fakta. Tidak defensif terhadap kritik warga, jujur memaparkan fakta penanganan perkara, serta mengedepankan tindakan korektif nyata demi kepastian hukum.
3. FAKTUAL & BERBASIS DATA (5W+1H): Analisa wajib berpijak pada verifikasi kronologi riil lapangan dan kutipan resmi.
4. PREVENTIF & SOLUTIF: Bedah akar masalah kamtibmas wilayah dan paparkan kajian risiko jika isu dibiarkan tanpa mitigasi cepat.

TUGAS HARIAN:
Lakukan pemantauan pemberitaan terbaru menggunakan tanggal dan waktu aktual ({$tz}), dengan dua cakupan:
1. NASIONAL: Seluruh isu strategis, trending, sentimen, prestasi, dan krisis reputasi terkait institusi Polri se-Indonesia.
2. KEWILAYAHAN: Pemberitaan khusus {$name}, monitoring jajaran ({$polresList}), isu kamtibmas menonjol, pelayanan masyarakat, kritik publik, penanganan kasus viral, serta potensi gangguan keamanan fisik dan siber.

PILAR KAJIAN RESIKO & DAMPAK ESKALASI JIKA TIDAK DIMITIGASI (ATENSI INTENS PIMPINAN):
Bedah proyeksi dampak jika isu lokal tidak segera dimitigasi dalam 6-24 jam: potensi viral ke skala nasional, ancaman aksi unjuk rasa, sentimen negatif, dan penurunan kepercayaan publik terhadap Polda jajaran.

SUMBER:
Telusuri 29 Portal Media Online Resmi Terverifikasi, Google News, X (Twitter), Instagram, TikTok, Facebook, YouTube, serta situs resmi Humas Polri. Prioritaskan informasi 24 jam terakhir dan sertakan tautan sumber serta waktu publikasi ({$tz}).

KETENTUAN PENTING:
- Angka sentimen WAJIB berupa angka bulat (integer) persen dan TIDAK BOLEH bernilai null (contoh: positif: 60, netral: 30, negatif: 10, total harus 100%).
- Berikan narasi tegas, presisi, humanis, dan lugas pada executive_brief, snapshot, dan langkah mitigasi.

OUTPUT WAJIB:
Sajikan laporan HANYA dalam blok JSON murni terstruktur (di dalam blok ```json ... ```) tanpa teks pendahuluan atau penutup agar langsung terurai otomatis dan tersaji di SIMOD HUMAS:

{
  "title": "LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK {$short}",
  "report_date": "YYYY-MM-DD",
  "report_time": "HH.MM {$tz}",
  "top_concern": "Ringkasan isu kamtibmas paling mendesak yang butuh perhatian Kapolda & PJU hari ini...",
  "executive_brief": "Ringkasan maksimal 250 kata mengenai situasi kamtibmas {$short}, stabilitas wilayah jajaran, kajian risiko jika tidak dimitigasi, dan arahan komunikasi humanis transparan...",
  "snapshot_national_status": "KUNING - WASPADA / HIJAU - KONDUSIF / MERAH - ATENSI",
  "snapshot_national_desc": "Uraian 1-2 kalimat narasi kunci yang menggerakkan sentimen nasional Polri hari ini...",
  "snapshot_sulbar_status": "MERAH - PRIORITAS TINGGI / KUNING - WASPADA / HIJAU - KONDUSIF",
  "snapshot_sulbar_desc": "Uraian 1-2 kalimat narasi kunci yang memengaruhi sentimen wilayah {$name} hari ini...",
  "sentiment_national": { "positif": 60, "netral": 30, "negatif": 10 },
  "sentiment_sulbar": { "positif": 55, "netral": 30, "negatif": 15 },
  "priority_responses": {
    "response_0_2h": {
      "title": "Validasi Fakta & Pendekatan Humanis",
      "action": "Aksi taktis 0-2 jam koordinasi Satfung/Polres terkait untuk validasi fakta riil 5W+1H, pendampingan empati, dan satu pintu juru bicara..."
    },
    "response_2_6h": {
      "title": "Keterbukaan Informasi & Klarifikasi Terukur",
      "action": "Aksi 2-6 jam menyusun rilis pers resmi terbuka, FAQ terverifikasi, dan narasi penjelasan transparan berimbang..."
    },
    "response_24h": {
      "title": "Penguatan Citra & Patroli Presisi",
      "action": "Aksi 24 jam masifikasi konten humanis, pelayanan prima SIM/lalu lintas/kamtibmas, dan patroli siber..."
    }
  },
  "national_issues": [
    {
      "title": "Judul Isu / Berita Nasional 1",
      "source": "Nama Media | HH.MM {$tz}",
      "url": "https://tautan-berita-sumber.com",
      "fact": "Salinan ringkasan isi berita dan fakta peristiwa secara lengkap...",
      "kamtibmas_impact": "Dampak terhadap situasi kamtibmas...",
      "unmitigated_risk": "Risiko eskalasi jika tidak direspon...",
      "sentiment": "Positif/Netral/Negatif",
      "virality": "Tinggi/Sedang/Rendah",
      "risk": "Hijau/Kuning/Merah"
    }
  ],
  "sulbar_issues": [
    {
      "title": "Judul Isu / Berita Lokal 1",
      "source": "Nama Media Lokal / Polres | HH.MM {$tz}",
      "url": "https://tautan-berita-sumber.com",
      "fact": "Salinan ringkasan isi berita, fakta peristiwa, dan kutipan relevan secara lengkap...",
      "kamtibmas_impact": "Dampak terhadap stabilitas keamanan wilayah...",
      "unmitigated_risk": "Potensi eskalasi ke tingkat Polda/Nasional jika diabaikan...",
      "sentiment": "Positif/Netral/Negatif",
      "virality": "Tinggi/Sedang/Rendah",
      "risk": "Hijau/Kuning/Merah"
    }
  ],
  "early_warnings": [
    {
      "level": "MERAH / KUNING",
      "title": "Isu Negatif / Kerawanan Kamtibmas Berisiko",
      "fact": "Fakta empiris peristiwa dan pemicu potensi eskalasi...",
      "kamtibmas_aspect": "Dimensi Kamtibmas Terancam (Keamanan Fisik / Polarisasi Siber / Citra / Kerukunan Warga)",
      "risk": "Dampak terhadap reputasi dan kamtibmas wilayah...",
      "unmitigated_risk": "KAJIAN DAMPAK FATAL JIKA TIDAK DIMITIGASI (0-6 jam: provokasi liar; 6-24 jam: potensi aksi massa/unjuk rasa; >24 jam: krisis kepercayaan)...",
      "response_0_2h": "Respon taktis 0-2 jam (verifikasi fakta & pendekatan persuasif)...",
      "response_2_6h": "Konsolidasi data transparan & rilis pers berimbang 2-6 jam...",
      "response_6_24h": "Patroli dialogis, penuntasan perkara, dan evaluasi 24 jam...",
      "pic": "Satfung / Polres Pelaksana"
    }
  ],
  "strategic_contents": [
    {
      "no": 1,
      "label": "HERO / EDUKASI / KLARIFIKASI",
      "title": "Judul / Hook Konten Kreatif",
      "format": "Reels / TikTok / Carousel / Infografis",
      "platform": "Instagram / TikTok / Facebook / Portal Resmi",
      "target": "Target audiens",
      "priority": "Segera / 24 Jam"
    }
  ],
  "agenda_setting": [
    {
      "day": "Hari, Tgl",
      "time": "HH.MM {$tz}",
      "theme": "Tema & Judul Konten Terjadwal",
      "format": "Format Media",
      "goal": "Tujuan Komunikasi Publik & Kamtibmas Kondusif",
      "pic": "Satuan / Humas"
    }
  ],
  "recommendations": [
    {
      "target": "1 | 0-24 jam",
      "issue": "Isu Prioritas",
      "action": "Langkah Komunikasi Konkret, Humanis, & Transparan",
      "pic": "PIC yang Disarankan",
      "indicator": "Indikator Keberhasilan (Kamtibmas Kondusif & Public Trust Terjaga)"
    }
  ]
}

BERIKUT DATA BERITA / POSTINGAN UNTUK DIANALISIS:
[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]
PROMPT;
    }

    /**
     * Seed default realistic report if table is empty.
     */
    protected function seedDefaultReport(int $poldaId, ?Polda $polda): MediaIntelligenceReport
    {
        $isNational = ($polda?->code === 'DIVHUMAS');
        if ($isNational) {
            return MediaIntelligenceReport::create([
                'polda_id'                 => $poldaId,
                'report_date'              => now()->format('Y-m-d'),
                'report_time'              => '19.00 WIB',
                'title'                    => 'LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK NASIONAL - DIVISI HUMAS POLRI',
                'snapshot_national_status' => 'HIJAU - KONDUSIF',
                'snapshot_national_desc'   => 'Stabilitas kamtibmas fisik dan ruang siber 34 Polda terkendali, didukung keterbukaan informasi publik dan pendekatan humanis penegakan hukum.',
                'snapshot_sulbar_status'   => 'KUNING - WASPADA',
                'snapshot_sulbar_desc'     => 'Orkestrasi Mabes Polri fokus pada mitigasi dini disinformasi digital dan penguatan empati pelayanan publik di tingkat Polres jajaran.',
                'top_concern'              => 'Antisipasi disinformasi siber terorganisir dan eskalasi provokasi lokal; pimpinan mengatensi respon cepat <2 jam berbasis fakta empiris transparan guna mencegah delegitimasi publik.',
                'executive_brief'          => "Situasi kamtibmas nasional di seluruh wilayah hukum 34 Polda Jajaran terpantau dalam kondisi kondusif dan terkendali. Ruang siber didominasi sentimen positif (86.8%) atas keberhasilan penindakan sindikat kejahatan siber lintas negara dan modernisasi layanan Presisi. Namun demikian, telaah intelijen mendeteksi titik rawan berupa upaya framing negatif sepihak pada beberapa isu penegakan hukum lokal. Pimpinan menginstruksikan pendekatan kehumasan yang humanis, transparan, dan tidak defensif—mengakui fakta riil secara jujur, mendampingi korban/warga secara berempati, serta merilis kronologi 5W+1H sebelum terjadi kekosongan informasi (information vacuum) yang berisiko memicu polarisasi massa.",
                'core_decision'            => 'Instruksikan seluruh Kabid Humas 34 Polda jajaran mengedepankan komunikasi humanis, keterbukaan fakta tanpa defensif, serta mitigasi risiko eskalasi kamtibmas dalam 2 jam pertama.',
                'sentiment_national'       => ['positif' => 68, 'netral' => 24, 'negatif' => 8],
                'sentiment_sulbar'         => ['positif' => 65, 'netral' => 25, 'negatif' => 10],
                'priority_responses'       => [
                    'response_0_2h' => [
                        'title'  => 'Validasi Fakta & Sikap Humanis Berempati',
                        'action' => 'Biro Penmas Divhumas memvalidasi data empiris 5W+1H bersama Satker Mabes & Polda terkait, menunjuk juru bicara satu pintu, dan menyampaikan empati langsung pada korban/masyarakat.',
                    ],
                    'response_2_6h' => [
                        'title'  => 'Keterbukaan Informasi & Rilis Transparan Berimbang',
                        'action' => 'Siapkan doorstop pimpinan, rilis pers terbuka berbasis barang bukti, serta infografis edukasi tanpa narasi defensif untuk media massa dan warganet.',
                    ],
                    'response_24h'  => [
                        'title'  => 'Mitigasi Berkelanjutan & Pemulihan Public Trust',
                        'action' => 'Instruksikan amplifikasi masif ke seluruh akun dinas 34 Polda dan 500+ Polres jajaran, dialog dengan tokoh masyarakat, dan evaluasi berkala pergerakan sentimen publik.',
                    ],
                ],
                'national_issues'          => [
                    [
                        'title'            => 'Kapolri Instruksikan Penguatan Pelayanan Publik dan Transformasi Presisi Berkeadilan',
                        'source'           => 'Antara News | 14.30 WIB',
                        'url'              => 'https://www.antaranews.com',
                        'fact'             => 'Kapolri menekankan pentingnya transparansi, empati anggota di lapangan, dan kepastian hukum yang humanis demi menjaga marwah institusi dan public trust.',
                        'kamtibmas_impact' => 'Memperkuat rasa aman masyarakat dan meningkatkan legitimasi Polri dalam pemeliharaan kamtibmas nasional.',
                        'unmitigated_risk' => 'Jika tidak diamplifikasi secara masif ke akar rumput, narasi positif dapat tenggelam oleh isu-isu friksi lokal.',
                        'sentiment'        => 'Positif',
                        'virality'         => 'Tinggi',
                        'risk'             => 'Hijau',
                    ],
                    [
                        'title'            => 'Bareskrim Polri Bongkar Jaringan Kejahatan Siber Internasional',
                        'source'           => 'Detikcom | 11.20 WIB',
                        'url'              => 'https://news.detik.com',
                        'fact'             => 'Dittipidsiber Bareskrim mengungkap sindikat penipuan daring lintas negara dan menyelamatkan dana ratusan miliar milik masyarakat.',
                        'kamtibmas_impact' => 'Menekan angka kejahatan siber transnasional dan melindungi ekosistem ekonomi digital masyarakat.',
                        'unmitigated_risk' => 'Potensi serangan balik propaganda siber dari kelompok sindikat yang mencoba mendiskreditkan penyidik.',
                        'sentiment'        => 'Positif',
                        'virality'         => 'Tinggi',
                        'risk'             => 'Hijau',
                    ],
                ],
                'sulbar_issues'            => [
                    [
                        'title'            => 'Divhumas Polri Salurkan Asistensi Komunikasi Krisis & Counter Hoaks ke 34 Polda',
                        'source'           => 'Tribrata News | 16.00 WIB',
                        'url'              => 'https://tribratanews.polri.go.id',
                        'fact'             => 'Biro PID dan Penmas Divhumas memperkuat standar SOP satu pintu penanganan isu viral dan mitigasi konflik horizontal di seluruh Polda.',
                        'kamtibmas_impact' => 'Mencegah disinformasi lokal membesar menjadi krisis kamtibmas berskala nasional.',
                        'unmitigated_risk' => 'Keterlambatan respon daerah berisiko memicu spekulasi liar netizen dan polarisasi warga.',
                        'sentiment'        => 'Positif',
                        'virality'         => 'Sedang',
                        'risk'             => 'Hijau',
                    ],
                ],
                'early_warnings'           => [
                    [
                        'level'            => 'KUNING',
                        'title'            => 'Potensi Kampanye Disinformasi Digital Terstruktur Jelang Agenda Nasional',
                        'fact'             => 'Terdeteksi anomali peningkatan tagar provokatif dan potongan video peristiwa lama yang diunggah ulang tanpa konteks untuk mendelegitimasi penegakan hukum.',
                        'kamtibmas_aspect' => 'Kamtibmas Ruang Siber & Polarisasi Opini Publik',
                        'risk'             => 'Erosi kepercayaan publik dan potensi pembentukan persepsi keliru terhadap transparansi Polri.',
                        'unmitigated_risk' => 'Jika tidak diklarifikasi dalam 0-6 jam, narasi liar akan diamplifikasi oleh akun bot dan media agregator; dalam 6-24 jam berisiko memicu gelombang unjuk rasa di lapangan.',
                        'response_0_2h'    => 'Biro Multimedia patroli siber 24 jam dan koordinasi validasi fakta kronologi riil 5W+1H bersama Bareskrim.',
                        'response_2_6h'    => 'Rilis stempel fakta resmi, video klarifikasi humanis transparan, dan konferensi pers terbuka dengan media massa.',
                        'response_6_24h'   => 'Monitoring pergerakan sentimen netizen, edukasi digital masyarakat, dan asistensi penanganan hukum siber proporsional.',
                        'pic'              => 'Biro Penmas & Biro Multimedia Divhumas Polri',
                    ],
                ],
                'strategic_contents'       => [
                    [
                        'no'       => 1,
                        'label'    => 'HERO & HUMANIS',
                        'title'    => 'Polri Humanis: Sentuhan Empati Bhabinkamtibmas dan Layanan Cepat 110',
                        'format'   => 'Video Dokumenter Pendek (Reels / TikTok / YouTube Shorts)',
                        'platform' => 'Instagram, TikTok, YouTube Resmi Divhumas Polri',
                        'target'   => 'Generasi Muda, Netizen & Masyarakat Luas',
                        'priority' => 'Segera',
                    ],
                ],
                'agenda_setting'           => [
                    [
                        'day'    => now()->isoFormat('dddd, D MMMM Y'),
                        'time'   => '10.00 WIB',
                        'theme'  => 'Transparansi & Akuntabilitas Kinerja Presisi Berbasis Fakta',
                        'format' => 'Konferensi Pers Terbuka & Live Streaming YouTube Polri TV',
                        'goal'   => 'Menyampaikan akuntabilitas kinerja kepolisian secara jujur, terbuka, dan transparan kepada publik',
                        'pic'    => 'Biro Penmas Divhumas Polri',
                    ],
                ],
                'recommendations'          => [
                    [
                        'target'    => '1 | 0-24 jam',
                        'issue'     => 'Mitigasi Proaktif Disinformasi & Penguatan Keterbukaan Informasi',
                        'action'    => 'Keluarkan instruksi komando ke 34 Kabid Humas untuk mengedepankan empati, tidak bersikap defensif, dan merilis data fakta terbuka dalam 2 jam pertama penanganan kasus menonjol.',
                        'pic'       => 'Kadivhumas & Karo Penmas Divhumas Polri',
                        'indicator' => 'Sentimen positif >80%, spekulasi publik teredam, dan kamtibmas kewilayahan kondusif.',
                    ],
                ],
                'raw_input'                => 'Laporan Intelijen Media Divisi Humas Polri (Mabes Polri) Berstandar Humanis, Transparan & Berbasis Fakta',
            ]);
        }
        return MediaIntelligenceReport::create([
            'polda_id'                 => $poldaId,
            'report_date'              => now()->format('Y-m-d'),
            'report_time'              => '19.03 WITA',
            'title'                    => 'LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK ' . ($polda?->name ?? 'POLDA SULAWESI BARAT'),
            'snapshot_national_status' => 'KUNING - WASPADA',
            'snapshot_national_desc'   => 'Pengungkapan kasus narkotika dan pengamanan tahapan pemilu serentak mendominasi sentimen positif institusi Polri secara nasional.',
            'snapshot_sulbar_status'   => 'MERAH - PRIORITAS TINGGI',
            'snapshot_sulbar_desc'     => 'Sentimen lokal berfokus pada atensi pengamanan jalur trans-Sulawesi di pesisir Majene dan patroli siber Pilkada Mamuju.',
            'top_concern'              => 'Pencegahan disinformasi tahapan Pilkada di ruang digital dan mitigasi potensi kemacetan di pesisir Majene-Mamuju.',
            'executive_brief'          => "Situasi kamtibmas di wilayah hukum {$polda?->name} terpantau relatif aman dan kondusif. Sentimen publik terhadap kinerja jajaran kepolisian mencapai 84.2% positif, didorong oleh akselerasi patroli malam presisi, bantuan sosial di Pasangkayu, dan penertiban balap liar di Polresta Mamuju.",
            'core_decision'            => 'Lanjutkan patroli siber terpadu dan amplifikasi narasi positif melalui media sosial jajaran 6 Polres.',
            'sentiment_national'       => ['positif' => 60, 'netral' => 30, 'negatif' => 10],
            'sentiment_sulbar'         => ['positif' => 55, 'netral' => 30, 'negatif' => 15],
            'priority_responses'       => [
                'response_0_2h' => [
                    'title'  => 'Validasi Fakta & Tindak Cepat',
                    'action' => 'Ditreskrimsus & Bidhumas koordinasi satu pintu klarifikasi fakta riil guna meredam spekulasi opini negatif di media siber.',
                ],
                'response_2_6h' => [
                    'title'  => 'Konsolidasi Data & Rilis Terukur',
                    'action' => 'Polresta Mamuju & Polres Majene siapkan FAQ dan rilis resmi terkait penanganan kejadian menonjol untuk transparansi publik.',
                ],
                'response_24h'  => [
                    'title'  => 'Amplifikasi Citra & Publikasi Positif',
                    'action' => 'Viralkan konten patroli humanis, edukasi keselamatan lalu lintas, dan bakti sosial kepolisian guna mendominasi ruang digital.',
                ],
            ],
            'national_issues'          => [
                [
                    'title'     => 'Polri Gelar Operasi Mantap Praja Serentak 2026',
                    'source'    => 'Detik News | 14.20 WITA',
                    'url'       => 'https://news.detik.com',
                    'fact'      => 'Mabes Polri menerjunkan personel pengamanan tahapan pilkada di seluruh wilayah Polda jajaran.',
                    'sentiment' => 'Positif',
                    'virality'  => 'Tinggi',
                    'risk'      => 'Hijau',
                ],
                [
                    'title'     => 'Bareskrim Ungkap Sindikat Narkotika Lintas Provinsi',
                    'source'    => 'Antara News | 11.15 WITA',
                    'url'       => 'https://antaranews.com',
                    'fact'      => 'Penyitaan barang bukti sabu puluhan kilogram dari jaringan antarpulau mendapat apresiasi luas.',
                    'sentiment' => 'Positif',
                    'virality'  => 'Sedang',
                    'risk'      => 'Hijau',
                ],
            ],
            'sulbar_issues'            => [
                [
                    'title'     => 'Polda Sulbar Intensifkan Patroli Siber Jelang Pilkada',
                    'source'    => 'Kabar Sulbar | 16.45 WITA',
                    'url'       => 'https://kabarsulbar.com',
                    'fact'      => 'Subbid Siber Ditreskrimsus dan Bidhumas Polda Sulbar memantau potensi ujaran kebencian dan hoaks di media sosial.',
                    'sentiment' => 'Positif',
                    'virality'  => 'Sedang',
                    'risk'      => 'Hijau',
                ],
                [
                    'title'     => 'Polres Majene Bersihkan Material Longsor di Jalur Trans-Sulawesi',
                    'source'    => 'Mamuju Pos | 10.30 WITA',
                    'url'       => 'https://mamujupos.com',
                    'fact'      => 'Personel Satlantas Polres Majene bersama instansi terkait bergerak cepat membuka arus lalu lintas yang tertutup pohon tumbang.',
                    'sentiment' => 'Positif',
                    'virality'  => 'Sedang',
                    'risk'      => 'Hijau',
                ],
            ],
            'early_warnings'           => [
                [
                    'level'         => 'KUNING',
                    'title'         => 'Potensi Isu Gesekan Antar-Pendukung Pilkada di Wilayah Pesisir',
                    'fact'          => 'Muncul saling sindir antarpendukung pasangan calon di grup Facebook lokal Mamuju dan Polman.',
                    'risk'          => 'Dapat memicu ketegangan fisik bila tidak segera diimbangi himbauan damai.',
                    'response_0_2h' => 'Patroli siber peringatkan akun provokatif secara humanis.',
                    'response_2_6h' => 'Rilis video himbauan damai dari para tokoh adat dan agama.',
                    'response_6_24h'=> 'Pertemuan silaturahmi Kamtibmas dengan tim sukses kedua kubu.',
                    'pic'           => 'Ditintelkam & Bidhumas Polda',
                ],
            ],
            'strategic_contents'       => [
                [
                    'no'       => 1,
                    'label'    => 'HERO KONTEN',
                    'title'    => 'Polri Bersama Rakyat: Patroli Humanis Nelayan Pesisir Mamuju',
                    'format'   => 'Video Reels & TikTok (9:16)',
                    'platform' => 'Instagram & TikTok @humaspoldasulbar',
                    'target'   => 'Generasi Muda & Masyarakat Maritim',
                    'priority' => 'Segera',
                ],
            ],
            'agenda_setting'           => [
                [
                    'day'    => 'Kamis, 29 Okt',
                    'time'   => '09.00 WITA',
                    'theme'  => 'Sosialisasi Operasi Keselamatan Lalu Lintas Jalur Lintas Barat',
                    'format' => 'Live Streaming & Press Briefing',
                    'goal'   => 'Tingkatkan kesadaran berkendara',
                    'pic'    => 'Ditlantas & Bidhumas',
                ],
            ],
            'recommendations'          => [
                [
                    'target'    => '0-24 jam',
                    'issue'     => 'Pengawalan Netralitas Pemilu',
                    'action'    => 'Publikasikan ikrar netralitas seluruh personel Polda Sulbar secara serentak.',
                    'pic'       => 'Bidpropam & Bidhumas',
                    'indicator' => 'Indeks kepercayaan publik bertahan di atas 80%',
                ],
            ],
            'raw_input'                => null,
        ]);
    }

    /**
     * Toggle bookmark artikel (disimpan di database agar tidak ikut dibersihkan).
     */
    public function toggleBookmark(int $id): JsonResponse
    {
        $article = MediaArticle::findOrFail($id);
        $article->update(['is_bookmarked' => ! $article->is_bookmarked]);

        return response()->json(['id' => (string) $article->id, 'is_bookmarked' => $article->is_bookmarked]);
    }

    /**
     * Impor bookmark lama dari localStorage browser (sekali jalan).
     */
    public function importBookmarks(Request $request): JsonResponse
    {
        $ids = collect($request->input('ids', []))->filter(fn ($i) => is_numeric($i))->map(fn ($i) => (int) $i)->take(2000);
        $count = MediaArticle::whereIn('id', $ids)->update(['is_bookmarked' => true]);

        return response()->json(['imported' => $count]);
    }

    /**
     * Daftarkan Topik Atensi Pimpinan (Smart Dossier) baru.
     */
    public function storeTopic(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'keywords'    => 'required|string|max:1000',
            'description' => 'nullable|string|max:1000',
            'priority'    => 'nullable|in:urgent,high,medium,low',
            'polda_code'  => 'nullable|string|max:50',
        ]);

        $poldaCode = $validated['polda_code'] ?? $request->query('polda', 'SULBAR');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $polda = Polda::where('code', $searchCode)->first() ?? Polda::where('is_active', true)->first();

        MediaTopic::create([
            'polda_id'    => $polda->id,
            'title'       => $validated['title'],
            'keywords'    => $validated['keywords'],
            'description' => $validated['description'] ?? null,
            'priority'    => $validated['priority'] ?? 'high',
            'status'      => 'active',
            'created_by'  => auth()->id(),
        ]);

        return back()->with('success', "Topik Atensi Pimpinan \"{$validated['title']}\" berhasil dibuat.");
    }

    /**
     * Perbarui Topik Atensi Pimpinan.
     */
    public function updateTopic(Request $request, int $id): RedirectResponse
    {
        $topic = MediaTopic::findOrFail($id);

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'keywords'    => 'required|string|max:1000',
            'description' => 'nullable|string|max:1000',
            'priority'    => 'nullable|in:urgent,high,medium,low',
            'status'      => 'nullable|in:active,archived',
        ]);

        $topic->update($validated);

        return back()->with('success', "Topik Atensi \"{$topic->title}\" berhasil diperbarui.");
    }

    /**
     * Hapus Topik Atensi Pimpinan.
     */
    public function deleteTopic(int $id): RedirectResponse
    {
        $topic = MediaTopic::findOrFail($id);
        $title = $topic->title;
        $topic->delete();

        return back()->with('success', "Topik Atensi \"{$title}\" berhasil dihapus.");
    }

    /**
     * Toggle manual tagging artikel ke Topik Atensi tertentu.
     */
    public function toggleArticleTopic(Request $request, int $id): JsonResponse|RedirectResponse
    {
        $validated = $request->validate([
            'topic_id' => 'required|exists:media_topics,id',
        ]);

        $article = MediaArticle::findOrFail($id);
        $topicId = (int) $validated['topic_id'];

        $existing = DB::table('media_article_topic')
            ->where('media_article_id', $article->id)
            ->where('media_topic_id', $topicId)
            ->first();

        if ($existing) {
            DB::table('media_article_topic')
                ->where('media_article_id', $article->id)
                ->where('media_topic_id', $topicId)
                ->delete();
            $action = 'dilepas dari';
            $isTagged = false;
        } else {
            DB::table('media_article_topic')->insert([
                'media_article_id' => $article->id,
                'media_topic_id'   => $topicId,
                'is_manual'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);
            $action = 'dikaitkan ke';
            $isTagged = true;
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success'    => true,
                'article_id' => (string) $article->id,
                'topic_id'   => $topicId,
                'is_tagged'  => $isTagged,
                'message'    => "Berita berhasil {$action} Topik Atensi.",
            ]);
        }

        return back()->with('success', "Berita berhasil {$action} Topik Atensi.");
    }

    /**
     * Analisis intelijen instan berbasis AI (Google Gemini Pro) khusus untuk Topik Atensi Pimpinan.
     */
    public function analyzeTopicAi(Request $request, int $id): JsonResponse
    {
        $topic = MediaTopic::with('polda')->findOrFail($id);
        $polda = $topic->polda ?? Polda::find($topic->polda_id);

        if (!$polda) {
            return response()->json(['success' => false, 'message' => 'Polda terkait topik ini tidak valid.'], 404);
        }

        // Kumpulkan berita terkait (kombinasi manual + keyword match)
        $keywords = $topic->getKeywordsArray();
        $manualArticleIds = DB::table('media_article_topic')
            ->where('media_topic_id', $topic->id)
            ->pluck('media_article_id')
            ->toArray();

        $articlesQuery = MediaArticle::where('polda_id', $polda->id);

        if (!empty($manualArticleIds) || !empty($keywords)) {
            $articlesQuery->where(function ($q) use ($manualArticleIds, $keywords) {
                if (!empty($manualArticleIds)) {
                    $q->whereIn('id', $manualArticleIds);
                }
                foreach ($keywords as $kw) {
                    $q->orWhere('title', 'like', "%{$kw}%")
                      ->orWhere('summary', 'like', "%{$kw}%");
                }
            });
        }

        $matchingArticles = $articlesQuery->latest('pub_date')->limit(25)->get();

        if ($matchingArticles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Belum ada berita yang sesuai dengan kata kunci Topik \"{$topic->title}\". Tambahkan kata kunci yang lebih luas atau kaitkan berita secara manual.",
            ], 422);
        }

        $formattedArticles = $matchingArticles->map(function ($a) {
            return [
                'title'       => $a->title,
                'source_name' => $a->source_name,
                'category'    => $a->category,
                'pub_date'    => $a->pub_date ? $a->pub_date->format('d M Y, H.i') : '',
                'summary'     => $a->summary,
                'link'        => $a->url,
            ];
        })->toArray();

        $geminiService = new GeminiService();
        $apiKey = $geminiService->getApiKey($polda->id);
        $model = $geminiService->getModel($polda->id);

        if (empty($apiKey)) {
            return response()->json([
                'success' => false,
                'status'  => 'missing_api_key',
                'message' => 'Google Gemini API Key belum diatur di menu Keamanan & Pemeliharaan -> Mesin Intelijen AI.',
            ], 422);
        }

        $result = $geminiService->analyzeTopicDossier($polda, $topic, $formattedArticles, $apiKey, $model);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        return response()->json([
            'success'        => true,
            'topic'          => [
                'id'          => $topic->id,
                'title'       => $topic->title,
                'priority'    => $topic->priority,
                'keywords'    => $topic->keywords,
                'description' => $topic->description,
            ],
            'analysis'       => $result['data'],
            'articles_count' => count($formattedArticles),
            'model_used'     => $result['model_used'] ?? $model,
            'latency_ms'     => $result['latency_ms'] ?? 0,
        ]);
    }
}