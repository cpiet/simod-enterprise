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
            if (empty($t)) {
                continue;
            }

            // Extract exact 39-character Gemini API Key if surrounded by labels, comments, or quotes
            if (preg_match('/(AIzaSy[A-Za-z0-9_-]{33})/', $t, $match)) {
                $candidate = $match[1];
            } else {
                $candidate = $t;
            }

            if (!empty($candidate) && !in_array($candidate, $clean, true)) {
                $clean[] = $candidate;
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

        if (str_contains($apiKey, ' ') || preg_match('/[^A-Za-z0-9_-]/', $apiKey)) {
            return [
                'success' => false,
                'message' => 'Format Gemini API Key tidak valid (terdeteksi spasi atau karakter ilegal). Pastikan kunci disalin lengkap tanpa spasi.',
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
  "whatsapp_script": "Susun naskah WhatsApp resmi siap kirim ke Pimpinan yang komprehensif, terstruktur rapi, berwibawa, dan berimbang (tidak terlalu panjang dan tidak terlalu pendek, sekitar 250-400 kata). Wajib menggunakan format baku dinas Polri dengan penomoran romawi dan bullet points, menggunakan penanda bold *teks*, serta dipisahkan baris baru ganda (\\n\\n) antar bagian:\\n- KEPADA YTH: {$leaderTitle}\\n- DARI: PIKET ANALISIS INTELIJEN MEDIA & OPINI PUBLIK\\n- SATUAN: {$commandName}\\n- WAKTU: [Hari, Tanggal & Jam aktual]\\n- PERIHAL: LAPORAN KHUSUS PERKEMBANGAN KASUS ATENSI — {$topic->title}\\n\\n*I. FAKTA & PERKEMBANGAN TERKINI:*\\n• [2-3 poin fakta objektif terverifikasi 5W+1H]\\n\\n*II. POLARISASI MEDIA & SENTIMEN OPINI PUBLIK:*\\n• *Komposisi Sentimen:* Positif X% | Netral Y% | Negatif Z% (*Dominan: [Sentimen]*)\\n• *Framing Media & Warganet:* [1-2 kalimat fokus pemberitaan media massa dan warganet]\\n\\n*III. POTENSI RISIKO KAMTIBMAS & CITRA (UNMITIGATED RISK):*\\n• [Kajian 2 kalimat dampak fatal jika isu dibiarkan tanpa mitigasi cepat: ancaman fisik/siber dan dampaknya terhadap public trust]\\n\\n*IV. LANGKAH TAKTIS & REKOMENDASI KEHUMASAN:*\\n1. *0-2 Jam (Tanggap Cepat):* [Aksi verifikasi lapangan, satu pintu juru bicara]\\n2. *2-6 Jam (Konsolidasi Publik):* [Penyusunan press release transparan, doorstop, infografis]\\n3. *24 Jam (Pengendalian Opini):* [Amplifikasi penegakan hukum profesional, pemantauan sentimen]\\n\\n*V. POIN KUNCI JURU BICARA (TALKING POINTS):*\\n• [2-3 poin pernyataan resmi juru bicara]\\n\\n*VI. PENUTUP & MOHON PETUNJUK:*\\nDemikian laporan khusus perkembangan kasus atensi ini kami haturkan ke hadapan Jenderal sebagai bahan pertimbangan dan arahan kebijakan lebih lanjut.\\n\\n*Hormat kami,*\\n*Tim Satgas Intelijen Media & Opini Publik*\\n*{$commandName}*"
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
                            $parsed['whatsapp_script'] = self::buildRichWhatsAppScript($parsed, $polda, $topic);

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

    /**
     * Standarisasi dan sintesis Naskah WhatsApp Laporan Khusus Pimpinan (Presisi Polri).
     * Memastikan naskah tidak terlalu pendek dan tidak terlalu panjang (~250-400 kata),
     * lengkap dengan hierarki resmi, fakta empiris, sentimen, risiko unmitigated, rekomendasi berjenjang, dan talking points.
     */
    public static function buildRichWhatsAppScript(array $data, ?Polda $polda = null, ?\App\Models\MediaTopic $topic = null): string
    {
        $existing = trim((string)($data['whatsapp_script'] ?? ''));

        // Cek apakah naskah bawaan Gemini sudah lengkap, berjenjang romawi, dan memiliki pemisah baris rapi
        $hasNewlines = substr_count($existing, "\n") >= 6;
        $hasSections = (str_contains($existing, 'I.') || str_contains($existing, 'FAKTA')) &&
                       (str_contains($existing, 'II.') || str_contains($existing, 'SENTIMEN') || str_contains($existing, 'POLARISASI')) &&
                       (str_contains($existing, 'III.') || str_contains($existing, 'RISIKO') || str_contains($existing, 'REKOMENDASI'));

        if (strlen($existing) >= 650 && $hasNewlines && $hasSections) {
            return $existing;
        }

        // Sintesis naskah intelijen berstandar kedinasan Humas Polri
        $isNational = ($polda?->code === 'DIVHUMAS');
        $commandName = $isNational ? 'DIVISI HUMAS POLRI (MABES POLRI)' : ($polda?->name ? strtoupper($polda->name) : 'POLDA');
        $leaderTitle = $isNational ? 'KADIVHUMAS POLRI / PEJABAT UTAMA MABES POLRI' : 'BAPAK KAPOLDA / WAKAPOLDA / PJU';
        $tz = ($isNational || $polda?->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';
        $nowStr = now()->translatedFormat('l, d F Y');
        $timeStr = now()->format('H.i') . ' ' . $tz;

        $caseTitle = strtoupper($topic?->title ?? $data['case_title'] ?? 'KASUS ATENSI');

        $lines = [];
        $lines[] = "*KEPADA YTH: {$leaderTitle}*";
        $lines[] = "*DARI: PIKET ANALISIS INTELIJEN MEDIA & OPINI PUBLIK*";
        $lines[] = "*SATUAN: {$commandName}*";
        $lines[] = "*WAKTU LAPORAN: {$nowStr}, Pukul {$timeStr}*";
        $lines[] = "*PERIHAL: LAPORAN KHUSUS PERKEMBANGAN KASUS ATENSI — {$caseTitle}*";
        $lines[] = "";

        // I. FAKTA & PERKEMBANGAN TERKINI
        $lines[] = "*I. FAKTA & PERKEMBANGAN TERKINI:*";
        $execSummary = trim($data['executive_summary'] ?? '');
        if (!empty($execSummary)) {
            // Pisahkan kalimat secara cerdas agar mudah dibaca di mobile
            $sentences = preg_split('/(?<=[.?!])\s+(?=[A-Z0-9])/', $execSummary);
            if (count($sentences) >= 2) {
                foreach ($sentences as $s) {
                    $trimmed = trim($s);
                    if (!empty($trimmed)) {
                        $lines[] = "• " . $trimmed;
                    }
                }
            } else {
                $lines[] = "• " . $execSummary;
            }
        } else {
            $lines[] = "• Penanganan kasus atensi pimpinan terus dipantau secara intensif melalui sinergi jajaran.";
        }
        $lines[] = "";

        // II. POLARISASI MEDIA & SENTIMEN OPINI PUBLIK
        $lines[] = "*II. POLARISASI MEDIA & SENTIMEN OPINI PUBLIK:*";
        $sent = $data['sentiment_analysis'] ?? [];
        $pos = isset($sent['positif']) ? (int)$sent['positif'] : 15;
        $net = isset($sent['netral']) ? (int)$sent['netral'] : 45;
        $neg = isset($sent['negatif']) ? (int)$sent['negatif'] : 40;
        $dom = strtoupper($sent['dominant_sentiment'] ?? ($neg > $pos ? 'NEGATIF' : 'NETRAL'));
        $lines[] = "• *Komposisi Sentimen:* Positif {$pos}% | Netral {$net}% | Negatif {$neg}% (*Dominan: {$dom}*)";
        if (!empty($sent['framing_media'])) {
            $lines[] = "• *Framing Media & Warganet:* " . trim($sent['framing_media']);
        }
        $lines[] = "";

        // III. POTENSI RISIKO KAMTIBMAS & CITRA (UNMITIGATED RISK)
        $lines[] = "*III. POTENSI RISIKO KAMTIBMAS & CITRA (UNMITIGATED RISK):*";
        $risk = trim($data['risk_assessment'] ?? '');
        if (!empty($risk)) {
            $lines[] = "• " . $risk;
        } else {
            $lines[] = "• Isu berpotensi menggerus public trust jika tidak dikelola dengan komunikasi strategis yang transparan dan akuntabel.";
        }
        $lines[] = "";

        // IV. LANGKAH TAKTIS & REKOMENDASI KEHUMASAN
        $lines[] = "*IV. LANGKAH TAKTIS & REKOMENDASI KEHUMASAN:*";
        $recs = $data['action_recommendations'] ?? [];
        if (!empty($recs) && is_array($recs)) {
            foreach ($recs as $idx => $r) {
                $num = $idx + 1;
                $tf = $r['timeframe'] ?? "Langkah {$num}";
                $act = $r['action'] ?? '';
                $pic = !empty($r['pic']) ? " _(PIC: {$r['pic']})_" : '';
                $lines[] = "{$num}. *{$tf}:* {$act}{$pic}";
            }
        } else {
            $lines[] = "1. *0-2 Jam (Tanggap Cepat):* Melaksanakan rapat koordinasi internal kilat, menyamakan narasi, dan menetapkan juru bicara resmi satu pintu.";
            $lines[] = "2. *2-6 Jam (Konsolidasi Publik):* Menyiapkan press release resmi transparan berbasis kronologi dan fakta objektif untuk menjawab keraguan publik.";
            $lines[] = "3. *24 Jam (Pengendalian Opini):* Mengamplifikasi narasi penegakan hukum profesional serta memantau fluktuasi sentimen media sosial.";
        }
        $lines[] = "";

        // V. POIN KUNCI JURU BICARA (TALKING POINTS)
        $tps = $data['talking_points'] ?? [];
        if (!empty($tps) && is_array($tps)) {
            $lines[] = "*V. POIN KUNCI JURU BICARA (TALKING POINTS):*";
            foreach ($tps as $tp) {
                $lines[] = "• " . trim($tp);
            }
            $lines[] = "";
        }

        // VI. PENUTUP & MOHON PETUNJUK
        $lines[] = "*VI. PENUTUP & MOHON PETUNJUK:*";
        $lines[] = "Demikian laporan khusus perkembangan kasus atensi ini kami haturkan ke hadapan Jenderal sebagai bahan pertimbangan dan arahan kebijakan lebih lanjut.";
        $lines[] = "";
        $lines[] = "*Hormat kami,*";
        $lines[] = "*Tim Satgas Intelijen Media & Opini Publik*";
        $lines[] = "*{$commandName}*";

        return implode("\n", $lines);
    }

    /**
     * SADEWA Social AI — Analisis Intelijen Media Sosial Terpadu (Executive Social Briefing).
     * Menghasilkan 5 instrumen analitik siber dari data percakapan warganet lintas platform.
     */
    public function analyzeSocialPosts(Polda $polda, array $posts, ?string $apiKey = null, ?string $model = null): array
    {
        $keysPool = !empty($apiKey) ? $this->parseApiKeys($apiKey) : $this->getAllApiKeys($polda->id);
        $model    = $model ?: $this->getModel($polda->id);

        if (empty($keysPool)) {
            return [
                'success' => false,
                'status'  => 'missing_api_key',
                'message' => 'Google Gemini API Key belum dikonfigurasi. Masukkan API Key pada menu Pengaturan AI (/settings/ai).',
            ];
        }

        $isNational  = ($polda->code === 'DIVHUMAS');
        $commandName = $isNational ? 'Divisi Humas Polri (Mabes Polri)' : $polda->name;
        $leaderTitle = $isNational ? 'Kepala Divisi Humas Polri' : 'Kepala Kepolisian Daerah (Kapolda)';
        $tz          = ($isNational || $polda->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';
        $nowStr      = now()->translatedFormat('l, d F Y') . ', Pukul ' . now()->format('H.i') . ' ' . $tz;

        // Build post context (up to 40 latest posts)
        $limitPosts  = array_slice($posts, 0, 40);
        $postLines   = [];
        $totalViews  = 0;
        $totalEngage = 0;
        $sentCounts  = ['positif' => 0, 'netral' => 0, 'negatif' => 0];

        foreach ($limitPosts as $idx => $post) {
            $num      = $idx + 1;
            $platform = strtoupper($post['platform'] ?? 'MEDSOS');
            $author   = $post['author_name'] ?? 'Warganet';
            $handle   = $post['author_handle'] ?? '';
            $isKol    = !empty($post['is_kol']) ? '[KOL]' : '';
            $sent     = strtoupper($post['sentiment'] ?? 'NETRAL');
            $content  = mb_substr(strip_tags($post['content'] ?? ''), 0, 200);
            $views    = (int) ($post['views_count'] ?? 0);
            $likes    = (int) ($post['likes_count'] ?? 0);
            $shares   = (int) ($post['shares_count'] ?? 0);
            $viral    = (int) ($post['virality_score'] ?? 0);

            $totalViews  += $views;
            $totalEngage += $likes + $shares + (int) ($post['comments_count'] ?? 0);
            $sentKey      = in_array(strtolower($post['sentiment'] ?? ''), ['positif', 'negatif', 'netral']) ? strtolower($post['sentiment']) : 'netral';
            $sentCounts[$sentKey]++;

            $postLines[] = "{$num}. [{$platform} {$isKol}| Sentimen: {$sent} | Jangkauan: " . number_format($views) . " views | Interaksi: " . number_format($likes + $shares) . " | Viralisasi: {$viral}/100]";
            $postLines[] = "   Akun: {$author} {$handle}";
            $postLines[] = "   Konten: {$content}";
            $postLines[] = '';
        }

        $totalPosts  = count($limitPosts);
        $postsContext = implode("\n", $postLines);
        $totalPos    = $sentCounts['positif'];
        $totalNet    = $sentCounts['netral'];
        $totalNeg    = $sentCounts['negatif'];
        $totalSent   = max(1, $totalPos + $totalNet + $totalNeg);

        $prompt = <<<PROMPT
Anda adalah analis intelijen media sosial terpercaya untuk Komando Siber {$commandName}.
Tanggal & Waktu Analisis: {$nowStr}

DATA PERCAKAPAN WARGANET TERPANTAU (Total: {$totalPosts} postingan dari 5 platform medsos):
{$postsContext}

STATISTIK GLOBAL:
- Total Jangkauan (Estimasi Views): {$totalViews}
- Total Interaksi (Likes + Shares + Komentar): {$totalEngage}
- Komposisi Sentimen Awal: Positif {$totalPos} ({$totalPos}/{$totalSent}), Netral {$totalNet}, Negatif {$totalNeg}

INSTRUKSI ANALISIS INTELIJEN SIBER (kembalikan HANYA JSON murni tanpa markdown, tanpa komentar):
{
  "executive_brief": "Ringkasan eksekutif 2-3 paragraf: topik dominan percakapan warganet 24 jam terakhir, platform mana yang paling aktif, nada dan arah opini publik. Gunakan bahasa Indonesia yang lugas, mengalir, dan bernilai intelijen tinggi.",
  "top_concern": "1-2 kalimat: isu atau narasi paling mengkhawatirkan dari sudut pandang keamanan siber dan reputasi institusi Polri yang perlu segera ditangani.",
  "sentiment_analysis": {
    "positif": 25,
    "netral": 45,
    "negatif": 30,
    "dominant": "netral",
    "stability_index": 72,
    "stability_label": "Waspada",
    "narrative_framing": "Deskripsi singkat framing dominan warganet: sudut pandang mayoritas yang muncul di postingan."
  },
  "buzzer_vs_organic": {
    "assessment": "Apakah pola sentimen negatif terlihat organik (keluhan asli warga) atau terkoordinasi (buzzer dengan pola hashtag serentak, posting burst)? Jelaskan sinyal-sinyal yang terdeteksi.",
    "risk_level": "rendah|sedang|tinggi",
    "risk_note": "Dampak potensial jika pola buzzer dibiarkan tidak dimitigasi."
  },
  "critical_accounts": [
    {
      "name": "Nama/Handle Akun Paling Vokal",
      "platform": "Nama Platform",
      "category": "Jurnalis|KOL|Aktivis|Politisi|Warganet Viral|Anonim",
      "tone": "Kritis|Netral|Suportif",
      "reach_note": "Estimasi jangkauan dan urgensi pemantauan."
    }
  ],
  "cooling_system": [
    {
      "action": "Rekomendasi taktis komunikasi Humas konkret (bukan generik).",
      "channel": "Platform medsos target (TikTok / Instagram / 𝕏 / WhatsApp).",
      "timing": "Jam terbaik untuk eksekusi berdasarkan pola aktivitas warganet.",
      "pic": "Unit Polri yang bertanggung jawab."
    }
  ],
  "whatsapp_script": "Naskah WhatsApp format komando pimpinan, siap kirim ke Kapolri/Kapolda/PJU. Gunakan format baku dinas Polri (penomoran romawi, bullet points, penanda bold *teks*, baris baru \\n antar bagian). Panjang ideal 300-450 kata. Wajib mencakup: (1) Kepada Yth, (2) Ringkasan Situasi Medsos, (3) Komposisi Sentimen, (4) Isu Kritis & Potensi Risiko, (5) Rekomendasi Taktis Bertahap (0-2 jam, 2-6 jam, 24 jam), (6) Penutup hormat. Gunakan nama komandan: {$leaderTitle}."
}
PROMPT;

        $modelsToTry = array_values(array_unique(array_filter([
            $model,
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-2.5-flash',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-2.5-pro',
            'gemini-1.5-pro',
        ])));

        $lastError = 'Gagal memproses analisis SADEWA Social AI';

        foreach ($keysPool as $keyIndex => $currentKey) {
            $maskedKey = $this->maskApiKey($currentKey);
            $start     = microtime(true);

            foreach ($modelsToTry as $m) {
                try {
                    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$currentKey}";
                    $response = Http::timeout(90)->withHeaders(['Content-Type' => 'application/json'])->post($endpoint, [
                        'contents' => [['parts' => [['text' => $prompt]]]],
                        'generationConfig' => [
                            'temperature'     => 0.2,
                            'maxOutputTokens' => 8192,
                            'responseMimeType' => 'application/json',
                        ],
                    ]);

                    $latency = round((microtime(true) - $start) * 1000);

                    if ($response->successful()) {
                        $json    = $response->json();
                        $rawText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        $parsed  = json_decode($rawText, true);

                        if (!$parsed && preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawText, $matches)) {
                            $parsed = json_decode($matches[1], true);
                        }

                        if (!empty($parsed) && is_array($parsed)) {
                            // Enhance whatsapp_script if too short
                            if (empty($parsed['whatsapp_script']) || strlen($parsed['whatsapp_script']) < 400) {
                                $parsed['whatsapp_script'] = $this->buildSocialWhatsAppScript($parsed, $polda, $totalPosts, $sentCounts);
                            }

                            return [
                                'success'    => true,
                                'data'       => $parsed,
                                'model_used' => $m,
                                'key_used'   => $maskedKey,
                                'latency_ms' => $latency,
                            ];
                        }

                        $lastError = 'JSON tidak valid: ' . substr($rawText, 0, 150);
                    } else {
                        $errMsg    = $response->json('error.message') ?? "HTTP {$response->status()}";
                        $lastError = $errMsg;
                        if (in_array($response->status(), [429, 403])) break; // switch key
                        if ($response->status() === 404) continue; // try next model
                    }
                } catch (\Throwable $e) {
                    $lastError = $e->getMessage();
                    Log::error("SADEWA Social AI exception [{$maskedKey}]: " . $e->getMessage());
                }
            }
        }

        return ['success' => false, 'message' => "Analisis SADEWA Social AI gagal: {$lastError}"];
    }

    /**
     * Build structured WhatsApp script for social media intelligence report.
     */
    private function buildSocialWhatsAppScript(array $data, Polda $polda, int $totalPosts, array $sentCounts): string
    {
        $isNational  = ($polda->code === 'DIVHUMAS');
        $commandName = $isNational ? 'DIVISI HUMAS POLRI (MABES POLRI)' : strtoupper($polda->name);
        $leaderTitle = $isNational ? 'KADIVHUMAS POLRI / WAKADIVHUMAS / PJU' : 'BAPAK KAPOLDA / WAKAPOLDA / PJU';
        $tz          = ($isNational || $polda->timezone === 'Asia/Jakarta') ? 'WIB' : 'WITA';
        $nowStr      = now()->translatedFormat('l, d F Y');
        $timeStr     = now()->format('H.i') . ' ' . $tz;

        $totalSent = max(1, array_sum($sentCounts));
        $posPct    = round(($sentCounts['positif'] / $totalSent) * 100);
        $netPct    = round(($sentCounts['netral'] / $totalSent) * 100);
        $negPct    = round(($sentCounts['negatif'] / $totalSent) * 100);

        $sent  = $data['sentiment_analysis'] ?? [];
        $domSent = strtoupper($sent['dominant'] ?? ($negPct > $posPct ? 'NEGATIF' : 'NETRAL'));
        $stability = $sent['stability_label'] ?? ($sent['stability_index'] >= 75 ? 'Sangat Terkendali' : ($sent['stability_index'] >= 50 ? 'Waspada' : 'Kritis'));

        $lines = [];
        $lines[] = "*LAPORAN SITUASI DINAMIKA MEDIA SOSIAL & OPINI PUBLIK*";
        $lines[] = "*KEPADA YTH: {$leaderTitle}*";
        $lines[] = "*DARI: PIKET ANALISIS INTELIJEN MEDIA SOSIAL & PATROLI SIBER*";
        $lines[] = "*SATUAN: {$commandName}*";
        $lines[] = "*WAKTU: {$nowStr}, Pukul {$timeStr}*";
        $lines[] = "";

        $lines[] = "*I. RINGKASAN SITUASI MEDSOS (24 JAM)*";
        $lines[] = "• *Total Percakapan Terpantau:* {$totalPosts} postingan warganet lintas 𝕏, TikTok, Instagram, YouTube, Facebook.";
        $briefText = $data['executive_brief'] ?? 'Percakapan warganet terpantau dalam batas kondusif.';
        $lines[] = "• " . mb_substr(strip_tags($briefText), 0, 400);
        $lines[] = "";

        $lines[] = "*II. INDEKS POLARISASI SENTIMEN OPINI PUBLIK*";
        $lines[] = "• *Komposisi:* Positif {$posPct}% | Netral {$netPct}% | Negatif {$negPct}% (*Dominan: {$domSent}*)";
        $lines[] = "• *Status Stabilitas Opini:* {$stability}";
        if (!empty($sent['narrative_framing'])) {
            $lines[] = "• *Framing Warganet:* " . mb_substr($sent['narrative_framing'], 0, 200);
        }
        $lines[] = "";

        $lines[] = "*III. ISU KRITIS & POTENSI RISIKO SIBER*";
        $topConcern = $data['top_concern'] ?? 'Tidak ada isu kritis yang terdeteksi saat ini.';
        $lines[]   = "• " . mb_substr($topConcern, 0, 300);
        $buzzer    = $data['buzzer_vs_organic'] ?? [];
        if (!empty($buzzer['risk_level']) && in_array($buzzer['risk_level'], ['sedang', 'tinggi'])) {
            $lines[] = "• *⚠️ Potensi Buzzer/Koordinasi:* Risiko " . strtoupper($buzzer['risk_level']) . " — " . mb_substr($buzzer['risk_note'] ?? '', 0, 150);
        }
        $lines[] = "";

        $lines[] = "*IV. REKOMENDASI TAKTIS COOLING SYSTEM*";
        $cooling = $data['cooling_system'] ?? [];
        if (!empty($cooling)) {
            foreach (array_slice($cooling, 0, 3) as $idx => $cs) {
                $n       = $idx + 1;
                $action  = $cs['action'] ?? '';
                $channel = $cs['channel'] ?? '';
                $timing  = $cs['timing'] ?? '';
                $pic     = $cs['pic'] ?? '';
                $lines[] = "{$n}. *{$timing}* ({$channel}): {$action}" . ($pic ? " _(PIC: {$pic})_" : '');
            }
        } else {
            $lines[] = "1. *0-2 Jam (Tanggap Cepat):* Rilis konten klarifikasi resmi melalui akun media sosial Divhumas/Bidhumas.";
            $lines[] = "2. *2-6 Jam (Konsolidasi):* Tayangkan video humanis di TikTok & Instagram Reels pada jam puncak malam (19.30-21.00).";
            $lines[] = "3. *24 Jam (Pengendalian Opini):* Intensifkan patroli siber dan replikasi narasi positif lintas platform.";
        }
        $lines[] = "";

        $lines[] = "*V. PENUTUP & MOHON PETUNJUK*";
        $lines[] = "Demikian laporan situasi dinamika media sosial dan opini publik ini disampaikan ke hadapan Jenderal sebagai bahan pertimbangan dan arahan kebijakan strategis lebih lanjut.";
        $lines[] = "";
        $lines[] = "*Hormat kami,*";
        $lines[] = "*Piket Analis Intelijen Media & Patroli Siber*";
        $lines[] = "*{$commandName}*";

        return implode("\n", $lines);
    }

    /**
     * SADEWA AI - Conversational Radar & Grounded Intelligence Assistant
     */
    public function chatWithRadar(Polda $polda, string $userMessage, array $conversationHistory, array $articles, ?array $activeReport = null, ?string $apiKey = null, ?string $model = null): array
    {
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
                'message' => 'Google Gemini API Key belum dikonfigurasi pada Pengaturan AI.',
            ];
        }

        $commandName = $polda->code === 'DIVHUMAS' || $polda->id === 10
            ? 'Divisi Humas Polri'
            : 'Bidang Humas ' . $polda->name;

        // Build grounded article context (top 35 articles)
        $articleLines = [];
        $limitArticles = array_slice($articles, 0, 35);
        foreach ($limitArticles as $idx => $art) {
            $num = $idx + 1;
            $src = strtoupper($art['source_name'] ?? 'MEDIA');
            $sent = strtoupper($art['sentiment'] ?? 'NETRAL');
            $title = $art['title'] ?? 'Tanpa Judul';
            $summary = !empty($art['summary']) ? $art['summary'] : '';
            $articleLines[] = "{$num}. [{$src} | Sentimen: {$sent}] {$title} - {$summary}";
        }
        $articlesContext = implode("\n", $articleLines);

        $reportContext = '';
        if ($activeReport) {
            $reportContext = "Ringkasan Eksekutif Terkini: " . ($activeReport['executive_brief'] ?? '-') . "\n"
                . "Atensi Utama Pimpinan: " . ($activeReport['top_concern'] ?? '-') . "\n"
                . "Komposisi Sentimen: Positif " . ($activeReport['sentiment_positive'] ?? 0) . "%, Netral " . ($activeReport['sentiment_neutral'] ?? 0) . "%, Negatif " . ($activeReport['sentiment_negative'] ?? 0) . "%";
        }

        // Format recent conversation history
        $historyLines = [];
        $recentHistory = array_slice($conversationHistory, -6);
        foreach ($recentHistory as $turn) {
            $role = ($turn['role'] ?? 'user') === 'user' ? 'USER' : 'SADEWA';
            $content = $turn['content'] ?? '';
            $historyLines[] = "{$role}: {$content}";
        }
        $historyText = implode("\n", $historyLines);

        $prompt = <<<PROMPT
Anda adalah "SADEWA" (Sistem Analisis Digital & Evaluasi Wacana Aktual), asisten analis intelijen media siber dan opini publik resmi Komando Siber {$commandName}.
Tugas Anda adalah berdialog dengan pimpinan Polri dan personel analis seputar dinamika pemberitaan, analisis sentimen masyarakat, deteksi kerawanan kamtibmas, serta rekomendasi langkah komunikasi kehumasan.

DATA PENGAMATAN RADAR 33 MEDIA TERKINI:
{$articlesContext}

{$reportContext}

RIWAYAT PERCAKAPAN SEBELUMNYA:
{$historyText}

PERTANYAAN / INSTRUKSI SAAT INI:
"{$userMessage}"

PANDUAN GAYA BAHASA & STRUKTUR LAPORAN (SANGAT KRUSIAL):
1. **Bahasa Indonesia Sangat Natural & Mengalir**: Tulis dalam Bahasa Indonesia yang luwes, hidup, runtut, dan mudah dipahami, selayaknya seorang staf analis intelijen senior yang cakap sedang berdialog langsung dengan pimpinan. Hindari susunan kalimat kaku atau kramat seperti terjemahan mesin otomatis.
2. **DILARANG MENYELIPKAN KATA BAHASA INGGRIS DALAM KURUNG**: JANGAN menggunakan kata-kata asing di dalam kurung di setiap baris atau judul (seperti "(update)", "(Risk Assessment)", "(Institutional Trust Crisis)", "(One Gate Information)", "(Counter Narrative)", "(Commanders Direction)"). Selalu gunakan istilah Bahasa Indonesia yang resmi, kaya, dan berwibawa: misalnya perkembangan mutakhir, potensi risiko reputasi institusi, keterbukaan satu pintu, langkah peredam opini negatif, arahan komando pimpinan.
3. **Format Rapi & Nyaman Dibaca**:
   - Awali dengan salam dinas yang santun dan kalimat pengantar yang mengalir (misal: "Siap Jenderal / Komandan, berdasarkan pemantauan radar media siber terkini oleh SADEWA (Sistem Analisis Digital & Evaluasi Wacana Aktual), berikut kami laporkan pemutakhiran data mengenai...").
   - Gunakan penomoran angka dan butir poin secara rapi dan proporsional.
   - Hindari tanda pagar '#' dan jangan menumpuk simbol bintang ganda yang berantakan. Cukup gunakan penebalan teks biasa untuk nama sub-judul atau poin penting.
4. **Kredibilitas Sumber Berita**: Sebutkan nama-nama portal media yang memberitakan (misal: "sebagaimana dilaporkan oleh Antara News, Detikcom, CNN Indonesia...") agar pimpinan mengetahui validitas sebaran informasinya.
5. **Dilarang Menyebutkan Identitas Model AI**: Jangan pernah mencantumkan atau menyebut nama teknis sistem seperti "gemini", "flash-lite", atau istilah algoritma internal di dalam jawaban. Identitas Anda murni adalah "SADEWA".
PROMPT;

        $modelsToTry = array_values(array_unique(array_filter([
            $model,
            'gemini-2.5-flash',
            'gemini-3.5-flash-lite',
            'gemini-flash-lite-latest',
            'gemini-2.0-flash',
            'gemini-1.5-flash',
            'gemini-2.5-pro',
        ])));

        $lastError = 'Gagal memproses respon SADEWA AI';

        foreach ($keysPool as $keyIndex => $currentKey) {
            $maskedKey = $this->maskApiKey($currentKey);

            foreach ($modelsToTry as $m) {
                try {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$m}:generateContent?key={$currentKey}";

                    $payload = [
                        'contents' => [
                            [
                                'role'  => 'user',
                                'parts' => [['text' => $prompt]]
                            ]
                        ],
                        'generationConfig' => [
                            'temperature'     => 0.4,
                            'maxOutputTokens' => 1500,
                        ],
                    ];

                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(30)
                        ->post($url, $payload);

                    if ($response->successful()) {
                        $json = $response->json();
                        $replyText = $json['candidates'][0]['content']['parts'][0]['text'] ?? '';

                        if (!empty(trim($replyText))) {
                            return [
                                'success' => true,
                                'reply'   => trim($replyText),
                                'model'   => $m,
                                'key'     => $maskedKey,
                            ];
                        }
                    }

                    $errorBody = $response->body();
                    $lastError = "Model {$m} (Key #{$keyIndex}): " . ($response->json('error.message') ?? $errorBody);
                } catch (\Throwable $e) {
                    $lastError = "Exception Model {$m} (Key #{$keyIndex}): " . $e->getMessage();
                }
            }
        }

        return [
            'success' => false,
            'status'  => 'error',
            'message' => $lastError,
        ];
    }
}
