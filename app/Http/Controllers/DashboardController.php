<?php

namespace App\Http\Controllers;

use App\Models\EarlyWarning;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Issue;
use App\Models\PressRelease;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Display the Command Center Intelligence Dashboard.
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

        // Query Early Warnings scoped to active Polda
        $ewsQuery = EarlyWarning::query();
        if ($selectedPolda) {
            $ewsQuery->where('polda_id', $selectedPolda->id);
        }
        $earlyWarnings = $ewsQuery->orderByDesc('level')->get();

        // Query Media Feeds
        $feedsQuery = MediaFeed::where('is_active', true);
        if ($selectedPolda) {
            $feedsQuery->where('polda_id', $selectedPolda->id);
        }
        $mediaFeeds = $feedsQuery->get();

        // Rich news items matching Humas operational feeds
        $sampleArticles = [
            [
                'id'             => 1,
                'title'          => 'Bakom: Pembangunan karakter penting siapkan generasi muda masa depan',
                'source_name'    => 'ANTARA NEWS TERKINI',
                'category'       => 'Nasional',
                'category_badge' => 'NASIONAL',
                'time_ago'       => '1 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.28 WITA',
                'summary'        => 'Kepala Badan Komunikasi Pemerintah (Bakom) RI Syahganda Nainggolan mengatakan pemerintah menempatkan pembangunan karakter sebagai fondasi stabilitas nasional.',
                'image_url'      => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&auto=format&fit=crop&q=80',
                'link'           => 'https://antaranews.com',
                'sentiment'      => 'positif',
            ],
            [
                'id'             => 2,
                'title'          => 'HUAWEI WATCH D3 Debut di Ajang ISH-ECS 2026, Hadirkan Teknologi Pemantauan Tekan...',
                'source_name'    => 'ANTARA NEWS TERKINI',
                'category'       => 'Nasional',
                'category_badge' => 'NASIONAL',
                'time_ago'       => '1 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.28 WITA',
                'summary'        => 'Ajang 31st International Society of Hypertension Scientific Meeting and 17th Emirates Cardiac Society Annual memperkenalkan terobosan sensor medis wearable.',
                'image_url'      => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80',
                'link'           => 'https://antaranews.com',
                'sentiment'      => 'netral',
            ],
            [
                'id'             => 3,
                'title'          => 'Nicky Astria akan gelar konser tandai 42 tahun bermusik',
                'source_name'    => 'ANTARA NEWS TERKINI',
                'category'       => 'Nasional',
                'category_badge' => 'NASIONAL',
                'time_ago'       => '2 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.27 WITA',
                'summary'        => 'Penyanyi Nicky Astria akan menggelar konser bertajuk "Nicky Astria Level 42: Api Dalam Hening" dengan pengamanan ketat aparat kepolisian.',
                'image_url'      => 'https://images.unsplash.com/photo-1514525253161-7a46d19cd819?w=600&auto=format&fit=crop&q=80',
                'link'           => 'https://antaranews.com',
                'sentiment'      => 'positif',
            ],
            [
                'id'             => 4,
                'title'          => 'Momen Prabowo Terima Menlu Singapura di Hambalang',
                'source_name'    => 'DETIKCOM HUKUM & KRIMINAL',
                'category'       => 'Hukum & Kriminal',
                'category_badge' => 'HUKUM & KRIMINAL',
                'time_ago'       => '4 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.26 WITA',
                'summary'        => 'Presiden Prabowo Subianto menerima kunjungan kehormatan Menteri Luar Negeri Singapura Vivian Balakrishnan di Hambalang membahas stabilitas kawasan.',
                'image_url'      => 'https://images.unsplash.com/photo-1577495508048-b635879837f1?w=600&auto=format&fit=crop&q=80',
                'link'           => 'https://detik.com',
                'sentiment'      => 'positif',
            ],
            [
                'id'             => 5,
                'title'          => '11 Pengasuh Daycare Little Aresha DIY Divonis 2-3 Tahun Penjara',
                'source_name'    => 'CNN INDONESIA NASIONAL',
                'category'       => 'Hukum & Kriminal',
                'category_badge' => 'NASIONAL',
                'time_ago'       => '4 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.25 WITA',
                'summary'        => 'Majelis Hakim Pengadilan Negeri Sleman menjatuhkan vonis 2 hingga 3 tahun penjara kepada 11 pengasuh daycare setelah terbukti melakukan kelalaian.',
                'image_url'      => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&auto=format&fit=crop&q=80',
                'link'           => 'https://cnnindonesia.com',
                'sentiment'      => 'negatif',
            ],
            [
                'id'             => 6,
                'title'          => 'Ibas Tegaskan Pentingnya Program BSPS untuk Genjot Kesejahteraan Warga',
                'source_name'    => 'DETIKCOM HUKUM & KRIMINAL',
                'category'       => 'Hukum & Kriminal',
                'category_badge' => 'HUKUM & KRIMINAL',
                'time_ago'       => '5 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.25 WITA',
                'summary'        => 'Wakil Ketua MPR RI Edhie Baskoro Yudhoyono meninjau realisasi Bantuan Stimulan Perumahan Swadaya dan pemeliharaan harkamtibmas pedesaan.',
                'image_url'      => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&auto=format&fit=crop&q=80',
                'link'           => 'https://detik.com',
                'sentiment'      => 'positif',
            ],
            [
                'id'             => 7,
                'title'          => 'Polda Tingkatkan Patroli Siber Jelang Pilkada Serentak di Kawasan Pesisir',
                'source_name'    => 'BID HUMAS POLDA',
                'category'       => 'Harkamtibmas',
                'category_badge' => 'HARKAMTIBMAS',
                'time_ago'       => '10 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.20 WITA',
                'summary'        => 'Direktorat Reserse Kriminal Khusus dan Bidhumas mengintensifkan patroli siber memantau disinformasi, ujaran kebencian, serta narasi provokatif.',
                'image_url'      => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&auto=format&fit=crop&q=80',
                'link'           => '#',
                'sentiment'      => 'positif',
            ],
            [
                'id'             => 8,
                'title'          => 'Operasi Patuh Marano Berjalan Humanis, Pelanggaran Menurun 24 Persen',
                'source_name'    => 'TRIBUN REGIONAL',
                'category'       => 'Lalu Lintas',
                'category_badge' => 'LALU LINTAS',
                'time_ago'       => '15 menit lalu',
                'pub_date'       => '28 Oct 2026, 20.15 WITA',
                'summary'        => 'Ditlantas bersama jajaran Polres mengedepankan teguran simpatik dan edukasi helm SNI kepada pengguna jalan jalur trans Sulawesi.',
                'image_url'      => 'https://images.unsplash.com/photo-1449965408869-eaa3f722e40d?w=600&auto=format&fit=crop&q=80',
                'link'           => '#',
                'sentiment'      => 'positif',
            ],
        ];


        // Query Issues scoped to active Polda
        $issuesQuery = Issue::with(['polres'])->latest();
        if ($selectedPolda) {
            $issuesQuery->where('polda_id', $selectedPolda->id);
        }
        $issues = $issuesQuery->limit(10)->get();

        // Query Press Releases scoped to active Polda
        $pressReleasesQuery = PressRelease::with(['polres'])->latest();
        if ($selectedPolda) {
            $pressReleasesQuery->where('polda_id', $selectedPolda->id);
        }
        $pressReleases = $pressReleasesQuery->limit(5)->get();

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

        return Inertia::render('Dashboard', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'earlyWarnings'  => $earlyWarnings,
            'mediaFeeds'     => $mediaFeeds,
            'sampleArticles' => $sampleArticles,
            'issues'         => $issues,
            'pressReleases'  => $pressReleases,
            'metrics'        => $metrics,
        ]);
    }
}
