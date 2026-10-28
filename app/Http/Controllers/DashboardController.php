<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarning;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\Polres;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $allPoldas = Polda::where('is_active', true)->withCount('polres')->get();

        // Selected Tenant (Default to Sulbar or query param)
        $selectedCode = $request->query('polda', 'SULBAR');
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::with(['polres' => function ($q) {
                $q->where('is_active', true);
            }])->where('code', $selectedCode)->first() ?? $allPoldas->first();
        }

        // Query EWS based on tenant
        $ewsQuery = EarlyWarning::with(['polda', 'polres'])->where('status', 'active');
        if ($selectedPolda) {
            $ewsQuery->where('polda_id', $selectedPolda->id);
        }
        $earlyWarnings = $ewsQuery->latest()->get();

        // Query Media Feeds
        $feedsQuery = MediaFeed::with('polda')->where('is_active', true);
        if ($selectedPolda) {
            $feedsQuery->where('polda_id', $selectedPolda->id);
        }
        $mediaFeeds = $feedsQuery->get();

        // Mock news items for demo showcase
        $sampleArticles = [
            [
                'id'          => 1,
                'title'       => 'Polda Tingkatkan Patroli Siber Jelang Pilkada Serentak di Kawasan Pesisir',
                'source_name' => 'ANTARA News',
                'category'    => 'Harkamtibmas',
                'pub_date'    => '28 Okt 2026, 20.30 WITA',
                'summary'     => 'Direktorat Reserse Kriminal Khusus dan Bidhumas mengintensifkan patroli siber memantau disinformasi, ujaran kebencian, serta narasi provokatif di media sosial.',
                'sentiment'   => 'positif',
                'is_bookmarked' => false,
            ],
            [
                'id'          => 2,
                'title'       => 'Operasi Patuh Marano Berjalan Humanis, Pelanggaran Menurun 24 Persen',
                'source_name' => 'Tribun Regional',
                'category'    => 'Lalu Lintas',
                'pub_date'    => '28 Okt 2026, 19.45 WITA',
                'summary'     => 'Ditlantas bersama jajaran Polres mengedepankan teguran simpatik dan edukasi helm SNI kepada pengguna jalan jalur trans Sulawesi.',
                'sentiment'   => 'positif',
                'is_bookmarked' => false,
            ],
            [
                'id'          => 3,
                'title'       => 'Keluhan Antrean SPBU Ditanggapi Cepat: Pengawasan Penyaluran BBM Subsidi Diperketat',
                'source_name' => 'Detikcom',
                'category'    => 'Ekonomi & Logistik',
                'pub_date'    => '28 Okt 2026, 18.15 WITA',
                'summary'     => 'Kapolres bersama Pertamina mengecek langsung tera dispenser dan nomor kendaraan pengantre guna mengantisipasi dugaan pelangsiran solar.',
                'sentiment'   => 'netral',
                'is_bookmarked' => false,
            ],
        ];

        // System metrics
        $metrics = [
            'total_poldas'   => $allPoldas->count(),
            'total_polres'   => Polres::where('is_active', true)->count(),
            'active_ews'     => $earlyWarnings->count(),
            'ews_red'        => $earlyWarnings->where('level', 'red')->count(),
            'ews_yellow'     => $earlyWarnings->where('level', 'yellow')->count(),
            'ews_green'      => $earlyWarnings->where('level', 'green')->count(),
            'server_wita'    => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return Inertia::render('Dashboard', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'earlyWarnings'  => $earlyWarnings,
            'mediaFeeds'     => $mediaFeeds,
            'sampleArticles' => $sampleArticles,
            'metrics'        => $metrics,
        ]);
    }
}
