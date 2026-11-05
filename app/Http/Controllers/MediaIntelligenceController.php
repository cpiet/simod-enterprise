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
use App\Models\RssSyncLog;
use App\Services\GeminiService;
use App\Services\MediaArticleListing;
use App\Services\RssFeedService;
use App\Support\FastTime;
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

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);

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
                        ['polda_id' => $poldaId, 'url' => $item['link']],
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

        // Catat sinkron manual (refresh=1) agar penanda kesegaran data di halaman akurat.
        if (isset($liveArticles)) {
            $this->logManualSync($poldaId, $mediaFeeds->count(), count($liveArticles), count($liveArticles));
        }

        // Load active Media Topics (Smart Dossier Atensi Pimpinan)
        $dbTopics = MediaTopic::where('polda_id', $poldaId)
            ->where('status', 'active')
            ->latest()
            ->get();

        $topicsMap = [];
        foreach ($dbTopics as $t) {
            $aiAnalysis = $t->ai_analysis;
            if (!empty($aiAnalysis) && is_array($aiAnalysis)) {
                $aiAnalysis['whatsapp_script'] = GeminiService::buildRichWhatsAppScript($aiAnalysis, $selectedPolda, $t);
            }

            $topicsMap[$t->id] = [
                'id'                    => $t->id,
                'title'                 => $t->title,
                'keywords'              => $t->keywords,
                'keywords_array'        => $t->getKeywordsArray(),
                'description'           => $t->description,
                'priority'              => $t->priority,
                'status'                => $t->status,
                'ai_analysis'           => $aiAnalysis,
                'ai_analyzed_at'        => $t->ai_analyzed_at?->toISOString(),
                'ai_analyzed_at_humans' => $t->ai_analyzed_at ? $t->ai_analyzed_at->diffForHumans() : null,
                'ai_articles_count'     => (int) ($t->ai_articles_count ?? 0),
                'created_at'            => $t->created_at?->format('d M Y, H:i'),
                'articles_count'        => 0,
            ];
        }

        // Cakupan data: satu Polda (terkunci tenant) atau seluruh Polda aktif pada mode Nasional (hanya Mabes/Super Admin
        // yang dapat berada di mode ini; resolveActiveTenant() menolak personel daerah).
        $isNational = $this->aggregatesNational($selectedCode);
        $scopeIds = $isNational
            ? array_values(array_unique(array_merge($allPoldas->pluck('id')->all(), [$poldaId])))
            : [$poldaId];

        $listing = (new MediaArticleListing(
            $scopeIds,
            $isNational,
            $isNational ? 'Asia/Jakarta' : ($selectedPolda->timezone ?? 'Asia/Makassar')
        ))->setTopics($topicsMap);

        // Pencarian, filter, penggabungan berita ganda & paginasi dilakukan di server atas seluruh arsip.
        $filters = MediaArticleListing::filtersFromRequest($request, $scopeIds);
        $pageData = $listing->page($filters);
        $scopeFeeds = MediaFeed::whereIn('polda_id', $scopeIds)->get(['name', 'is_active']);
        $facets = $listing->facets($filters, $scopeFeeds->pluck('name')->unique()->values()->all());
        foreach ($listing->topicCounts() as $topicId => $topicCount) {
            $topicsMap[$topicId]['articles_count'] = $topicCount;
        }
        $stats = $listing->stats();
        $isLeadership = in_array($request->user()?->role, ['kadiv_humas', 'polda_leader', 'super_admin_mabes', 'superadmin'], true);

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

        // Pastikan suggested_topics selalu ada & terisi (Google News AI Trend Suggestions)
        $suggestedTopics = (is_array($report->suggested_topics) && !empty($report->suggested_topics))
            ? $report->suggested_topics
            : static::getDefaultSuggestedTopics($selectedPolda);

        if (empty($report->suggested_topics) || !is_array($report->suggested_topics)) {
            $report->update(['suggested_topics' => $suggestedTopics]);
            $report->suggested_topics = $suggestedTopics;
        }

        // Dynamic AI Prompt Template (Tenant-Aware)
        $aiPromptTemplate = $this->generateDynamicPrompt($selectedPolda);

        // Gemini AI Settings & Key Status
        $geminiService = new GeminiService();
        $rawApiKey = $geminiService->getApiKey($selectedPolda->id);
        $aiSettingModel = AiSetting::where('polda_id', $selectedPolda->id)->first();
        $aiSetting = [
            'has_api_key'     => !empty($rawApiKey),
            // Telemetri teknis (kunci tersamar, nama model) hanya untuk admin; pengguna lain tidak melihat vendor/model AI.
            'masked_api_key'  => $this->isAiAdmin() ? $geminiService->maskApiKey($rawApiKey) : null,
            'gemini_model'    => $this->isAiAdmin() ? ($aiSettingModel?->gemini_model ?? $geminiService->getModel($selectedPolda->id)) : null,
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
            'sampleArticles'   => $pageData['articles'],
            'articlesMeta'     => $pageData['meta'],
            'searchTerms'      => $pageData['terms'],
            'filters'          => $filters,
            'facets'           => $facets,
            'stats'            => collect($stats)->except('trend7')->merge(['portals_active' => $scopeFeeds->where('is_active', true)->count()])->all(),
            'freshness'        => $listing->freshness(),
            'situation'        => $isLeadership ? $listing->situation() : null,
            'isNational'       => $isNational,
            'myArea'           => $this->myArea($request),
            'topics'           => array_values($topicsMap),
            'report'           => $report,
            'aiPromptTemplate' => $aiPromptTemplate,
            'aiSetting'        => $aiSetting,
            'issues'           => $issues,
            'pressReleases'    => $pressReleases,
            'suggestedTopics'  => $suggestedTopics,
            'trend7'           => $stats['trend7'],
            'metrics'          => $metrics,
        ]);
    }

    /**
     * Catat sinkron manual dari antarmuka ke `rss_sync_logs` agar kesegaran data terlihat di halaman.
     */
    protected function logManualSync(int $poldaId, int $feeds, int $fetched, int $stored): void
    {
        RssSyncLog::create([
            'polda_id'         => $poldaId,
            'sync_type'        => 'manual_ui',
            'status'           => 'success',
            'feeds_processed'  => $feeds,
            'articles_fetched' => $fetched,
            'articles_stored'  => $stored,
            'message'          => 'Sinkron manual dari antarmuka Radar Media Online.',
        ]);
    }

    /**
     * Wilayah penugasan pengguna (Polres) sebagai kata kunci pencarian cepat "Wilayah Saya".
     * Hanya Polres milik Polda pengguna yang dipertimbangkan.
     *
     * @return array{label: string, keyword: string}|null
     */
    protected function myArea(Request $request): ?array
    {
        $user = $request->user();
        if (! $user || ! $user->polres_id || ! $user->polda_id) {
            return null;
        }

        $polres = Polres::where('id', $user->polres_id)->where('polda_id', $user->polda_id)->first();
        if (! $polres) {
            return null;
        }

        $keyword = $polres->placeKeyword();

        return $keyword === null ? null : ['label' => (string) $polres->name, 'keyword' => $keyword];
    }

    /**
     * Jalankan analisis intelijen media otomatis menggunakan Google Gemini Pro.
     */
    public function analyzeGemini(Request $request): RedirectResponse|JsonResponse
    {
        // Tenant aktif dari sesi server (bukan dari query/klien).
        [, $polda] = $this->resolveActiveTenant($request);
        $polda = $polda ?? Polda::first();

        $geminiService = new GeminiService();
        $apiKey = $geminiService->getApiKey($polda->id);

        if (empty($apiKey)) {
            if ($request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'status'  => 'missing_api_key',
                    'message' => 'Kunci API Mesin AI belum diatur. Hubungi Administrator di menu Keamanan & Pemeliharaan.',
                ], 422);
            }
            return back()->with('error', 'Kunci API Mesin AI belum diatur. Hubungi Administrator di menu Keamanan & Pemeliharaan.');
        }

        // 1. Sinkronisasi Berita RSS jika diminta (Pipeline 2-in-1 Seamless)
        $shouldSync = $request->boolean('sync', false) || $request->boolean('sync_first', false);
        $syncedCount = 0;
        if ($shouldSync) {
            $mediaFeeds = MediaFeed::where('polda_id', $polda->id)->orderBy('name')->get();
            if ($mediaFeeds->isNotEmpty()) {
                $rssService = new RssFeedService();
                $liveArticles = $rssService->fetchAggregatedFeeds($mediaFeeds->toArray(), true);
                $bySource = [];
                foreach ($liveArticles as $item) {
                    $srcKey = strtoupper($item['source_name'] ?? 'MEDIA ONLINE');
                    $bySource[$srcKey][] = $item;
                }
                foreach ($bySource as $srcKey => $items) {
                    foreach (array_slice($items, 0, 25) as $item) {
                        MediaArticle::updateOrCreate(
                            ['polda_id' => $polda->id, 'url' => $item['link']],
                            [
                                'polda_id'    => $polda->id,
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
                        $syncedCount++;
                    }
                }
            }
        }

        if ($shouldSync && isset($liveArticles)) {
            $this->logManualSync($polda->id, $mediaFeeds->count(), count($liveArticles), $syncedCount);
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

        // Hemat kuota: bila 30 berita terbaru dan templat prompt sama dengan analisis terakhir, jangan analisis ulang.
        $sourceHash = sha1(json_encode(array_map(fn ($a) => [$a['title'] ?? '', $a['link'] ?? ''], $articles)) . '|' . md5($promptTemplate));
        $quota = app(\App\Services\AiQuota::class);
        $latestReport = MediaIntelligenceReport::where('polda_id', $polda->id)->latest()->first();

        if (!$request->boolean('force') && $latestReport && $latestReport->source_hash === $sourceHash) {
            $quota->log($request->user(), $polda->id, 'media', 'skipped');
            $skipMsg = ($shouldSync ? "Sinkronisasi {$syncedCount} berita selesai. " : '') . 'Tidak ada berita baru sejak analisis terakhir, jadi hasil yang ada dipakai (tanpa memakai kuota AI).';
            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'skipped' => true, 'message' => $skipMsg, 'synced_count' => $syncedCount, 'report' => $latestReport, 'model' => null]);
            }
            return back()->with('success', $skipMsg);
        }

        if ($denied = $quota->denial($request->user(), $polda->id)) {
            if ($request->wantsJson()) {
                return response()->json(['success' => false, 'status' => 'ai_limited', 'message' => $denied], 429);
            }
            return back()->with('error', $denied);
        }

        $result = $geminiService->analyzeMediaArticles($polda, $articles, $promptTemplate, $apiKey, $model);
        $quota->finish($request->user(), $polda->id, 'media', $result);

        $aiSetting = AiSetting::forPolda($polda->id);

        if (!$result['success']) {
            $aiSetting->update([
                'last_status' => 'error',
                'last_error'  => $result['message'] ?? 'Gagal memproses analisis AI.',
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
            'suggested_topics'         => (!empty($parsed['suggested_topics']) && is_array($parsed['suggested_topics'])) ? $parsed['suggested_topics'] : static::getDefaultSuggestedTopics($polda),
            'raw_input'                => $rawText,
            'source_hash'              => $sourceHash,
        ]);

        $aiSetting->update([
            'last_analyzed_at' => now(),
            'last_status'      => 'success',
            'last_error'       => null,
        ]);

        $modelUsed = $result['model_used'] ?? $model;
        $successMsg = $shouldSync
            ? "Sinkronisasi {$syncedCount} berita dan analisis intelijen otomatis berhasil diperbarui!"
            : "Analisis intelijen otomatis berhasil diperbarui!";

        if ($request->wantsJson()) {
            return response()->json([
                'success'      => true,
                'message'      => $successMsg,
                'synced_count' => $syncedCount,
                'report'       => $report,
                'model'        => $modelUsed,
            ]);
        }

        return back()->with('success', $successMsg);
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

        // Tenant aktif dari sesi server (bukan dari query/klien).
        [, $polda] = $this->resolveActiveTenant($request);
        $polda = $polda ?? Polda::first();

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
                'message' => 'Pengaturan mesin AI berhasil disimpan.',
            ]);
        }

        return back()->with('success', 'Pengaturan mesin AI berhasil disimpan.');
    }

    /**
     * Uji koneksi ke Google Gemini API.
     */
    public function testGeminiConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');
        $model = $request->input('model', 'gemini-2.5-pro');

        // Tenant aktif dari sesi server (bukan dari query/klien).
        [, $polda] = $this->resolveActiveTenant($request);
        $polda = $polda ?? Polda::first();

        $geminiService = new GeminiService();
        $apiKey = $apiKey ?: $geminiService->getApiKey($polda->id);

        $result = $geminiService->testConnection($apiKey, $model);

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Resolve effective AI Prompt based on custom template in database or standard fallback.
     */
    public static function resolvePromptTemplate(?Polda $polda): string
    {
        if ($polda) {
            $setting = AiSetting::where('polda_id', $polda->id)->first();
            if (!empty(trim((string) $setting?->custom_prompt_template))) {
                return $setting->custom_prompt_template;
            }
        }

        $global = AiSetting::whereNull('polda_id')->first();
        if (!empty(trim((string) $global?->custom_prompt_template))) {
            return $global->custom_prompt_template;
        }

        return self::getStandardPromptTemplate($polda);
    }

    /**
     * Generate dynamic AI Prompt based on selected Polda (with custom prompt override).
     */
    protected function generateDynamicPrompt(?Polda $polda): string
    {
        return self::resolvePromptTemplate($polda);
    }

    /**
     * Get official standard AI prompt template based on Polda.
     */
    public static function getStandardPromptTemplate(?Polda $polda): string
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

PILAR 3: DETEKSI DINI TOPIK POTENSIAL & TREN MEDIA ONLINE (SUGGESTED_TOPICS - GOOGLE NEWS AI):
Pimpinan mengatensi deteksi tren sebelum isu menjadi krisis viral liar (pre-emptive intelligence). Pindai seluruh sebaran berita online dan tren Google News, lalu rekomendasikan 2-4 isu yang berpotensi melesat menjadi perhatian publik besar dalam 1-2 hari ke depan.
Untuk setiap topik potensial, tentukan:
- title: Judul ringkas dan padat isu potensial yang perlu dipantau.
- priority: "urgent" (sangat mendesak) atau "high" (prioritas tinggi).
- velocity: Estimasi akselerasi sebaran berita (contoh: "+34 Berita / Tren Cepat", "+18 Berita / Tren Baru").
- keywords: Kata kunci pencarian spesifik (#tags) yang relevan untuk crawling Google News (pisahkan koma).
- rationale: Analisis 1-2 kalimat mengapa pimpinan dan jajaran Humas wajib memantau topik ini sebelum eskalasi meluas.

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
  ],
  "suggested_topics": [
    {
      "title": "Judul Isu Potensial / Topik Tren Media Baru 1",
      "priority": "high",
      "velocity": "+15 Berita / Tren Cepat",
      "keywords": "kata_kunci_1, kata_kunci_2, kata_kunci_3",
      "rationale": "Uraian 1-2 kalimat mengapa isu ini berpotensi menjadi tren nasional dan perlu dipantau sebelum eskalasi meluas..."
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

PILAR DETEKSI DINI TOPIK POTENSIAL & TREN MEDIA ONLINE (SUGGESTED_TOPICS - GOOGLE NEWS AI):
Pimpinan mengatensi deteksi tren sebelum isu menjadi krisis viral liar (pre-emptive intelligence). Pindai seluruh sebaran berita online dan tren Google News, lalu rekomendasikan 2-4 isu kewilayahan yang berpotensi melesat menjadi perhatian publik besar dalam 1-2 hari ke depan.
Untuk setiap topik potensial, tentukan:
- title: Judul ringkas dan padat isu potensial kewilayahan.
- priority: "urgent" (sangat mendesak) atau "high" (prioritas tinggi).
- velocity: Estimasi akselerasi sebaran berita (contoh: "+15 Berita / Tren Cepat", "+8 Berita / Tren Baru").
- keywords: Kata kunci pencarian spesifik (#tags) yang relevan untuk crawling Google News (pisahkan koma).
- rationale: Analisis 1-2 kalimat mengapa jajaran Polda wajib memantau topik ini sebelum eskalasi meluas.

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
  ],
  "suggested_topics": [
    {
      "title": "Judul Isu Potensial / Topik Tren Media Baru Kewilayahan",
      "priority": "high",
      "velocity": "+10 Berita / Tren Cepat",
      "keywords": "kata_kunci_1, kata_kunci_2, kata_kunci_3",
      "rationale": "Uraian 1-2 kalimat mengapa isu ini berpotensi menjadi perhatian publik di daerah dan perlu dipantau..."
    }
  ]
}

BERIKUT DATA BERITA / POSTINGAN UNTUK DIANALISIS:
[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]
PROMPT;
    }

    /**
     * Dapatkan daftar sugesti topik potensial default (Google News AI Trend Detection).
     */
    public static function getDefaultSuggestedTopics(?Polda $polda): array
    {
        $isNational = ($polda?->code === 'DIVHUMAS');
        if ($isNational) {
            return [
                [
                    'title'     => 'Pengamanan & Transparansi Distribusi Program Makan Bergizi Gratis (MBG)',
                    'priority'  => 'urgent',
                    'velocity'  => '+34 Berita / Tren Cepat',
                    'keywords'  => 'makan bergizi gratis, mbg, pengamanan pangan, polri presisi',
                    'rationale' => 'Atensi Khusus Pimpinan: Pantau kelancaran distribusi logistik pangan bergizi di sekolah, cegah potensi kecurangan dan pastikan kepatuhan standar higienis.',
                ],
                [
                    'title'     => 'Pemberantasan Sindikat Judol & Pemblokiran Ribuan Rekening Nominee',
                    'priority'  => 'urgent',
                    'velocity'  => '+26 Berita / Tren Cepat',
                    'keywords'  => 'judol, judi online, rekening penampung, bareskrim, cyber crime',
                    'rationale' => 'Tingginya atensi netizen dan media massa terhadap penindakan bandar judi online, penyitaan aset, dan pencegahan TPPU.',
                ],
                [
                    'title'     => 'Cooling System & Tangkal Disinformasi Pilkada Serentak di Ruang Siber',
                    'priority'  => 'high',
                    'velocity'  => '+19 Berita / Tren Cepat',
                    'keywords'  => 'pilkada serentak, cooling system, patroli siber, hoaks politik, netralitas polri',
                    'rationale' => 'Eskalasi percakapan ruang digital jelang agenda nasional; perlukan amplifikasi pesan kamtibmas dan penegakan hukum humanis.',
                ],
                [
                    'title'     => 'Penertiban Balap Liar & Tilang Knalpot Brong Jelang Operasi Kepolisian',
                    'priority'  => 'high',
                    'velocity'  => '+12 Berita / Tren Cepat',
                    'keywords'  => 'knalpot brong, balap liar, razia gabungan, lantas presisi',
                    'rationale' => 'Merespon aduan warganet terkait gangguan kebisingan malam hari dan potensi laka lantas di berbagai satwil.',
                ],
            ];
        }

        $poldaName = $polda?->name ?? 'Polda Sulawesi Barat';
        $short = $polda?->code === 'SULBAR' ? 'Sulbar' : ($polda?->code ?? 'Kewilayahan');

        return [
            [
                'title'     => "Pengawasan Penyaluran MBG & Ketahanan Pangan Wilayah {$short}",
                'priority'  => 'urgent',
                'velocity'  => '+15 Berita / Tren Cepat',
                'keywords'  => "makan bergizi gratis {$short}, distribusi pangan, polres jajaran",
                'rationale' => "Mitigasi dini pengawasan penyaluran menu pangan bergizi di kabupaten/kota jajaran {$poldaName} agar tepat sasaran dan transparan.",
            ],
            [
                'title'     => "Cooling System & Patroli Dialogis Kamtibmas Pilkada {$short}",
                'priority'  => 'urgent',
                'velocity'  => '+11 Berita / Tren Cepat',
                'keywords'  => "cooling system {$short}, bhabinkamtibmas, pilkada damai, patroli siber",
                'rationale' => "Penguatan silaturahmi kamtibmas dengan tokoh masyarakat serta menangkal provokasi isu lokal di media sosial.",
            ],
            [
                'title'     => "Penertiban Balap Liar & Knalpot Brong Jalur Utama {$short}",
                'priority'  => 'high',
                'velocity'  => '+8 Berita / Tren Cepat',
                'keywords'  => "razia knalpot brong {$short}, balap liar, ditlantas, kamtibmas malam",
                'rationale' => "Tindak lanjut cepat atas keresahan warga terhadap kebisingan knalpot brong pada jam istirahat malam.",
            ],
        ];
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
                'suggested_topics'         => static::getDefaultSuggestedTopics($polda),
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
            'suggested_topics'         => static::getDefaultSuggestedTopics($polda),
            'raw_input'                => null,
        ]);
    }

    /**
     * Toggle bookmark artikel (disimpan di database agar tidak ikut dibersihkan).
     */
    public function toggleBookmark(Request $request, int $id): JsonResponse
    {
        $article = MediaArticle::findOrFail($id);
        $this->authorizeTenantRecord($request, $article);
        $article->update(['is_bookmarked' => ! $article->is_bookmarked]);

        return response()->json(['id' => (string) $article->id, 'is_bookmarked' => $article->is_bookmarked]);
    }

    /**
     * Impor bookmark lama dari localStorage browser (sekali jalan).
     */
    public function importBookmarks(Request $request): JsonResponse
    {
        $ids = collect($request->input('ids', []))->filter(fn ($i) => is_numeric($i))->map(fn ($i) => (int) $i)->take(2000);
        $user = $request->user();
        $count = MediaArticle::whereIn('id', $ids)
            // Personel daerah hanya dapat menandai berita Polda-nya sendiri (ID asing diabaikan).
            ->when($user && ! $user->isSuperAdmin() && $user->polda_id, fn ($q) => $q->where('polda_id', $user->polda_id))
            ->update(['is_bookmarked' => true]);

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

        [, $activePolda] = $this->resolveActiveTenant($request);
        $polda = $this->requestedPoldaOverride($request, $activePolda) ?? Polda::where('is_active', true)->first();

        $topic = MediaTopic::create([
            'polda_id'    => $polda->id,
            'title'       => $validated['title'],
            'keywords'    => $validated['keywords'],
            'description' => $validated['description'] ?? null,
            'priority'    => $validated['priority'] ?? 'high',
            'status'      => 'active',
            'created_by'  => auth()->id(),
        ]);

        $crawlNotice = '';
        if ($request->boolean('crawl_live', true)) {
            // Penarikan Google News (jaringan eksternal + puluhan penulisan) dijalankan SETELAH respons terkirim,
            // supaya permintaan tidak menggantung di balik proxy (Cloudflare/LiteSpeed) dan tidak berujung galat 520/524.
            defer(function () use ($topic) {
                try {
                    app(\App\Services\GoogleNewsCrawlerService::class)->crawlAndIngestForTopic($topic, null, 40);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Penarikan Google News untuk topik #' . $topic->id . ' gagal: ' . $e->getMessage());
                }
            });
            $crawlNotice = '. Penarikan berita internet berjalan di latar belakang; muat ulang beberapa detik lagi untuk melihat hasilnya';
        }

        return back()->with('success', "Topik Atensi Pimpinan \"{$validated['title']}\" berhasil dibuat{$crawlNotice}");
    }

    /**
     * Perbarui Topik Atensi Pimpinan.
     */
    public function updateTopic(Request $request, int $id): RedirectResponse
    {
        $topic = MediaTopic::findOrFail($id);
        $this->authorizeTenantRecord($request, $topic);

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
    public function deleteTopic(Request $request, int $id): RedirectResponse
    {
        $topic = MediaTopic::findOrFail($id);
        $this->authorizeTenantRecord($request, $topic);
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
        $topicRecord = MediaTopic::findOrFail($topicId);

        // Artikel dan topik harus milik tenant pengguna DAN satu wilayah yang sama.
        $this->authorizeTenantRecord($request, $article);
        $this->authorizeTenantRecord($request, $topicRecord);
        abort_if((int) $article->polda_id !== (int) $topicRecord->polda_id, 403, 'Artikel dan topik berasal dari wilayah yang berbeda.');

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
        $this->authorizeTenantRecord($request, $topic);
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

        // Total seluruh berita yang cocok di database & hitungan saat ini
        $totalMatchingCount = (clone $articlesQuery)->count();
        $clientCurrentCount = (int) $request->input('current_articles_count', 0);

        $matchingArticles = $articlesQuery->latest('pub_date')->limit(30)->get();

        if ($matchingArticles->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => "Belum ada berita yang sesuai dengan kata kunci Topik \"{$topic->title}\". Tambahkan kata kunci yang lebih luas atau kaitkan berita secara manual.",
            ], 422);
        }

        $effectiveAnalyzedCount = max($totalMatchingCount, $clientCurrentCount, count($matchingArticles));

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
                'message' => 'Kunci API Mesin AI belum diatur di menu Keamanan & Pemeliharaan -> Mesin Intelijen AI.',
            ], 422);
        }

        // Hemat kuota: analisis topik yang sudah ada dipakai ulang bila tidak ada berita baru sejak analisis itu.
        $quota = app(\App\Services\AiQuota::class);
        $newestArticleAt = $matchingArticles->max('created_at');
        if (!$request->boolean('force') && !empty($topic->ai_analysis) && $topic->ai_analyzed_at && $newestArticleAt && $newestArticleAt <= $topic->ai_analyzed_at) {
            $quota->log($request->user(), $polda->id, 'topic', 'skipped');

            return response()->json([
                'success'        => true,
                'skipped'        => true,
                'message'        => 'Belum ada berita baru untuk topik ini sejak analisis terakhir, jadi hasil yang ada dipakai (tanpa memakai kuota AI).',
                'topic'          => [
                    'id' => $topic->id, 'title' => $topic->title, 'priority' => $topic->priority, 'keywords' => $topic->keywords,
                    'description' => $topic->description, 'ai_analysis' => $topic->ai_analysis,
                    'ai_analyzed_at' => $topic->ai_analyzed_at->toISOString(), 'ai_analyzed_at_humans' => $topic->ai_analyzed_at->diffForHumans(),
                    'ai_articles_count' => $topic->ai_articles_count,
                ],
                'analysis'       => $topic->ai_analysis,
                'articles_count' => $topic->ai_articles_count,
                'sample_count'   => count($formattedArticles),
                'model_used'     => null,
                'latency_ms'     => 0,
            ]);
        }

        if ($denied = $quota->denial($request->user(), $polda->id)) {
            return response()->json(['success' => false, 'status' => 'ai_limited', 'message' => $denied], 429);
        }

        $result = $geminiService->analyzeTopicDossier($polda, $topic, $formattedArticles, $apiKey, $model);
        $quota->finish($request->user(), $polda->id, 'topic', $result);

        if (!$result['success']) {
            return response()->json($result, 500);
        }

        // Pastikan naskah WhatsApp diperkaya dan berstruktur resmi kedinasan Polri
        $result['data']['whatsapp_script'] = GeminiService::buildRichWhatsAppScript($result['data'], $polda, $topic);

        // Simpan hasil analisis AI secara permanen ke basis data (Smart Caching)
        $topic->update([
            'ai_analysis'       => $result['data'],
            'ai_analyzed_at'    => now(),
            'ai_articles_count' => $effectiveAnalyzedCount,
        ]);

        return response()->json([
            'success'               => true,
            'topic'                 => [
                'id'                    => $topic->id,
                'title'                 => $topic->title,
                'priority'              => $topic->priority,
                'keywords'              => $topic->keywords,
                'description'           => $topic->description,
                'ai_analysis'           => $result['data'],
                'ai_analyzed_at'        => now()->toISOString(),
                'ai_analyzed_at_humans' => now()->diffForHumans(),
                'ai_articles_count'     => $effectiveAnalyzedCount,
            ],
            'analysis'              => $result['data'],
            'articles_count'        => $effectiveAnalyzedCount,
            'sample_count'          => count($formattedArticles),
            'model_used'            => $result['model_used'] ?? $model,
            'latency_ms'            => $result['latency_ms'] ?? 0,
        ]);
    }

    /**
     * Tarik sebaran berita secara on-demand dari internet (Google News) untuk Topik Atensi tertentu.
     */
    public function crawlTopicLive(Request $request, int $id): JsonResponse
    {
        $topic = MediaTopic::findOrFail($id);
        $this->authorizeTenantRecord($request, $topic);
        $customQuery = $request->input('query');
        $depth = (int) $request->input('depth', 50);

        $crawler = new \App\Services\GoogleNewsCrawlerService();
        $result = $crawler->crawlAndIngestForTopic($topic, $customQuery, $depth);

        // Hitung ulang total berita topik di database
        $keywords = $topic->getKeywordsArray();
        $manualArticleIds = DB::table('media_article_topic')
            ->where('media_topic_id', $topic->id)
            ->pluck('media_article_id')
            ->toArray();

        $articlesQuery = MediaArticle::where('polda_id', $topic->polda_id);
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
        $totalArticlesCount = $articlesQuery->count();

        return response()->json([
            'success'              => $result['success'],
            'message'              => $result['message'],
            'count'                => $result['count'],
            'total_topic_articles' => $totalArticlesCount,
            'query'                => $result['query'] ?? '',
            'articles'             => $result['articles'] ?? [],
        ], $result['success'] ? 200 : 500);
    }

    /**
     * Adopsi sugesti topik potensial dari AI Google News menjadi Topik Atensi aktif (1-Klik).
     */
    public function adoptSuggestedTopic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'keywords'    => 'required|string|max:500',
            'description' => 'nullable|string|max:1000',
            'priority'    => 'nullable|string|in:urgent,high',
            'crawl_live'  => 'nullable|boolean',
            'polda_id'    => 'nullable|integer',
            'polda_code'  => 'nullable|string',
        ]);

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $this->requestedPoldaOverride($request, $selectedPolda);
        $selectedPolda = $selectedPolda ?? Polda::first();

        // 1. Buat Topik Atensi Baru
        $topic = MediaTopic::create([
            'polda_id'    => $selectedPolda->id,
            'title'       => trim($validated['title']),
            'keywords'    => trim($validated['keywords']),
            'description' => $validated['description'] ?? 'Isu tren media online terdeteksi otomatis oleh AI Google News.',
            'priority'    => $validated['priority'] ?? 'high',
            'status'      => 'active',
            'created_by'  => auth()->id(),
        ]);

        // 2. Tarik Google News secara instan jika crawl_live diaktifkan
        $crawledCount = 0;
        $crawledArticles = [];
        if ($request->boolean('crawl_live', true)) {
            $crawler = new \App\Services\GoogleNewsCrawlerService();
            $crawlResult = $crawler->crawlAndIngestForTopic($topic, null, 40);
            $crawledCount = $crawlResult['count'] ?? 0;
            $crawledArticles = $crawlResult['articles'] ?? [];
        }

        // 3. Hubungkan juga artikel lokal di basis data yang sesuai dengan kata kunci topik
        $keywords = $topic->getKeywordsArray();
        $cleanedKeywords = [];
        foreach ($keywords as $kw) {
            $clean = preg_replace('/[#]/', '', $kw);
            $clean = trim($clean, " \t\n\r\0\x0B.#,;");
            if (mb_strlen($clean) >= 3) {
                $cleanedKeywords[] = $clean;
            }
        }

        if (!empty($cleanedKeywords)) {
            $existingMatches = MediaArticle::where('polda_id', $selectedPolda->id)
                ->where(function ($q) use ($cleanedKeywords, $topic) {
                    foreach ($cleanedKeywords as $kw) {
                        $q->orWhere('title', 'like', "%{$kw}%")
                          ->orWhere('summary', 'like', "%{$kw}%");
                    }
                    $q->orWhere('title', 'like', "%{$topic->title}%");
                })
                ->latest('pub_date')
                ->take(40)
                ->get();

            foreach ($existingMatches as $em) {
                DB::table('media_article_topic')->updateOrInsert(
                    [
                        'media_article_id' => $em->id,
                        'media_topic_id'   => $topic->id,
                    ],
                    [
                        'is_manual'        => true,
                        'created_at'       => now(),
                        'updated_at'       => now(),
                    ]
                );

                $already = false;
                foreach ($crawledArticles as $ca) {
                    if ((string) $ca['id'] === (string) $em->id || $ca['link'] === $em->url) {
                        $already = true;
                        break;
                    }
                }
                if (!$already) {
                    $crawledArticles[] = [
                        'id'                => (string) $em->id,
                        'title'             => $em->title,
                        'source_name'       => strtoupper($em->source_name),
                        'category'          => $em->category,
                        'category_badge'    => strtoupper($em->category),
                        'time_ago'          => $em->created_at ? $em->created_at->diffForHumans() : 'Baru saja',
                        'pub_date'          => $em->pub_date ? $em->pub_date->format('d M Y, H.i') . ' WITA' : now()->format('d M Y, H.i') . ' WITA',
                        'summary'           => $em->summary ?: 'Cuplikan berita pantauan intelijen media siber.',
                        'image_url'         => (empty($em->image_url) || str_contains($em->image_url, 'unsplash.com')) ? null : $em->image_url,
                        'link'              => $em->url,
                        'sentiment'         => $em->sentiment ?: 'netral',
                        'is_bookmarked'     => (bool) $em->is_bookmarked,
                        'matched_topic_ids' => [$topic->id],
                        'matched_topics'    => [
                            [
                                'id'       => $topic->id,
                                'title'    => $topic->title,
                                'priority' => $topic->priority,
                            ]
                        ],
                        'manual_topic_ids'  => [$topic->id],
                    ];
                }
            }
        }

        // 4. Hapus topik ini dari daftar sugesti laporan aktif
        $latestReport = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)->latest()->first();
        $remainingSuggestions = [];
        if ($latestReport && is_array($latestReport->suggested_topics)) {
            $remainingSuggestions = array_values(array_filter($latestReport->suggested_topics, function ($st) use ($topic) {
                return strtolower(trim($st['title'] ?? '')) !== strtolower(trim($topic->title));
            }));
            $latestReport->update(['suggested_topics' => $remainingSuggestions]);
        }

        $totalConnectedCount = max(count($crawledArticles), 1);

        return response()->json([
            'success'              => true,
            'message'              => "✓ Topik '{$topic->title}' berhasil diadopsi sebagai Topik Atensi! {$totalConnectedCount} berita berhasil disinkronkan.",
            'topic'                => array_merge($topic->toArray(), [
                'articles_count' => $totalConnectedCount,
                'keywords_array' => $topic->getKeywordsArray(),
            ]),
            'crawled_count'        => $totalConnectedCount,
            'articles'             => $crawledArticles,
            'remaining_suggestions'=> $remainingSuggestions,
        ]);
    }

    /**
     * Abaikan (dismiss) sugesti topik potensial.
     */
    public function dismissSuggestedTopic(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title'      => 'required|string',
            'polda_id'   => 'nullable|integer',
            'polda_code' => 'nullable|string',
        ]);

        $title = trim($validated['title']);

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $this->requestedPoldaOverride($request, $selectedPolda);
        $selectedPolda = $selectedPolda ?? Polda::first();

        $latestReport = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)->latest()->first();
        $remainingSuggestions = [];
        if ($latestReport && is_array($latestReport->suggested_topics)) {
            $remainingSuggestions = array_values(array_filter($latestReport->suggested_topics, function ($st) use ($title) {
                return strtolower(trim($st['title'] ?? '')) !== strtolower(trim($title));
            }));
            $latestReport->update(['suggested_topics' => $remainingSuggestions]);
        }

        return response()->json([
            'success'              => true,
            'message'              => 'Sugesti topik berhasil diabaikan.',
            'remaining_suggestions'=> $remainingSuggestions,
        ]);
    }
}
