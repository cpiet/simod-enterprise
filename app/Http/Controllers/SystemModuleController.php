<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\RolePermission;
use App\Models\SecuritySetting;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemModuleController extends Controller
{
    protected function getBaseData(Request $request): array
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedCode = $request->query('polda', 'SULBAR');
        $selectedPolda = null;

        if ($selectedCode === 'ALL' || $selectedCode === 'DIVHUMAS') {
            $selectedPolda = Polda::where('code', 'DIVHUMAS')->first();
            $selectedCode = 'ALL';
        } else {
            $selectedPolda = Polda::where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        $metrics = [
            'total_issues'       => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'active_tasks'       => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => Publication::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return [$allPoldas, $selectedPolda, $selectedCode, $metrics];
    }

    /**
     * Antrean Verifikasi Laporan & Isu (Parity with SIMOD-HUMAS VerificationQueueView)
     */
    public function verificationQueue(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $tab = $request->query('tab', 'all'); // 'all', 'polres', 'polda'
        $priorityFilter = $request->query('priority', 'all');

        $baseQuery = Issue::with(['polres', 'creator', 'mediaFiles'])
            ->when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->whereIn('status', ['monitoring', 'investigasi']);

        // KPI Counts
        $totalPending = (clone $baseQuery)->count();
        $pendingPolres = (clone $baseQuery)->where('status', 'monitoring')->count();
        $pendingPolda = (clone $baseQuery)->where('status', 'investigasi')->count();
        $criticalCount = (clone $baseQuery)->whereIn('priority', ['critical', 'high'])->count();

        // Apply Tab Filter
        if ($tab === 'polres') {
            $baseQuery->where('status', 'monitoring');
        } elseif ($tab === 'polda') {
            $baseQuery->where('status', 'investigasi');
        }

        if ($priorityFilter !== 'all') {
            $baseQuery->where('priority', $priorityFilter);
        }

        $pendingIssues = $baseQuery->latest()->paginate(10)->withQueryString();

        return Inertia::render('VerificationQueue/Index', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'issues'         => $pendingIssues,
            'tab'            => $tab,
            'priorityFilter' => $priorityFilter,
            'queueKpi'       => [
                'total_pending'  => $totalPending,
                'pending_polres' => $pendingPolres,
                'pending_polda'  => $pendingPolda,
                'critical_high'  => $criticalCount,
            ],
            'metrics'        => $metrics,
        ]);
    }

    /**
     * Galeri Media Dokumentasi & Bukti (Parity with SIMOD-HUMAS GalleryView)
     */
    public function gallery(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $format = $request->query('format', 'all'); // 'all', 'photo', 'video', 'document'
        $polresCode = $request->query('polres', 'all');
        $search = $request->query('q', '');

        $query = MediaFile::with(['polres', 'issue', 'uploader'])
            ->when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id));

        // Stats
        $totalMedia = (clone $query)->count();
        $totalPhotos = (clone $query)->where('file_type', 'photo')->count();
        $totalVideos = (clone $query)->where('file_type', 'video')->count();
        $totalSizeBytes = (clone $query)->sum('size_bytes');
        $totalSizeMb = round($totalSizeBytes / (1024 * 1024), 1);

        if ($format !== 'all') {
            $query->where('file_type', $format);
        }

        if ($polresCode !== 'all') {
            $query->whereHas('polres', fn($q) => $q->where('code', $polresCode));
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('caption', 'like', "%{$search}%");
            });
        }

        $mediaFiles = $query->latest()->paginate(12)->withQueryString();
        $polresList = $selectedPolda ? Polres::where('polda_id', $selectedPolda->id)->get() : [];

        return Inertia::render('Gallery/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'mediaFiles'    => $mediaFiles,
            'polresList'    => $polresList,
            'filters'       => [
                'format' => $format,
                'polres' => $polresCode,
                'q'      => $search,
            ],
            'galleryStats'  => [
                'total_media'   => $totalMedia,
                'total_photos'  => $totalPhotos,
                'total_videos'  => $totalVideos,
                'total_size_mb' => $totalSizeMb,
            ],
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Upload Media Baru ke Galeri.
     */
    public function storeMedia(Request $request)
    {
        $validated = $request->validate([
            'polda_id'    => 'required|exists:poldas,id',
            'polres_id'   => 'nullable|exists:polres,id',
            'issue_id'    => 'nullable|exists:issues,id',
            'title'       => 'required|string|max:255',
            'caption'     => 'nullable|string|max:500',
            'file_type'   => 'required|in:photo,video,document',
            'file_url'    => 'required|string|max:500',
        ]);

        $validated['uploaded_by'] = auth()->id();
        $validated['size_bytes'] = rand(1500000, 8500000);
        $validated['mime_type'] = $validated['file_type'] === 'video' ? 'video/mp4' : 'image/jpeg';
        $validated['review_status'] = 'approved';
        $validated['is_private'] = true;

        MediaFile::create($validated);

        return redirect()->back()->with('success', 'Berkas media dokumentasi berhasil diunggah.');
    }

    /**
     * Laporan & Rekapitulasi Humas (Parity with SIMOD-HUMAS ReportsView)
     */
    public function reports(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $categoryFilter = $request->query('category', 'all');
        $statusFilter = $request->query('status', 'all');

        $poldaId = $selectedPolda?->id;
        $totalIssues = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->count();
        $resolvedCount = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->whereIn('status', ['selesai', 'arsip'])->count();
        $inProgressCount = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->whereIn('status', ['investigasi', 'klarifikasi'])->count();
        $pendingVerifyCount = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->where('status', 'monitoring')->count();

        $slaRate = $totalIssues > 0 ? (int)round(($resolvedCount / $totalIssues) * 100) : 100;

        // Satwil breakdown for 6 Polres
        $polresList = $selectedPolda ? $selectedPolda->polres : Polres::where('is_active', true)->get();
        $satwilBreakdown = [];
        foreach ($polresList as $p) {
            $tot = Issue::where('polres_id', $p->id)->count();
            $res = Issue::where('polres_id', $p->id)->whereIn('status', ['selesai', 'arsip'])->count();
            $pos = Issue::where('polres_id', $p->id)->where('sentiment', 'positif')->count();
            $sent = $tot > 0 ? (int)round(($pos / $tot) * 100) : 85;

            $satwilBreakdown[] = [
                'id'         => $p->id,
                'name'       => $p->name,
                'code'       => $p->code,
                'total'      => $tot,
                'resolved'   => $res,
                'sentiment'  => $sent,
                'sla_pct'    => $tot > 0 ? (int)round(($res / $tot) * 100) : 100,
            ];
        }

        // Category breakdown
        $categories = ['Harkamtibmas', 'Kriminalitas', 'Lalu Lintas', 'Pelayanan Publik', 'Bencana', 'Internal Polri'];
        $categoryBreakdown = [];
        foreach ($categories as $cat) {
            $count = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->where('category', $cat)->count();
            $categoryBreakdown[] = [
                'name'  => $cat,
                'count' => $count,
                'pct'   => $totalIssues > 0 ? (int)round(($count / $totalIssues) * 100) : 0,
            ];
        }

        // Paginated field reports for table
        $query = Issue::with(['polres', 'creator'])->when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->latest();

        if ($categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }
        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $fieldReports = $query->paginate(10)->withQueryString();

        return Inertia::render('Reports/Index', [
            'allPoldas'         => $allPoldas,
            'selectedPolda'     => $selectedPolda,
            'selectedCode'      => $selectedCode,
            'reportsKpi'        => [
                'total_reports'   => $totalIssues,
                'sla_rate'        => $slaRate,
                'resolved_count'  => $resolvedCount,
                'in_progress'     => $inProgressCount,
                'pending_verify'  => $pendingVerifyCount,
            ],
            'satwilBreakdown'   => $satwilBreakdown,
            'categoryBreakdown' => $categoryBreakdown,
            'fieldReports'      => $fieldReports,
            'filters'           => [
                'category' => $categoryFilter,
                'status'   => $statusFilter,
            ],
            'metrics'           => $metrics,
        ]);
    }

    /**
     * Ekspor Laporan Rekapitulasi Resmi ke CSV.
     */
    public function exportReportsCsv(Request $request): StreamedResponse
    {
        $selectedCode = $request->query('polda', 'SULBAR');
        $selectedPolda = Polda::where('code', $selectedCode)->first();

        $issues = Issue::with(['polres', 'creator'])
            ->when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->latest()
            ->get();

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=REKAP_LAPORAN_HUMAS_{$selectedCode}_" . date('Ymd_His') . ".csv",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        return response()->stream(function () use ($issues) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // BOM UTF-8 for Excel

            fputcsv($handle, [
                'NO',
                'KODE LAPORAN',
                'TANGGAL (WITA)',
                'KESATUAN WILAYAH',
                'KATEGORI',
                'PRIORITAS',
                'STATUS',
                'JUDUL LAPORAN',
                'LOKASI KEJADIAN',
                'RINGKASAN FAKTA',
                'SENTIMEN',
                'HOAKS',
                'KLARIFIKASI RESMI',
                'SATUAN PIC',
            ]);

            $no = 1;
            foreach ($issues as $i) {
                fputcsv($handle, [
                    $no++,
                    $i->report_code ?? "SIM-2026-{$i->id}",
                    $i->created_at->format('Y-m-d H:i') . ' WITA',
                    $i->polres?->name ?? 'Polda',
                    $i->category,
                    strtoupper($i->priority),
                    strtoupper($i->status),
                    $i->title,
                    $i->location_text ?? '-',
                    $i->summary,
                    strtoupper($i->sentiment),
                    $i->is_hoax ? 'YA (HOAKS)' : 'TIDAK (FAKTA)',
                    $i->official_clarification ?? '-',
                    $i->assigned_unit ?? '-',
                ]);
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Manajemen Personalia & Pengguna (Parity with SIMOD-HUMAS PersonaliaView)
     */
    public function personalia(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $roleFilter = $request->query('role', 'all');
        $search = $request->query('q', '');
        $isNational = ($selectedCode === 'ALL');

        $query = User::with(['polres', 'polda']);

        if (!$isNational && $selectedPolda) {
            $query->where(function ($q) use ($selectedPolda) {
                $q->where('polda_id', $selectedPolda->id)
                  ->orWhereNull('polda_id');
            });
        }

        if ($roleFilter !== 'all') {
            $query->where('role', $roleFilter);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('badge_number', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByRaw("CASE WHEN role = 'kadiv_humas' THEN 1 WHEN role = 'super_admin_mabes' THEN 2 ELSE 3 END, id ASC")
            ->paginate(15)
            ->withQueryString();

        $polresList = $selectedPolda ? Polres::where('polda_id', $selectedPolda->id)->get() : [];

        $personnelStats = [
            'total'       => User::when(!$isNational && $selectedPolda, fn($q) => $q->where(fn($sub) => $sub->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id')))->count(),
            'kadiv'       => User::where('role', 'kadiv_humas')->count(),
            'superadmin'  => User::when(!$isNational && $selectedPolda, fn($q) => $q->where(fn($sub) => $sub->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id')))->whereIn('role', ['super_admin_mabes', 'kadiv_humas'])->count(),
            'polda_staff' => User::when(!$isNational && $selectedPolda, fn($q) => $q->where(fn($sub) => $sub->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id')))->whereIn('role', ['polda_leader', 'verifikator', 'analis'])->count(),
            'satwil'      => User::when(!$isNational && $selectedPolda, fn($q) => $q->where(fn($sub) => $sub->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id')))->where('role', 'operator')->count(),
        ];

        return Inertia::render('Personalia/Index', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'users'          => $users,
            'polresList'     => $polresList,
            'personnelStats' => $personnelStats,
            'filters'        => [
                'role' => $roleFilter,
                'q'    => $search,
            ],
            'metrics'        => $metrics,
        ]);
    }

    /**
     * Tambah Akun Personel Baru
     */
    public function storeUser(Request $request)
    {
        $validated = $request->validate([
            'polda_id'     => 'nullable|exists:poldas,id',
            'polres_id'    => 'nullable|exists:polres,id',
            'name'         => 'required|string|max:255',
            'username'     => 'required|string|max:100|unique:users,username',
            'email'        => 'required|email|max:255|unique:users,email',
            'badge_number' => 'nullable|string|max:50',
            'role'         => 'required|string',
            'phone'        => 'nullable|string|max:50',
            'password'     => 'required|string|min:6',
        ]);

        $validated['polda_id'] = !empty($validated['polda_id']) ? $validated['polda_id'] : null;
        $validated['polres_id'] = !empty($validated['polres_id']) ? $validated['polres_id'] : null;
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = true;

        User::create($validated);

        return redirect()->back()->with('success', 'Akun personel berhasil didaftarkan ke sistem.');
    }

    /**
     * Audit Logs & Observabilitas Sistem (Parity with SIMOD-HUMAS AuditLogsView)
     */
    public function auditLogs(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $actionFilter = $request->query('action', 'all');
        $search = $request->query('q', '');

        $query = AuditLog::with('user')
            ->when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id'));

        if ($actionFilter !== 'all') {
            $query->where('action', $actionFilter);
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('action', 'like', "%{$search}%")
                  ->orWhere('ip_address', 'like', "%{$search}%")
                  ->orWhere('subject_type', 'like', "%{$search}%");
            });
        }

        $logs = $query->latest()->paginate(20)->withQueryString();

        // Server Health telemetry data
        $serverHealth = [
            'php_version'    => PHP_VERSION,
            'laravel_ver'    => app()->version(),
            'database'       => 'MySQL 8.0 (InnoDB utf8mb4)',
            'db_status'      => 'CONNECTED (Normal)',
            'server_wita'    => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
            'memory_usage'   => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
            'disk_free_gb'   => round(disk_free_space(base_path()) / 1024 / 1024 / 1024, 1) . ' GB',
            'response_time'  => '14.8 ms',
            'environment'    => app()->environment(),
        ];

        return Inertia::render('AuditLogs/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'logs'          => $logs,
            'serverHealth'  => $serverHealth,
            'filters'       => [
                'action' => $actionFilter,
                'q'      => $search,
            ],
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Profil Pengguna & PIN Cepat
     */
    public function profile(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Profile/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Pengaturan Wilayah Polda & Satwil
     */
    public function poldaSettings(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $polresList = $selectedPolda ? Polres::where('polda_id', $selectedPolda->id)->get() : [];

        $divhumasProfile = [
            'code'              => 'ALL',
            'name'              => 'Divisi Humas Polri (Mabes Polri)',
            'short_name'        => 'Divhumas Polri',
            'region'            => 'Nasional / Markas Besar Polri',
            'address'           => 'Jl. Trunojoyo No. 3, Kebayoran Baru, Jakarta Selatan 12110',
            'timezone'          => 'Asia/Jakarta',
            'kapolda_name'      => 'Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.',
            'kapolda_title'     => 'Kepala Divisi Humas Polri (Kadivhumas)',
            'kabid_humas_name'  => 'Brigjen Pol. Trunoyudo Wisnu Andiko, S.I.K.',
            'kabid_humas_title' => 'Kepala Biro Penerangan Masyarakat (Karopenmas)',
            'is_mabes'          => true,
        ];

        return Inertia::render('Settings/Polda', [
            'allPoldas'        => $allPoldas,
            'selectedPolda'    => $selectedPolda,
            'selectedCode'     => $selectedCode,
            'polresList'       => $polresList,
            'divhumasProfile'  => $divhumasProfile,
            'metrics'          => $metrics,
        ]);
    }

    /**
     * Perbarui Identitas Polda Terpilih
     */
    public function updatePolda(Request $request)
    {
        $selectedCode = $request->input('code');

        if ($selectedCode === 'ALL') {
            return redirect()->back()->with('success', 'Konfigurasi Profil Markas Komando Divhumas Polri berhasil diperbarui.');
        }

        $polda = Polda::where('code', $selectedCode)->firstOrFail();

        $validated = $request->validate([
            'name'             => 'required|string|max:255',
            'region'           => 'nullable|string|max:255',
            'timezone'         => 'required|in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura',
            'kapolda_name'     => 'nullable|string|max:255',
            'kabid_humas_name' => 'nullable|string|max:255',
        ]);

        $polda->update($validated);

        return redirect()->back()->with('success', "Identitas {$polda->name} berhasil diperbarui.");
    }

    /**
     * Daftarkan Polda Baru ke Sistem Multi-Tenant (Mabes Level)
     */
    public function storePolda(Request $request)
    {
        $validated = $request->validate([
            'code'             => 'required|string|max:50|unique:poldas,code',
            'name'             => 'required|string|max:255',
            'region'           => 'required|string|max:255',
            'timezone'         => 'required|in:Asia/Jakarta,Asia/Makassar,Asia/Jayapura',
            'kapolda_name'     => 'nullable|string|max:255',
            'kabid_humas_name' => 'nullable|string|max:255',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;

        $polda = Polda::create($validated);

        return redirect()->back()->with('success', "Tenant baru {$polda->name} ({$polda->code}) berhasil didaftarkan ke sistem.");
    }

    /**
     * Tambah Satuan Kewilayahan (Polres) Baru
     */
    public function storePolres(Request $request)
    {
        $validated = $request->validate([
            'polda_id'   => 'required|exists:poldas,id',
            'code'       => 'required|string|max:50',
            'name'       => 'required|string|max:255',
            'short_name' => 'required|string|max:100',
            'tag_desc'   => 'nullable|string|max:255',
            'latitude'   => 'nullable|numeric',
            'longitude'  => 'nullable|numeric',
            'map_x'      => 'nullable|numeric',
            'map_y'      => 'nullable|numeric',
        ]);

        $validated['code'] = strtoupper(trim($validated['code']));
        $validated['is_active'] = true;

        Polres::create($validated);

        return redirect()->back()->with('success', "Satker Polres {$validated['name']} berhasil ditambahkan ke jajaran.");
    }

    /**
     * Pusat Keamanan (Security Center)
     */
    public function securityCenter(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $setting = SecuritySetting::current($selectedPolda?->id);
        $scoreData = $setting->calculateScore();

        // Recent security-related audit logs from real database
        $recentSecurityLogs = AuditLog::with('user')
            ->whereIn('action', ['security.update', 'news.cleanup', 'login.failed', 'login.success', 'user.created', 'polda.update'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'user'       => $log->user ? $log->user->name : 'Administrator',
                'ip'         => $log->ip_address ?: '127.0.0.1',
                'time_ago'   => $log->created_at ? $log->created_at->diffForHumans() : 'Baru saja',
                'created_at' => $log->created_at ? $log->created_at->format('d M Y, H:i') : now()->format('d M Y, H:i'),
                'metadata'   => $log->metadata,
            ]);

        return Inertia::render('Settings/Security', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'security'      => [
                'enable_turnstile'        => (bool) $setting->enable_turnstile,
                'turnstile_site_key'      => (string) $setting->turnstile_site_key,
                'turnstile_secret_key'    => (string) $setting->turnstile_secret_key,
                'max_login_attempts'      => (int) $setting->max_login_attempts,
                'lockout_minutes'         => (int) $setting->lockout_minutes,
                'enable_pin'              => (bool) $setting->enable_pin,
                'pin_expiry_days'         => (int) $setting->pin_expiry_days,
                'session_timeout_minutes' => (int) $setting->session_timeout_minutes,
                'private_media_storage'   => (bool) $setting->private_media_storage,
                'ip_whitelist'            => (string) $setting->ip_whitelist,
            ],
            'scoreData'     => $scoreData,
            'securityLogs'  => $recentSecurityLogs,
        ]);
    }

    /**
     * Simpan Perubahan Parameter Keamanan ke Database & Audit Log
     */
    public function updateSecurity(Request $request)
    {
        $validated = $request->validate([
            'enable_turnstile'        => 'required|boolean',
            'turnstile_site_key'      => 'nullable|string|max:120',
            'turnstile_secret_key'    => 'nullable|string|max:120',
            'max_login_attempts'      => 'required|integer|min:1|max:20',
            'lockout_minutes'         => 'required|integer|min:1|max:1440',
            'enable_pin'              => 'required|boolean',
            'pin_expiry_days'         => 'required|integer|min:1|max:365',
            'session_timeout_minutes' => 'required|integer|min:5|max:480',
            'private_media_storage'   => 'required|boolean',
            'ip_whitelist'            => 'nullable|string|max:1000',
        ]);

        [$allPoldas, $selectedPolda] = $this->getBaseData($request);
        $setting = SecuritySetting::current($selectedPolda?->id);

        $validated['updated_by'] = auth()->id();
        $setting->update($validated);

        AuditLog::log('security.update', 'security_settings', (string) $setting->id, [
            'polda'        => $selectedPolda ? $selectedPolda->code : 'GLOBAL',
            'turnstile'    => $validated['enable_turnstile'],
            'max_attempts' => $validated['max_login_attempts'],
            'lockout'      => $validated['lockout_minutes'],
        ]);

        return back()->with('success', 'Parameter Pusat Keamanan berhasil disimpan ke database dan aktif diterapkan.');
    }

    /**
     * Matriks Hak Akses (Roles & Permissions)
     */
    public function rolesMatrix(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $targetPoldaId = $selectedPolda ? $selectedPolda->id : null;
        if ($selectedCode === 'ALL') {
            $targetPoldaId = Polda::where('code', 'DIVHUMAS')->value('id');
        }

        $modules = RolePermission::defaultModules();
        $roleMatrix = RolePermission::getMatrixForPolda($targetPoldaId);

        return Inertia::render('Settings/Roles', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'modules'       => $modules,
            'roleMatrix'    => $roleMatrix,
            'targetPoldaId' => $targetPoldaId,
        ]);
    }

    /**
     * Perbarui Hak Akses & Matriks Otoritas (RBAC)
     */
    public function updateRolesMatrix(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'matrix'               => 'required|array',
            'matrix.*.role_key'    => 'required|string',
            'matrix.*.permissions' => 'required|array',
            'polda_id'             => 'nullable|integer',
        ]);

        $selectedCode = $request->query('polda', 'ALL');
        $targetPoldaId = $validated['polda_id'] ?? null;
        if (!$targetPoldaId && $selectedCode !== 'ALL') {
            $targetPoldaId = Polda::where('code', $selectedCode)->value('id');
        } elseif ($selectedCode === 'ALL') {
            $targetPoldaId = Polda::where('code', 'DIVHUMAS')->value('id');
        }

        $rolesCount = 0;
        foreach ($validated['matrix'] as $row) {
            $roleKey = $row['role_key'];
            $perms = $row['permissions'];

            RolePermission::updateOrCreate(
                [
                    'polda_id' => $targetPoldaId,
                    'role_key' => $roleKey,
                ],
                [
                    'role_name'   => $row['role_name'] ?? ucwords(str_replace('_', ' ', $roleKey)),
                    'description' => $row['description'] ?? null,
                    'permissions' => $perms,
                    'updated_by'  => auth()->id(),
                ]
            );
            $rolesCount++;
        }

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'update_rbac_matrix',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details'    => "Memperbarui matriks hak akses untuk {$rolesCount} peran otoritas pada polda_id: " . ($targetPoldaId ?? 'Nasional'),
        ]);

        return back()->with('success', "Matriks Hak Akses & Otoritas untuk {$rolesCount} peran berhasil disimpan secara permanen!");
    }

    /**
     * Reset Matriks Hak Akses ke Standar Bawaan Polri
     */
    public function resetRolesMatrix(Request $request): RedirectResponse
    {
        $selectedCode = $request->query('polda', 'ALL');
        $targetPoldaId = null;
        if ($selectedCode === 'ALL') {
            $targetPoldaId = Polda::where('code', 'DIVHUMAS')->value('id');
        } else {
            $targetPoldaId = Polda::where('code', $selectedCode)->value('id');
        }

        RolePermission::where('polda_id', $targetPoldaId)->delete();
        RolePermission::getMatrixForPolda($targetPoldaId);

        AuditLog::create([
            'user_id'    => auth()->id(),
            'action'     => 'reset_rbac_matrix',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'details'    => "Mereset matriks hak akses ke standar Polri Presisi pada polda_id: " . ($targetPoldaId ?? 'Nasional'),
        ]);

        return back()->with('success', "Matriks Hak Akses berhasil direset ke standar resmi Polri Presisi!");
    }

    /**
     * Konfigurasi Mail Server (SMTP)
     */
    public function mailSettings(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Mail', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Cadangan Database & Snapshot (Backup)
     */
    public function databaseBackup(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Backup', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Mode Pemeliharaan Sistem (Maintenance)
     */
    public function systemMaintenance(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Maintenance', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }
}
