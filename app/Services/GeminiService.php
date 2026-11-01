<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Polda;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Resolve Gemini API Key for specific Polda or Global fallback.
     */
    public function getApiKey(?int $poldaId = null): ?string
    {
        $key = null;

        if ($poldaId) {
            $key = AiSetting::where('polda_id', $poldaId)->value('gemini_api_key');
        }

        if (empty($key)) {
            $key = AiSetting::whereNull('polda_id')->value('gemini_api_key');
        }

        if (empty($key)) {
            $key = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
        }

        return !empty(trim((string) $key)) ? trim((string) $key) : null;
    }

    /**
     * Resolve Gemini Model for specific Polda or Global fallback.
     */
    public function getModel(?int $poldaId = null): string
    {
        $model = null;

        if ($poldaId) {
            $model = AiSetting::where('polda_id', $poldaId)->value('gemini_model');
        }

        if (empty($model)) {
            $model = AiSetting::whereNull('polda_id')->value('gemini_model');
        }

        if (empty($model)) {
            $model = config('services.gemini.model') ?: env('GEMINI_MODEL');
        }

        return !empty(trim((string) $model)) ? trim((string) $model) : 'gemini-3.5-flash-lite';
    }

    /**
     * Mask API Key for safe UI display.
     */
    public function maskApiKey(?string $key): string
    {
        if (empty($key)) {
            return '';
        }

        if (strlen($key) <= 12) {
            return $key;
        }

        return substr($key, 0, 7) . '...' . substr($key, -4);
    }

    /**
     * Test connection to Google Gemini API.
     */
    public function testConnection(?string $apiKey = null, ?string $model = null): array
    {
        $model = $model ?: 'gemini-3.5-flash-lite';
        $apiKey = trim((string) $apiKey);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'message' => 'Gemini API Key tidak boleh kosong.',
            ];
        }

        $modelsToTry = array_unique([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-3.8-flash',
            'gemini-3.1-pro-preview',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
            'gemini-2.5-flash',
            'gemini-1.5-flash',
        ]);
        $start = microtime(true);
        $lastError = 'Gagal menghubungi Google Gemini API';

        foreach ($modelsToTry as $m) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$apiKey}";
                $response = Http::timeout(20)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => 'Ping test for SIMOD POLRI. Output JSON: {"status": "ok", "app": "SIMOD Enterprise"}'],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'      => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                $latency = round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $json = $response->json();
                    $text = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $decoded = json_decode($text, true);

                    if (isset($decoded['status']) && $decoded['status'] === 'ok') {
                        return [
                            'success'    => true,
                            'message'    => "Koneksi Google Gemini API Berhasil ({$m}, {$latency}ms)!",
                            'model'      => $m,
                            'latency_ms' => $latency,
                        ];
                    }
                }

                $errJson = $response->json();
                $lastError = $errJson['error']['message'] ?? "HTTP {$response->status()}";

                // If 404 model not found or 429 quota exceeded, try fallback model in the list
                if ($response->status() === 404 || $response->status() === 429) {
                    continue;
                }

                return [
                    'success' => false,
                    'message' => "Google Gemini API Error: {$lastError}",
                    'model'   => $m,
                ];
            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
            }
        }

        return [
            'success' => false,
            'message' => "Gagal terhubung ke Google Gemini API: {$lastError}",
        ];
    }

    /**
     * Run full media intelligence analysis using Google Gemini Pro.
     */
    public function analyzeMediaArticles(Polda $polda, array $articles, string $basePromptTemplate, ?string $apiKey = null, ?string $model = null): array
    {
        $apiKey = $apiKey ?: $this->getApiKey($polda->id);
        $model = $model ?: $this->getModel($polda->id);

        if (empty($apiKey)) {
            return [
                'success' => false,
                'status'  => 'missing_api_key',
                'message' => 'Google Gemini API Key belum dikonfigurasi. Masukkan API Key Anda pada pengaturan Gemini.',
            ];
        }

        // Format articles summary for ingestion
        $articleLines = [];
        $limitArticles = array_slice($articles, 0, 30);
        foreach ($limitArticles as $idx => $art) {
            $num = $idx + 1;
            $src = strtoupper($art['source_name'] ?? 'MEDIA ONLINE');
            $time = $art['pub_date'] ?? $art['time_ago'] ?? '';
            $cat = strtoupper($art['category'] ?? 'NASIONAL');
            $title = $art['title'] ?? 'Tanpa Judul';
            $summary = !empty($art['summary']) ? $art['summary'] : 'Ringkasan berita sedang diproses.';
            $url = $art['link'] ?? '';

            $articleLines[] = "{$num}. [{$src} | {$time} | Kategori: {$cat}]\nJudul: {$title}\nRingkasan: {$summary}\nURL: {$url}\n";
        }

        $newsContext = implode("\n", $articleLines);

        // Inject live news into prompt
        $fullPrompt = str_replace(
            '[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]',
            $newsContext,
            $basePromptTemplate
        );

        $modelsToTry = array_unique([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-3.8-flash',
            'gemini-3.1-pro-preview',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
            'gemini-2.5-flash',
            'gemini-1.5-flash',
        ]);
        $start = microtime(true);
        $lastError = 'Gagal memproses analisis Gemini Pro';

        foreach ($modelsToTry as $m) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$apiKey}";

                $response = Http::timeout(90)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $fullPrompt],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature'      => 0.2,
                        'maxOutputTokens'  => 8192,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

                $latency = round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $json = $response->json();
                    $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                    $parsed = json_decode($rawText, true);

                    // Fallback JSON extraction if text has wrappers
                    if (!$parsed && preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawText, $matches)) {
                        $parsed = json_decode($matches[1], true);
                    }

                    if (!empty($parsed) && is_array($parsed)) {
                        return [
                            'success'    => true,
                            'data'       => $parsed,
                            'raw_text'   => $rawText,
                            'model_used' => $m,
                            'latency_ms' => $latency,
                        ];
                    }

                    $lastError = 'Gemini mengembalikan respon namun format JSON tidak valid: ' . substr($rawText, 0, 200);
                } else {
                    $errJson = $response->json();
                    $lastError = $errJson['error']['message'] ?? "HTTP {$response->status()}";

                    // If model 404 or 429 quota exceeded, try next model in list
                    if ($response->status() === 404 || $response->status() === 429) {
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                Log::error('Gemini Analysis Exception: ' . $e->getMessage());
                $lastError = $e->getMessage();
            }
        }

        return [
            'success' => false,
            'message' => "Analisis otomatis Gemini Pro gagal: {$lastError}",
        ];
    }
}
