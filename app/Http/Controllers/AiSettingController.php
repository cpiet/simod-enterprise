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

        // Effective values
        $rawApiKey = $geminiService->getApiKey($selectedPolda->id);
        $maskedKey = $geminiService->maskApiKey($rawApiKey);
        $activeModel = $geminiService->getModel($selectedPolda->id);

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
                'has_api_key'      => !empty($rawApiKey),
                'masked_api_key'   => $maskedKey,
                'gemini_model'     => $aiSetting->gemini_model ?: $activeModel,
                'is_auto_analyze'  => (bool) $aiSetting->is_auto_analyze,
                'temperature'      => (float) ($aiSetting->temperature ?? 0.20),
                'last_analyzed_at' => $aiSetting->last_analyzed_at ? $aiSetting->last_analyzed_at->diffForHumans() : null,
                'last_status'      => $aiSetting->last_status,
                'last_error'       => $aiSetting->last_error,
            ],
            'globalSetting'  => [
                'has_api_key'     => !empty($globalSetting?->gemini_api_key),
                'masked_api_key'  => $geminiService->maskApiKey($globalSetting?->gemini_api_key),
                'gemini_model'    => $globalSetting?->gemini_model ?? 'gemini-2.5-pro',
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
     * Update AI settings (Admin Only).
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'gemini_api_key'      => 'nullable|string',
            'gemini_model'        => 'required|string|in:gemini-3.5-flash-lite,gemini-flash-lite-latest,gemini-3.8-flash,gemini-3.1-pro-preview,gemini-2.5-pro,gemini-1.5-pro,gemini-2.5-flash,gemini-1.5-flash',
            'is_auto_analyze'     => 'nullable|boolean',
            'temperature'         => 'nullable|numeric|min:0.0|max:1.0',
            'apply_to_all_poldas' => 'nullable|boolean',
        ]);

        $poldaCode = $request->query('polda', 'ALL');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $selectedPolda = Polda::where('code', $searchCode)->first() ?? Polda::first();

        $updateData = [
            'gemini_model'    => $validated['gemini_model'],
            'is_auto_analyze' => (bool) ($validated['is_auto_analyze'] ?? false),
            'temperature'     => (float) ($validated['temperature'] ?? 0.20),
        ];

        if (array_key_exists('gemini_api_key', $validated) && !empty(trim((string) $validated['gemini_api_key']))) {
            $updateData['gemini_api_key'] = trim($validated['gemini_api_key']);
        }

        // Apply setting to active Polda
        $aiSetting = AiSetting::forPolda($selectedPolda->id);
        $aiSetting->update($updateData);

        // Also update global master fallback and all Poldas if requested
        if ($request->boolean('apply_to_all_poldas')) {
            AiSetting::updateOrCreate(
                ['polda_id' => null],
                $updateData
            );

            AiSetting::query()->update($updateData);
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'UPDATE_AI_SETTINGS',
            'ip_address' => $request->ip() ?: '127.0.0.1',
            'details'    => json_encode([
                'polda_id'            => $selectedPolda->id,
                'polda_code'          => $selectedPolda->code,
                'gemini_model'        => $validated['gemini_model'],
                'is_auto_analyze'     => $updateData['is_auto_analyze'],
                'apply_to_all_poldas' => (bool) ($validated['apply_to_all_poldas'] ?? false),
            ]),
        ]);

        return back()->with('success', 'Konfigurasi Mesin Intelijen AI & Google Gemini Pro berhasil disimpan.');
    }

    /**
     * Test connection to Google Gemini API (Admin Only).
     */
    public function testConnection(Request $request): JsonResponse
    {
        $apiKey = $request->input('api_key');
        $model = $request->input('model', 'gemini-2.5-pro');

        $poldaCode = $request->query('polda', 'ALL');
        $searchCode = strtoupper($poldaCode) === 'ALL' ? 'DIVHUMAS' : strtoupper($poldaCode);
        $selectedPolda = Polda::where('code', $searchCode)->first() ?? Polda::first();

        $geminiService = new GeminiService();
        $apiKey = $apiKey ?: $geminiService->getApiKey($selectedPolda->id);

        $result = $geminiService->testConnection($apiKey, $model);

        return response()->json($result, $result['success'] ? 200 : 400);
    }
}

