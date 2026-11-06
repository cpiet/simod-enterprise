<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\MediaArticle;
use App\Models\MediaIntelligenceReport;
use App\Models\Polda;
use App\Models\SocialSetting;
use App\Services\ApifySyncService;
use App\Services\GeminiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AiSettingController extends Controller
{
    /**
     * Tampilkan halaman pusat konfigurasi Mesin Intelijen AI & LLM (Admin Only).
     */
    public function index(Request $request): Response
    {
        $allPoldas = Polda::where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $selectedPolda ?? Polda::first();

        $geminiService = new GeminiService();
        $aiSetting = AiSetting::forPolda($selectedPolda->id);
        $globalSetting = AiSetting::whereNull('polda_id')->first();

        // Effective values & Key Pool
        $allKeys = $aiSetting->getCleanKeysList();
        if (empty($allKeys) && $globalSetting) {
            $allKeys = $globalSetting->getCleanKeysList();
        }
        if (empty($allKeys)) {
            $allKeys = $geminiService->getAllApiKeys($selectedPolda->id);
        }

        $rawApiKey = !empty($allKeys) ? $allKeys[0] : null;
        $maskedKey = $geminiService->maskApiKey($rawApiKey);
        $activeModel = $aiSetting->gemini_model ?: $geminiService->getModel($selectedPolda->id);

        $keysDetail = [];
        foreach ($allKeys as $idx => $k) {
            $keysDetail[] = [
                'index'      => $idx + 1,
                'raw'        => $k,
                'masked'     => $geminiService->maskApiKey($k),
                'is_primary' => $idx === 0,
            ];
        }

        // Apify Social Media Scraper Settings
        $socialSetting = SocialSetting::where('polda_id', $selectedPolda->id)->first()
            ?: SocialSetting::whereNull('polda_id')->first()
            ?: new SocialSetting();

        $apifyRaw = $socialSetting->apify_api_token ?: config('services.apify.token', env('APIFY_API_TOKEN', ''));
        $apifyService = new ApifySyncService();
        $apifyTokens = $apifyService->parseTokens($apifyRaw);

        $socialSettingData = [
            'has_token'         => !empty($apifyTokens),
            'apify_api_token'   => $apifyRaw,
            'total_tokens'      => count($apifyTokens),
            'masked_tokens'     => array_map(fn($t) => $apifyService->maskToken($t), $apifyTokens),
            'keywords_tracked'  => $socialSetting->keywords_tracked ?: 'Polri, Humas Polri, Polisi Presisi, Dumas Presisi',
            'accounts_tracked'  => $socialSetting->accounts_tracked ?: '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi',
            'sync_frequency'    => $socialSetting->sync_frequency ?: 'every_3_hours',
            'auto_sync_enabled' => (bool) ($socialSetting->auto_sync_enabled ?? true),
            'last_synced_at'    => $socialSetting->last_synced_at ? $socialSetting->last_synced_at->diffForHumans() : 'Belum pernah sinkron',
        ];

        // Stats & Telemetry
        $totalReports = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)->count();
        $latestReport = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)->latest()->first();
        $totalArticles = MediaArticle::where('polda_id', $selectedPolda->id)->count();

        // Prompt Template resolution (Custom vs Standard)
        $defaultPrompt = MediaIntelligenceController::getStandardPromptTemplate($selectedPolda);
        $customPrompt = $aiSetting->custom_prompt_template;
        $effectivePrompt = !empty(trim((string) $customPrompt)) ? $customPrompt : $defaultPrompt;
        $hasCustomPrompt = !empty(trim((string) $customPrompt));

        $recentReports = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)
            ->latest()
            ->limit(6)
            ->get(['id', 'report_date', 'report_time', 'title', 'snapshot_national_status', 'created_at']);

        return Inertia::render('Settings/Ai', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'setting'        => [
                'has_api_key'               => !empty($rawApiKey),
                'masked_api_key'            => $maskedKey,
                'raw_api_keys_text'         => implode("\n", $allKeys),
                'api_keys_pool'             => $allKeys,
                'keys_detail'               => $keysDetail,
                'total_keys'                => count($allKeys),
                'gemini_model'              => $activeModel,
                'daily_user_limit'          => (int) ($aiSetting->daily_user_limit ?? \App\Services\AiQuota::DEFAULT_LIMIT),
                'web_search_enabled'        => (bool) ($aiSetting->web_search_enabled ?? true),
                'temperature'               => (float) ($aiSetting->temperature ?? 0.20),
                'custom_prompt_template'    => $customPrompt,
                'default_prompt_template'   => $defaultPrompt,
                'effective_prompt_template' => $effectivePrompt,
                'has_custom_prompt'         => $hasCustomPrompt,
                'last_analyzed_at'          => $aiSetting->last_analyzed_at ? $aiSetting->last_analyzed_at->diffForHumans() : null,
                'last_status'               => $aiSetting->last_status,
                'last_error'                => $aiSetting->last_error,
            ],
            'socialSetting'  => $socialSettingData,
            'globalSetting'  => [
                'has_api_key'     => !empty($globalSetting?->gemini_api_key),
                'masked_api_key'  => $geminiService->maskApiKey($globalSetting?->gemini_api_key),
                'gemini_model'    => $globalSetting?->gemini_model ?? 'gemini-3.5-flash-lite',
                'is_auto_analyze' => (bool) ($globalSetting?->is_auto_analyze ?? false),
            ],
            'telemetry'      => [
                'total_reports'      => $totalReports,
                'total_articles'     => $totalArticles,
                'latest_brief_time'  => $latestReport?->report_time,
                'server_env_has_key' => !empty(config('services.gemini.api_key') ?: env('GEMINI_API_KEY')),
            ],
            'recentReports'  => $recentReports,
            'usage'          => app(\App\Services\AiQuota::class)->todaySummary($poldaId ?? null),
        ]);
    }

    /**
     * Update AI settings with multi-key pool and schedules (Admin Only).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gemini_api_key'          => 'nullable|string',
            'api_keys_pool'           => 'nullable',
            'gemini_model'            => 'required|string|in:gemini-3.5-flash-lite,gemini-flash-lite-latest,gemini-3.8-flash,gemini-3.1-pro-preview,gemini-2.5-pro,gemini-1.5-pro,gemini-2.5-flash,gemini-1.5-flash',
            'daily_user_limit'        => 'nullable|integer|min:0|max:1000',
            'web_search_enabled'      => 'nullable|boolean',
            'temperature'             => 'nullable|numeric|min:0.0|max:1.0',
            'custom_prompt_template'  => 'nullable|string',
            'reset_prompt_to_default' => 'nullable|boolean',
            'apply_to_all_poldas'     => 'nullable|boolean',
        ]);

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $selectedPolda ?? Polda::first();

        $geminiService = new GeminiService();
        $aiSetting = AiSetting::forPolda($selectedPolda->id);

        // Parse keys from gemini_api_key input (textarea or string) or api_keys_pool
        $inputKeysRaw = $validated['gemini_api_key'] ?? '';
        if (empty(trim($inputKeysRaw)) && !empty($request->input('raw_api_keys_text'))) {
            $inputKeysRaw = $request->input('raw_api_keys_text');
        }

        $parsedKeys = $geminiService->parseApiKeys($inputKeysRaw);

        $updateData = [
            'gemini_model'     => $validated['gemini_model'],
            'daily_user_limit' => (int) ($validated['daily_user_limit'] ?? \App\Services\AiQuota::DEFAULT_LIMIT),
            'temperature'      => (float) ($validated['temperature'] ?? 0.20),
            'web_search_enabled' => $request->has('web_search_enabled') ? $request->boolean('web_search_enabled') : (bool) ($aiSetting->web_search_enabled ?? true),
        ];

        // Custom Prompt Template Handling
        if ($request->boolean('reset_prompt_to_default')) {
            $updateData['custom_prompt_template'] = null;
        } elseif ($request->has('custom_prompt_template')) {
            $val = trim((string) $request->input('custom_prompt_template'));
            $updateData['custom_prompt_template'] = !empty($val) ? $val : null;
        }

        // Only update API keys if user submitted non-empty keys, or keep existing ones
        if (!empty($parsedKeys)) {
            $updateData['api_keys_pool'] = $parsedKeys;
            $updateData['gemini_api_key'] = $parsedKeys[0];
        }

        // Apply setting to active Polda
        $aiSetting->update($updateData);

        // Also update global master fallback and all Poldas if requested
        if ($request->boolean('apply_to_all_poldas')) {
            AiSetting::updateOrCreate(
                ['polda_id' => null],
                $updateData
            );

            AiSetting::query()->update($updateData);
        }

        // Kunci/model baru harus langsung dicoba: hapus penahanan kuota akibat kunci lama.
        app(\App\Services\AiQuota::class)->clearBlock();

        $keysCount = !empty($updateData['api_keys_pool']) ? count($updateData['api_keys_pool']) : count($aiSetting->getCleanKeysList());

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'UPDATE_AI_SETTINGS',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => json_encode([
                'polda_id'            => $selectedPolda->id,
                'polda_code'          => $selectedPolda->code,
                'gemini_model'        => $validated['gemini_model'],
                'daily_user_limit'    => $updateData['daily_user_limit'],
                'web_search_enabled'  => $updateData['web_search_enabled'],
                'keys_count'          => $keysCount,
                'has_custom_prompt'   => !empty($updateData['custom_prompt_template']),
                'apply_to_all_poldas' => (bool) ($validated['apply_to_all_poldas'] ?? false),
            ]),
        ]);

        return back()->with('success', "✓ Konfigurasi Mesin Intelijen AI, {$keysCount} Kunci Pool & Jadwal Otomasi Berhasil Disimpan!");
    }

    /**
     * Reset custom AI prompt template back to default standard prompt (Admin Only).
     */
    public function resetPrompt(Request $request): RedirectResponse
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $selectedPolda ?? Polda::first();

        $aiSetting = AiSetting::forPolda($selectedPolda->id);
        $aiSetting->update(['custom_prompt_template' => null]);

        if ($request->boolean('apply_to_all_poldas')) {
            AiSetting::query()->update(['custom_prompt_template' => null]);
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'RESET_AI_PROMPT_TEMPLATE',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => json_encode([
                'polda_id'   => $selectedPolda->id,
                'polda_code' => $selectedPolda->code,
                'message'    => 'Template prompt AI dikembalikan ke standar resmi Divhumas/Polda.',
            ]),
        ]);

        return back()->with('success', '✓ Template Prompt Intelijen AI berhasil direset ke standar resmi Divhumas Polri.');
    }

    /** Cabut penahanan permintaan AI secara manual (admin), dicatat di Audit Log. */
    public function clearBlock(Request $request)
    {
        app(\App\Services\AiQuota::class)->clearBlock();

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'ai.block_cleared',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => json_encode(['by' => 'manual']),
        ]);

        return back()->with('success', 'Penahanan permintaan AI dicabut. Permintaan berikutnya langsung dicoba.');
    }

    /**
     * Test connection to Google Gemini API (Single Key or Multi-Key Pool).
     */
    public function testConnection(Request $request): JsonResponse
    {
        $rawApiKey = $request->input('api_key') ?: $request->input('raw_api_keys_text');
        $model = $request->input('model', 'gemini-3.5-flash-lite');

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $selectedPolda ?? Polda::first();

        $geminiService = new GeminiService();

        // Extract tokens
        $tokens = $geminiService->parseApiKeys($rawApiKey);
        if (empty($tokens)) {
            $tokens = $geminiService->getAllApiKeys($selectedPolda->id);
        }

        if (empty($tokens)) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada Gemini API Key yang ditemukan untuk diuji.',
            ], 422);
        }

        // If multiple keys or test_pool requested, run full pool test
        if (count($tokens) > 1 || $request->boolean('test_pool')) {
            $poolResult = $geminiService->testPoolKeys($tokens, $model);
            if ($poolResult['success'] ?? false) {
                app(\App\Services\AiQuota::class)->clearBlock();
            }
            return response()->json($poolResult, $poolResult['success'] ? 200 : 400);
        }

        // Single key test
        $singleKey = $tokens[0];
        $result = $geminiService->testConnection($singleKey, $model);

        if ($result['success'] ?? false) {
            app(\App\Services\AiQuota::class)->clearBlock();
        }

        // Attach accounts structure so frontend can display nicely
        $result['accounts'] = [
            [
                'index'        => 1,
                'token_masked' => $geminiService->maskApiKey($singleKey),
                'status'       => $result['success'] ? 'connected' : 'error',
                'latency_ms'   => $result['latency_ms'] ?? null,
                'model'        => $result['model'] ?? $model,
                'message'      => $result['message'] ?? '',
                'is_primary'   => true,
            ]
        ];

        return response()->json($result, $result['success'] ? 200 : 400);
    }

    /**
     * Update Apify Scraper configuration & Social Listening parameters (Admin Only).
     */
    public function updateApify(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'apify_api_token'     => 'nullable|string',
            'keywords_tracked'    => 'nullable|string',
            'accounts_tracked'    => 'nullable|string',
            'sync_frequency'      => 'nullable|string',
            'auto_sync_enabled'   => 'nullable|boolean',
            'apply_to_all_poldas' => 'nullable|boolean',
        ]);

        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $selectedPolda = $selectedPolda ?? Polda::first();

        $updateData = [
            'apify_api_token'   => $validated['apify_api_token'] ?? '',
            'keywords_tracked'  => $validated['keywords_tracked'] ?? 'Polri, Humas Polri, Polisi Presisi, Dumas Presisi',
            'accounts_tracked'  => $validated['accounts_tracked'] ?? '@divisihumaspri, @humaspolri, @polripresisi, @halo_polisi',
            'sync_frequency'    => $validated['sync_frequency'] ?? 'every_3_hours',
            'auto_sync_enabled' => (bool) ($validated['auto_sync_enabled'] ?? true),
        ];

        SocialSetting::updateOrCreate(
            ['polda_id' => $selectedPolda->id],
            $updateData
        );

        if ($request->boolean('apply_to_all_poldas')) {
            SocialSetting::query()->update($updateData);
            foreach (Polda::all() as $p) {
                SocialSetting::updateOrCreate(['polda_id' => $p->id], $updateData);
            }
        }

        $apifyService = new ApifySyncService();
        $tokensCount = count($apifyService->parseTokens($updateData['apify_api_token']));

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'UPDATE_APIFY_SETTINGS',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => json_encode([
                'polda_id'            => $selectedPolda->id,
                'polda_code'          => $selectedPolda->code,
                'tokens_count'        => $tokensCount,
                'sync_frequency'      => $updateData['sync_frequency'],
                'auto_sync_enabled'   => $updateData['auto_sync_enabled'],
                'apply_to_all_poldas' => (bool) ($validated['apply_to_all_poldas'] ?? false),
            ]),
        ]);

        return back()->with('success', "✓ Konfigurasi Mesin Scraper Apify ({$tokensCount} Akun) & Parameter Patroli Medsos Berhasil Disimpan!");
    }

    /**
     * Test connection to Apify Scraper API using single or multi-tokens pool.
     */
    public function testApify(Request $request, ApifySyncService $service): JsonResponse
    {
        $token = $request->input('apify_api_token');
        $result = $service->testConnection($token);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
