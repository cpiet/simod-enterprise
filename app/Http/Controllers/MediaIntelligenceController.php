<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarning;
use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\MediaIntelligenceReport;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\PressRelease;
use App\Services\RssFeedService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
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

        // Active Polda ID fallback
        $poldaId = $selectedPolda ? $selectedPolda->id : ($allPoldas->first()?->id ?? 1);

        // Media Feeds for this Polda
        $mediaFeeds = MediaFeed::where('polda_id', $poldaId)->orderBy('name')->get();

        // If user requests live sync/refresh, scrape now
        $forceRefresh = $request->boolean('refresh', false);
        $rssService = new RssFeedService();

        if ($forceRefresh && $mediaFeeds->isNotEmpty()) {
            $liveArticles = $rssService->fetchAggregatedFeeds($mediaFeeds->toArray(), true);
            foreach (array_slice($liveArticles, 0, 400) as $item) {
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

        // Load articles from database (up to 800 latest articles)
        $dbArticles = MediaArticle::where('polda_id', $poldaId)
            ->latest('pub_date')
            ->latest('id')
            ->limit(800)
            ->get();

        // Format articles for frontend
        $articles = $dbArticles->map(function ($a) {
            return [
                'id'             => (string) $a->id,
                'title'          => $a->title,
                'source_name'    => strtoupper($a->source_name),
                'category'       => $a->category,
                'category_badge' => strtoupper($a->category),
                'time_ago'       => $a->created_at ? $a->created_at->diffForHumans() : 'Baru saja',
                'pub_date'       => $a->pub_date ? $a->pub_date->format('d M Y, H.i') . ' WITA' : now()->format('d M Y, H.i') . ' WITA',
                'summary'        => $a->summary ?: 'Cuplikan berita pantauan intelijen media belum tersedia.',
                'image_url'      => $a->image_url,
                'link'           => $a->url,
                'sentiment'      => $a->sentiment ?: 'netral',
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
            'portalsList'      => $portalsList,
            'categoriesList'   => $categoriesList,
            'report'           => $report,
            'aiPromptTemplate' => $aiPromptTemplate,
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
        $polda = Polda::where('code', $poldaCode)->first() ?? Polda::first();

        if (empty(trim($rawInput))) {
            return back()->with('error', 'Teks salinan hasil analisis ChatGPT tidak boleh kosong.');
        }

        // Coba decode JSON langsung atau bersihkan ```json
        $parsed = json_decode($rawInput, true);
        if (!$parsed && preg_match('/```(?:json)?\s*([\s\S]*?)\s*```/', $rawInput, $matches)) {
            $parsed = json_decode($matches[1], true);
        }

        if (!$parsed) {
            // Text based fallback
            $parsed = [
                'title'           => 'LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK ' . ($polda->name ?? 'POLDA'),
                'report_date'     => now()->format('Y-m-d'),
                'report_time'     => now()->format('H.i') . ' WITA',
                'executive_brief' => mb_substr($rawInput, 0, 500),
                'top_concern'     => 'Hasil analisis mandiri intelijen media dari ChatGPT.',
                'sentiment_national' => ['positif' => 60, 'netral' => 30, 'negatif' => 10],
                'sentiment_sulbar'   => ['positif' => 55, 'netral' => 30, 'negatif' => 15],
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
                'positif' => $pos !== null ? $pos : 55,
                'netral'  => $net !== null ? $net : 30,
                'negatif' => $neg !== null ? $neg : 15,
            ];
        };

        $natIssues = $parsed['national_issues'] ?? [];
        $sulIssues = $parsed['sulbar_issues'] ?? [];

        MediaIntelligenceReport::create([
            'polda_id'                 => $polda->id,
            'report_date'              => $parsed['report_date'] ?? now()->format('Y-m-d'),
            'report_time'              => $parsed['report_time'] ?? now()->format('H.i') . ' WITA',
            'title'                    => $parsed['title'] ?? ('LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK ' . $polda->name),
            'snapshot_national_status' => $parsed['snapshot_national_status'] ?? 'KUNING - WASPADA',
            'snapshot_national_desc'   => $parsed['snapshot_national_desc'] ?? 'Dinamika isu nasional Polri relatif stabil dengan pengawasan ruang siber.',
            'snapshot_sulbar_status'   => $parsed['snapshot_sulbar_status'] ?? 'MERAH - PRIORITAS TINGGI',
            'snapshot_sulbar_desc'     => $parsed['snapshot_sulbar_desc'] ?? 'Perlu akselerasi mitigasi isu lokal di wilayah pesisir dan pengamanan tahapan Pilkada.',
            'top_concern'              => $parsed['top_concern'] ?? 'Perhatian khusus pimpinan terhadap situasi Harkamtibmas terkini.',
            'executive_brief'          => $parsed['executive_brief'] ?? $rawInput,
            'core_decision'            => $parsed['core_decision'] ?? 'Tingkatkan patroli gabungan dan klarifikasi satu pintu oleh Kabid Humas.',
            'sentiment_national'       => $calcSentiment($parsed['sentiment_national'] ?? [], $natIssues),
            'sentiment_sulbar'         => $calcSentiment($parsed['sentiment_sulbar'] ?? [], $sulIssues),
            'priority_responses'       => $parsed['priority_responses'] ?? [
                'response_0_2h' => ['title' => 'Validasi Fakta', 'action' => 'Koordinasi satu pintu dengan Satker/Polres terkait.'],
                'response_2_6h' => ['title' => 'Konsolidasi & Klarifikasi', 'action' => 'Siapkan rilis pers dan materi counter narasi.'],
                'response_24h'  => ['title' => 'Amplifikasi Citra Positif', 'action' => 'Masifikasi publikasi humanis dan bansos kepolisian.'],
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
     * Generate dynamic AI Prompt based on selected Polda.
     */
    protected function generateDynamicPrompt(?Polda $polda): string
    {
        $name = $polda?->name ?? 'Polda Sulawesi Barat';
        $short = $polda?->code === 'SULBAR' ? 'Polda Sulbar' : ($polda?->name ?? 'Polda');
        $tz = 'WITA';
        $polresList = $polda && $polda->polres ? $polda->polres->pluck('name')->implode(', ') : 'Polresta Mamuju, Polres Majene, Polres Polewali Mandar, Polres Mamasa, Polres Pasangkayu, Polres Mamuju Tengah';

        return <<<PROMPT
ROLE: Bertindak sebagai Senior Media Intelligence Analyst dan Strategic Communication Advisor untuk Polri, khususnya {$name} ({$short}).

TUGAS HARIAN:
Lakukan pemantauan pemberitaan terbaru menggunakan tanggal dan waktu aktual ({$tz}), dengan dua cakupan:
1. NASIONAL: Seluruh isu strategis, trending, sentimen, prestasi, dan krisis reputasi terkait Polri.
2. {$short}: Pemberitaan khusus {$name}, jajaran Satwil ({$polresList}), isu kamtibmas lokal, pelayanan masyarakat, kritik publik, serta isu nasional yang berdampak terhadap wilayah hukum {$short}.

SUMBER:
Telusuri media online nasional dan lokal daerah {$short}, Google News, X, Instagram, TikTok, Facebook, YouTube, serta situs resmi Polri. Prioritaskan informasi 24 jam terakhir dan sertakan tautan sumber serta waktu publikasi ({$tz}).

KETENTUAN PENTING:
- Angka sentimen WAJIB berupa angka bulat (integer) persen dan TIDAK BOLEH bernilai null (contoh: positif: 60, netral: 30, negatif: 10, total harus 100%).
- Berikan narasi tegas dan lugas pada executive_brief, snapshot, dan langkah mitigasi.

OUTPUT WAJIB:
Sajikan laporan HANYA dalam blok JSON murni terstruktur (di dalam blok ```json ... ```) tanpa teks pendahuluan atau penutup agar langsung terurai otomatis dan tersaji mewah di SIMOD HUMAS:

{
  "title": "LAPORAN INTELIJEN MEDIA & SENTIMEN OPINI PUBLIK {$short}",
  "report_date": "YYYY-MM-DD",
  "report_time": "HH.MM {$tz}",
  "top_concern": "Ringkasan isu paling mendesak yang butuh perhatian pimpinan hari ini...",
  "executive_brief": "Ringkasan maksimal 200 kata mengenai situasi pemberitaan Polri nasional dan {$short}, termasuk isu paling mendesak bagi pimpinan...",
  "snapshot_national_status": "KUNING - WASPADA / HIJAU - KONDUSIF / MERAH - ATENSI",
  "snapshot_national_desc": "Uraian 1-2 kalimat narasi kunci yang menggerakkan sentimen nasional Polri hari ini...",
  "snapshot_sulbar_status": "MERAH - PRIORITAS TINGGI / KUNING - WASPADA / HIJAU - KONDUSIF",
  "snapshot_sulbar_desc": "Uraian 1-2 kalimat narasi kunci yang memengaruhi sentimen wilayah {$name} hari ini...",
  "sentiment_national": { "positif": 60, "netral": 30, "negatif": 10 },
  "sentiment_sulbar": { "positif": 55, "netral": 30, "negatif": 15 },
  "priority_responses": {
    "response_0_2h": {
      "title": "Validasi Fakta & Tindak Cepat",
      "action": "Aksi taktis 0-2 jam koordinasi Satfung/Polres terkait untuk validasi fakta riil dan satu pintu juru bicara..."
    },
    "response_2_6h": {
      "title": "Konsolidasi Data & Klarifikasi Terukur",
      "action": "Aksi 2-6 jam menyusun rilis pers resmi, FAQ terverifikasi, dan narasi bantahan proporsional..."
    },
    "response_24h": {
      "title": "Amplifikasi Citra & Publikasi Positif",
      "action": "Aksi 24 jam masifikasi konten humanis, pelayanan prima SIM/lalu lintas, dan patroli siber..."
    }
  },
  "national_issues": [
    {
      "title": "Judul Isu / Berita Nasional 1",
      "source": "Nama Media | HH.MM {$tz}",
      "url": "https://tautan-berita-sumber.com",
      "fact": "Salinan ringkasan isi berita dan fakta peristiwa secara lengkap...",
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
      "sentiment": "Positif/Netral/Negatif",
      "virality": "Tinggi/Sedang/Rendah",
      "risk": "Hijau/Kuning/Merah"
    }
  ],
  "early_warnings": [
    {
      "level": "MERAH / KUNING",
      "title": "Isu Negatif Berisiko",
      "fact": "Fakta peristiwa dan alasan potensi eskalasi...",
      "risk": "Dampak terhadap reputasi Polri...",
      "response_0_2h": "Respon taktis 0-2 jam...",
      "response_2_6h": "Konsolidasi data 2-6 jam...",
      "response_6_24h": "Rilis klarifikasi / penanganan 24 jam...",
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
      "goal": "Tujuan Komunikasi Publik",
      "pic": "Satuan / Humas"
    }
  ],
  "recommendations": [
    {
      "target": "1 | 0-24 jam",
      "issue": "Isu Prioritas",
      "action": "Langkah Komunikasi Konkret",
      "pic": "PIC yang Disarankan",
      "indicator": "Indikator Keberhasilan"
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
}
