<?php

namespace App\Services;

use App\Models\AiSetting;
use App\Models\Polda;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeminiService
{
    /**
     * Parse raw string of API keys (separated by newline, comma, semicolon) into clean array.
     */
    public function parseApiKeys(?string $rawKeys): array
    {
        if (empty($rawKeys)) {
            return [];
        }

        $tokens = preg_split('/[\r\n,;]+/', $rawKeys);
        $clean = [];

        foreach ($tokens as $t) {
            $t = trim($t);
            if (!empty($t) && !in_array($t, $clean, true)) {
                $clean[] = $t;
            }
        }

        return $clean;
    }

    /**
     * Resolve all clean Gemini API Keys available for a specific Polda (or global/env fallback).
     */
    public function getAllApiKeys(?int $poldaId = null): array
    {
        $keys = [];

        if ($poldaId) {
            $setting = AiSetting::where('polda_id', $poldaId)->first();
            if ($setting) {
                $keys = array_merge($keys, $setting->getCleanKeysList());
            }
        }

        if (empty($keys)) {
            $globalSetting = AiSetting::whereNull('polda_id')->first();
            if ($globalSetting) {
                $keys = array_merge($keys, $globalSetting->getCleanKeysList());
            }
        }

        if (empty($keys)) {
            $envKey = config('services.gemini.api_key') ?: env('GEMINI_API_KEY');
            if (!empty($envKey)) {
                $keys = array_merge($keys, $this->parseApiKeys($envKey));
            }
        }

        return array_values(array_unique(array_filter($keys)));
    }

    /**
     * Resolve primary Gemini API Key for specific Polda or Global fallback.
     */
    public function getApiKey(?int $poldaId = null): ?string
    {
        $all = $this->getAllApiKeys($poldaId);
        return !empty($all) ? $all[0] : null;
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

        $len = strlen($key);
        if ($len <= 12) {
            return $key;
        }

        return substr($key, 0, 7) . '...' . substr($key, -4);
    }

    /**
     * Test connection for a single Google Gemini API key.
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

        $modelsToTry = array_values(array_unique(array_filter([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-3.1-pro-preview',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
        ])));
        $start = microtime(true);
        $lastError = 'Gagal menghubungi Google Gemini API';

        foreach ($modelsToTry as $m) {
            try {
                $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$apiKey}";
                $response = Http::timeout(15)->withHeaders([
                    'Content-Type' => 'application/json',
                ])->post($endpoint, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => 'Ping uji koneksi SIMOD Presisi Polri. Mohon balas: KONEKSI_OK.'],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                    ],
                ]);

                $latency = round((microtime(true) - $start) * 1000);

                if ($response->successful()) {
                    $json = $response->json();
                    if (!empty($json['candidates']) || !empty($response->body())) {
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

                if (in_array($response->status(), [400, 404, 429])) {
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
     * Test a pool of API keys and return status for each key.
     */
    public function testPoolKeys(array $keys, ?string $model = null): array
    {
        if (empty($keys)) {
            return [
                'success'      => false,
                'total'        => 0,
                'active_count' => 0,
                'message'      => 'Tidak ada API Key yang dapat diuji.',
                'accounts'     => [],
            ];
        }

        $accounts = [];
        $activeCount = 0;

        foreach ($keys as $idx => $key) {
            $key = trim($key);
            if (empty($key)) {
                continue;
            }

            $res = $this->testConnection($key, $model);
            $isOk = $res['success'] ?? false;
            if ($isOk) {
                $activeCount++;
            }

            $accounts[] = [
                'index'        => $idx + 1,
                'token_masked' => $this->maskApiKey($key),
                'key_raw'      => $key,
                'status'       => $isOk ? 'connected' : 'error',
                'latency_ms'   => $res['latency_ms'] ?? null,
                'model'        => $res['model'] ?? $model,
                'message'      => $res['message'] ?? '',
                'is_primary'   => $idx === 0,
            ];
        }

        return [
            'success'      => $activeCount > 0,
            'total'        => count($accounts),
            'active_count' => $activeCount,
            'message'      => "Pengujian Pool Selesai: {$activeCount} dari " . count($accounts) . " API Key siap melayani.",
            'accounts'     => $accounts,
        ];
    }

    /**
     * Run full media intelligence analysis using Google Gemini with multi-key pool failover.
     */
    public function analyzeMediaArticles(Polda $polda, array $articles, string $basePromptTemplate, ?string $apiKey = null, ?string $model = null): array
    {
        // Resolve key pool: if explicit apiKey passed, parse it; otherwise get all keys for this polda
        $keysPool = [];
        if (!empty($apiKey)) {
            $keysPool = $this->parseApiKeys($apiKey);
        }
        if (empty($keysPool)) {
            $keysPool = $this->getAllApiKeys($polda->id);
        }

        $model = $model ?: $this->getModel($polda->id);

        if (empty($keysPool)) {
            return [
                'success' => false,
                'status'  => 'missing_api_key',
                'message' => 'Google Gemini API Key belum dikonfigurasi. Masukkan satu atau lebih API Key pada Pengaturan AI.',
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

        $modelsToTry = array_values(array_unique(array_filter([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-3.1-pro-preview',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
        ])));

        $lastError = 'Gagal memproses analisis Gemini Pro';
        $keysExhausted = [];

        // Failover loop through each available API key in the pool
        foreach ($keysPool as $keyIndex => $currentKey) {
            $maskedCurrentKey = $this->maskApiKey($currentKey);
            $start = microtime(true);

            foreach ($modelsToTry as $m) {
                try {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$currentKey}";

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

                        // Fallback JSON extraction if text has markdown block wrappers
                        if (!$parsed && preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawText, $matches)) {
                            $parsed = json_decode($matches[1], true);
                        }

                        if (!empty($parsed) && is_array($parsed)) {
                            return [
                                'success'    => true,
                                'data'       => $parsed,
                                'raw_text'   => $rawText,
                                'model_used' => $m,
                                'key_used'   => $maskedCurrentKey,
                                'key_index'  => $keyIndex + 1,
                                'total_keys' => count($keysPool),
                                'latency_ms' => $latency,
                            ];
                        }

                        $lastError = 'Gemini mengembalikan respon namun format JSON tidak valid: ' . substr($rawText, 0, 200);
                    } else {
                        $errJson = $response->json();
                        $errMsg = $errJson['error']['message'] ?? "HTTP {$response->status()}";
                        $lastError = $errMsg;

                        // If 404 model not found, try next model with same key
                        if ($response->status() === 404) {
                            continue;
                        }

                        // If 429 quota exhausted or 403 unauthorized, failover immediately to next API key!
                        if ($response->status() === 429 || $response->status() === 403) {
                            Log::warning("Gemini Key [{$maskedCurrentKey}] quota/rate limit error ({$response->status()}: {$errMsg}). Failing over to next key in pool.");
                            $keysExhausted[] = "{$maskedCurrentKey} ({$response->status()})";
                            break; // break model loop to switch to next key
                        }
                    }
                } catch (\Throwable $e) {
                    Log::error("Gemini Analysis Exception on key [{$maskedCurrentKey}]: " . $e->getMessage());
                    $lastError = $e->getMessage();
                }
            }
        }

        $exhaustedMsg = !empty($keysExhausted) ? ' [Kunci habis/dibatasi: ' . implode(', ', $keysExhausted) . ']' : '';

        return [
            'success' => false,
            'message' => "Analisis otomatis Gemini Pro gagal pada seluruh pool API Key: {$lastError}{$exhaustedMsg}",
        ];
    }

    /**
     * Analisis intelijen khusus untuk Topik Atensi / Kasus Request Pimpinan (Smart Dossier).
     */
    public function analyzeTopicDossier(Polda $polda, \App\Models\MediaTopic $topic, array $articles, ?string $apiKey = null, ?string $model = null): array
    {
        $keysPool = !empty($apiKey) ? $this->parseApiKeys($apiKey) : $this->getAllApiKeys($polda->id);
        $model = $model ?: $this->getModel($polda->id);

        if (empty($keysPool)) {
            return [
                'success' => false,
                'status'  => 'missing_api_key',
                'message' => 'Google Gemini API Key belum dikonfigurasi. Masukkan API Key pada Pengaturan AI.',
            ];
        }

        // Format artikel terkait kasus
        $articleLines = [];
        $limitArticles = array_slice($articles, 0, 25);
        foreach ($limitArticles as $idx => $art) {
            $num = $idx + 1;
            $src = strtoupper($art['source_name'] ?? 'MEDIA ONLINE');
            $time = $art['pub_date'] ?? $art['time_ago'] ?? '';
            $cat = strtoupper($art['category'] ?? 'BERITA');
            $title = $art['title'] ?? 'Tanpa Judul';
            $summary = !empty($art['summary']) ? $art['summary'] : 'Cuplikan berita belum tersedia.';
            $url = $art['link'] ?? '';

            $articleLines[] = "{$num}. [{$src} | {$time} | {$cat}]\nJudul: {$title}\nRingkasan: {$summary}\nURL: {$url}\n";
        }
        $newsContext = !empty($articleLines) ? implode("\n", $articleLines) : 'Belum ada rincian artikel spesifik.';
        $count = count($limitArticles);

        $isNational = ($polda->code === 'DIVHUMAS');
        $commandName = $isNational ? 'Divisi Humas Polri (Mabes Polri)' : $polda->name;
        $leaderTitle = $isNational ? 'Kepala Divisi Humas Polri (Kadivhumas)' : 'Kepala Kepolisian Daerah (Kapolda)';

        $prompt = <<<PROMPT
Anda adalah Senior Media Intelligence Analyst & Strategic Communications Advisor Humas Polri berintegritas tinggi.
Pimpinan ({$leaderTitle} / Pejabat Utama {$commandName}) memberikan arahan atensi khusus untuk memantau, mendalami, dan menyiapkan mitigasi atas kasus request berikut:

=== TOPIK / KASUS ATENSI PIMPINAN ===
- Judul Kasus / Isu: {$topic->title}
- Tingkat Prioritas: {$topic->priority}
- Arahan & Catatan Khusus Pimpinan: {$topic->description}
- Kata Kunci Pemantauan: {$topic->keywords}

=== DAFTAR BERITA TERKUMPUL TERKAIT KASUS INI (Total: {$count} Berita) ===
{$newsContext}

=== INSTRUKSI ANALISIS KHUSUS KASUS (HUMAS POLRI) ===
Lakukan analisis objektif, profesional, tajam, dan solutif berbasis fakta yang ada.
Kembalikan respon HANYA dalam format JSON murni tanpa markdown wrapper (RFC 8259) dengan struktur persis berikut:
{
  "case_title": "{$topic->title}",
  "executive_summary": "Ringkasan komprehensif mengenai pokok permasalahan, kronologi insiden, dan eskalasi pemberitaan di media siber (2-3 paragraf faktual dan berimbang).",
  "risk_assessment": "Analisis potensi risiko kamtibmas, risiko hukum, serta dampak terhadap citra dan public trust institusi Polri jika isu ini tidak segera dimitigasi/diklarifikasi secara transparan.",
  "sentiment_analysis": {
    "positif": 15,
    "netral": 45,
    "negatif": 40,
    "dominant_sentiment": "negatif",
    "framing_media": "Uraian mengenai sudut pandang media massa dan sentimen warganet (misal: penekanan pada transparansi SOP, sorotan lembaga pengawas, atau empati masyarakat)."
  },
  "talking_points": [
    "Poin klarifikasi 1 berbasis fakta objektif untuk juru bicara...",
    "Poin klarifikasi 2 komitmen keterbukaan dan penegakan hukum profesional...",
    "Poin klarifikasi 3 koordinasi proaktif dengan tokoh masyarakat dan pengawas eksternal..."
  ],
  "action_recommendations": [
    {
      "timeframe": "0 - 2 Jam (Tanggap Cepat)",
      "action": "Tindakan mitigasi mendesak, verifikasi lapangan, atau pernyataan awal.",
      "pic": "Satker / Pejabat Terkait"
    },
    {
      "timeframe": "2 - 6 Jam (Konsolidasi)",
      "action": "Press briefing, rilis resmi terpadu, dan pendekatan stakeholder.",
      "pic": "Bidhumas / Divhumas"
    },
    {
      "timeframe": "24 Jam (Pengendalian Opini)",
      "action": "Amplifikasi narasi positif, monitoring sentimen lanjutan, dan publikasi transparansi penanganan.",
      "pic": "Tim Siber & Pengelola Medsos"
    }
  ],
  "whatsapp_script": "Tuliskan naskah WhatsApp resmi siap kirim ke Pimpinan menggunakan format baku kedinasan Polri (Kepada Yth. Pimpinan, Laporan Perkembangan Kasus Atensi, Fakta Kasus, Analisis Sentimen Media, Rekomendasi Langkah Humas, Penutup Hormat)."
}
PROMPT;

        $modelsToTry = array_values(array_unique(array_filter([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-2.5-flash',
            'gemini-1.5-flash',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
        ])));

        $lastError = 'Gagal memproses analisis kasus Gemini Pro';

        foreach ($keysPool as $keyIndex => $currentKey) {
            $maskedCurrentKey = $this->maskApiKey($currentKey);
            $start = microtime(true);

            foreach ($modelsToTry as $m) {
                try {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$currentKey}";

                    $response = Http::timeout(90)->withHeaders([
                        'Content-Type' => 'application/json',
                    ])->post($endpoint, [
                        'contents' => [
                            [
                                'parts' => [
                                    ['text' => $prompt],
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

                        if (!$parsed && preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawText, $matches)) {
                            $parsed = json_decode($matches[1], true);
                        }

                        if (!empty($parsed) && is_array($parsed)) {
                            return [
                                'success'    => true,
                                'data'       => $parsed,
                                'raw_text'   => $rawText,
                                'model_used' => $m,
                                'key_used'   => $maskedCurrentKey,
                                'latency_ms' => $latency,
                            ];
                        }
                    } else {
                        if ($response->status() === 429 || $response->status() === 403) {
                            break; // switch to next key in pool
                        }
                    }
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                }
            }
        }

        return [
            'success' => false,
            'message' => "Analisis kasus Gemini gagal: {$lastError}",
        ];
    }
}
