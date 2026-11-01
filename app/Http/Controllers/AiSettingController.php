<?php

namespace App\Http\Controllers;

use App\Models\AiSetting;
use App\Models\AuditLog;
use App\Models\MediaArticle;
use App\Models\MediaIntelligenceReport;
use App\Models\Polda;
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
        $selectedCode = $request->query('polda', 'ALL');
        $searchCode = strtoupper($selectedCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($selectedCode);
        $selectedPolda = Polda::where('code', $searchCode)->first() ?? Polda::first();

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

        // Stats & Telemetry
        $totalReports = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)->count();
        $latestReport = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)->latest()->first();
        $totalArticles = MediaArticle::where('polda_id', $selectedPolda->id)->count();

        $recentReports = MediaIntelligenceReport::where('polda_id', $selectedPolda->id)
            ->latest()
            ->limit(6)
            ->get(['id', 'report_date', 'report_time', 'title', 'snapshot_national_status', 'created_at']);

        return Inertia::render('Settings/Ai', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'setting'        => [
                'has_api_key'       => !empty($rawApiKey),
                'masked_api_key'    => $maskedKey,
                'raw_api_keys_text' => implode("\n", $allKeys),
                'api_keys_pool'     => $allKeys,
                'keys_detail'       => $keysDetail,
                'total_keys'        => count($allKeys),
                'gemini_model'      => $activeModel,
                'is_auto_analyze'   => (bool) ($aiSetting->is_auto_analyze ?? true),
                'sync_mode'         => $aiSetting->sync_mode ?: 'after_rss',
                'interval_minutes'  => (int) ($aiSetting->interval_minutes ?: 180),
                'scheduled_times'   => is_array($aiSetting->scheduled_times) && !empty($aiSetting->scheduled_times)
                    ? $aiSetting->scheduled_times
                    : ['06:00', '12:00', '18:00', '22:00'],
                'temperature'       => (float) ($aiSetting->temperature ?? 0.20),
                'last_analyzed_at'  => $aiSetting->last_analyzed_at ? $aiSetting->last_analyzed_at->diffForHumans() : null,
                'last_status'       => $aiSetting->last_status,
                'last_error'        => $aiSetting->last_error,
            ],
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
        ]);
    }

    /**
     * Update AI settings with multi-key pool and schedules (Admin Only).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gemini_api_key'      => 'nullable|string',
            'api_keys_pool'       => 'nullable',
            'gemini_model'        => 'required|string|in:gemini-3.5-flash-lite,gemini-flash-lite-latest,gemini-3.8-flash,gemini-3.1-pro-preview,gemini-2.5-pro,gemini-1.5-pro,gemini-2.5-flash,gemini-1.5-flash',
            'is_auto_analyze'     => 'nullable|boolean',
            'sync_mode'           => 'nullable|string|in:manual,after_rss,interval,scheduled_times',
            'interval_minutes'    => 'nullable|integer|min:15|max:1440',
            'scheduled_times'     => 'nullable|array',
            'temperature'         => 'nullable|numeric|min:0.0|max:1.0',
            'apply_to_all_poldas' => 'nullable|boolean',
        ]);

        $poldaCode = $request->query('polda', 'ALL');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $selectedPolda = Polda::where('code', $searchCode)->first() ?? Polda::first();

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
            'is_auto_analyze'  => (bool) ($validated['is_auto_analyze'] ?? true),
            'sync_mode'        => $validated['sync_mode'] ?? 'after_rss',
            'interval_minutes' => (int) ($validated['interval_minutes'] ?? 180),
            'scheduled_times'  => is_array($validated['scheduled_times'] ?? null)
                ? array_values(array_unique(array_filter($validated['scheduled_times'])))
                : ['06:00', '12:00', '18:00', '22:00'],
            'temperature'      => (float) ($validated['temperature'] ?? 0.20),
        ];

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

        $keysCount = !empty($updateData['api_keys_pool']) ? count($updateData['api_keys_pool']) : count($aiSetting->getCleanKeysList());

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'UPDATE_AI_SETTINGS',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => json_encode([
                'polda_id'            => $selectedPolda->id,
                'polda_code'          => $selectedPolda->code,
                'gemini_model'        => $validated['gemini_model'],
                'sync_mode'           => $updateData['sync_mode'],
                'interval_minutes'    => $updateData['interval_minutes'],
                'keys_count'          => $keysCount,
                'is_auto_analyze'     => $updateData['is_auto_analyze'],
                'apply_to_all_poldas' => (bool) ($validated['apply_to_all_poldas'] ?? false),
            ]),
        ]);

        return back()->with('success', "✓ Konfigurasi Mesin Intelijen AI, {$keysCount} Kunci Pool & Jadwal Otomasi Berhasil Disimpan!");
    }

    /**
     * Test connection to Google Gemini API (Single Key or Multi-Key Pool).
     */
    public function testConnection(Request $request): JsonResponse
    {
        $rawApiKey = $request->input('api_key') ?: $request->input('raw_api_keys_text');
        $model = $request->input('model', 'gemini-3.5-flash-lite');

        $poldaCode = $request->query('polda', 'ALL');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $selectedPolda = Polda::where('code', $searchCode)->first() ?? Polda::first();

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
            return response()->json($poolResult, $poolResult['success'] ? 200 : 400);
        }

        // Single key test
        $singleKey = $tokens[0];
        $result = $geminiService->testConnection($singleKey, $model);

        // Attach accounts structure so frontend can display nicely
        $result['accounts'] = [
            [
                'index'        => 1,
                'token_masked' => $geminiService->maskApiKey($singleKey),
                'key_raw'      => $singleKey,
                'status'       => $result['success'] ? 'connected' : 'error',
                'latency_ms'   => $result['latency_ms'] ?? null,
                'model'        => $result['model'] ?? $model,
                'message'      => $result['message'] ?? '',
                'is_primary'   => true,
            ]
        ];

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}
