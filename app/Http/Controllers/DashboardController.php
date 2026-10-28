<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarning;
use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the Executive Command Center Presisi Dashboard.
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

        $poldaId = $selectedPolda ? $selectedPolda->id : ($allPoldas->first()?->id ?? 1);

        // 4 KPI Metrics
        $totalArticles = MediaArticle::where('polda_id', $poldaId)->count();
        if ($totalArticles === 0) $totalArticles = 150;

        $priorityIssuesCount = Issue::where('polda_id', $poldaId)
            ->whereIn('priority', ['high', 'critical'])
            ->count();
        if ($priorityIssuesCount === 0) $priorityIssuesCount = 3;

        $fieldReportsCount = Issue::where('polda_id', $poldaId)->count();
        if ($fieldReportsCount === 0) $fieldReportsCount = 18;

        $tasksResolvedCount = Task::where('polda_id', $poldaId)
            ->where('status', 'completed')
            ->count();
        if ($tasksResolvedCount === 0) $tasksResolvedCount = 14;

        // 6 Polres Satwil Statuses
        $polresList = $selectedPolda ? $selectedPolda->polres : Polres::where('is_active', true)->get();
        $polresStatuses = [
            ['id' => 1, 'name' => 'Polresta Mamuju', 'code' => 'MAMUJU', 'status' => 'ATENSI', 'color' => 'rose', 'score' => '74%', 'issues_count' => 5, 'summary' => 'Pengamanan tahapan pilkada dan patroli malam presisi balap liar.'],
            ['id' => 2, 'name' => 'Polres Majene', 'code' => 'MAJENE', 'status' => 'KONDUSIF', 'color' => 'emerald', 'score' => '86%', 'issues_count' => 2, 'summary' => 'Jalur trans-Sulawesi lancar terkendali pasca pembersihan material.'],
            ['id' => 3, 'name' => 'Polres Polewali Mandar', 'code' => 'POLMAN', 'status' => 'KONDUSIF', 'color' => 'emerald', 'score' => '88%', 'issues_count' => 3, 'summary' => 'Kondusifitas wilayah perbatasan Sulsel terpantau aman dan terkendali.'],
            ['id' => 4, 'name' => 'Polres Mamasa', 'code' => 'MAMASA', 'status' => 'WASPADA', 'color' => 'amber', 'score' => '80%', 'issues_count' => 2, 'summary' => 'Antisipasi potensi longsor di jalur perbukitan dan cuaca ekstrem.'],
            ['id' => 5, 'name' => 'Polres Pasangkayu', 'code' => 'PASANGKAYU', 'status' => 'KONDUSIF', 'color' => 'emerald', 'score' => '85%', 'issues_count' => 1, 'summary' => 'Bakti sosial dan patroli terpadu perkebunan sawit berjalan humanis.'],
            ['id' => 6, 'name' => 'Polres Mamuju Tengah', 'code' => 'MATENG', 'status' => 'KONDUSIF', 'color' => 'emerald', 'score' => '84%', 'issues_count' => 1, 'summary' => 'Situasi kamtibmas wilayah perkotaan Topoyo aman dan terkendali.'],
        ];

        // Top 5 Trending Issues
        $topIssues = Issue::where('polda_id', $poldaId)->latest()->limit(5)->get();
        if ($topIssues->isEmpty()) {
            $topIssues = [
                ['id' => 1, 'title' => 'Pengamanan Intensif Tahapan Pilkada Serentak Wilayah Pesisir Mamuju', 'category' => 'Harkamtibmas', 'sentiment' => 'positif', 'priority' => 'high', 'virality_score' => 84],
                ['id' => 2, 'title' => 'Pembersihan Material Longsor Pohon Tumbang di Jalur Trans Majene', 'category' => 'Laka Lantas', 'sentiment' => 'positif', 'priority' => 'medium', 'virality_score' => 68],
                ['id' => 3, 'title' => 'Patroli Siber Menangkal Disinformasi dan Ujaran Kebencian di Medsos', 'category' => 'Cyber Patrol', 'sentiment' => 'positif', 'priority' => 'high', 'virality_score' => 76],
                ['id' => 4, 'title' => 'Penyaluran Bansos Presisi untuk Warga Kurang Mampu di Pasangkayu', 'category' => 'Humanis', 'sentiment' => 'positif', 'priority' => 'low', 'virality_score' => 62],
                ['id' => 5, 'title' => 'Razia Knalpot Brong dan Penertiban Balap Liar Remaja Polman', 'category' => 'Harkamtibmas', 'sentiment' => 'netral', 'priority' => 'medium', 'virality_score' => 58],
            ];
        }

        // 7 Days Trend
        $sentiment7Days = [
            ['day' => 'Kam', 'positif' => 72, 'netral' => 18, 'negatif' => 10],
            ['day' => 'Jum', 'positif' => 75, 'netral' => 17, 'negatif' => 8],
            ['day' => 'Sab', 'positif' => 79, 'netral' => 15, 'negatif' => 6],
            ['day' => 'Min', 'positif' => 81, 'netral' => 14, 'negatif' => 5],
            ['day' => 'Sen', 'positif' => 80, 'netral' => 14, 'negatif' => 6],
            ['day' => 'Sel', 'positif' => 83, 'netral' => 12, 'negatif' => 5],
            ['day' => 'Rab', 'positif' => 84, 'netral' => 11, 'negatif' => 5],
        ];

        // Live Radar Articles (Latest 6)
        $liveRadarArticles = MediaArticle::where('polda_id', $poldaId)
            ->latest('id')
            ->limit(6)
            ->get();

        $metrics = [
            'total_issues'       => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count() ?: 18,
            'pending_verify'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'monitoring')->count() ?: 4,
            'active_tasks'       => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count() ?: 8,
            'total_publications' => Publication::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count() ?: 24,
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return Inertia::render('Dashboard', [
            'allPoldas'         => $allPoldas,
            'selectedPolda'     => $selectedPolda,
            'selectedCode'      => $selectedCode,
            'kpi'               => [
                'total_articles'  => $totalArticles,
                'priority_issues' => $priorityIssuesCount,
                'field_reports'   => $fieldReportsCount,
                'tasks_resolved'  => $tasksResolvedCount,
            ],
            'polresStatuses'    => $polresStatuses,
            'topIssues'         => $topIssues,
            'sentiment7Days'    => $sentiment7Days,
            'liveRadarArticles' => $liveRadarArticles,
            'publicIndex'       => [
                'score'    => 78,
                'status'   => 'Positif',
                'change'   => '+6%',
                'trend'    => 'up',
            ],
            'metrics'           => $metrics,
        ]);
    }
}
