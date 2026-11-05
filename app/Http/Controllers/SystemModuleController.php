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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SystemModuleController extends Controller
{
    /**
     * Antrean Verifikasi Laporan & Isu (Parity with SIMOD-HUMAS VerificationQueueView)
     */
    public function verificationQueue(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $tab = $request->query('tab', 'all'); // 'all', 'polres', 'polda'
        $priorityFilter = $request->query('priority', 'all');

        $isNational = $this->aggregatesNational($selectedCode);
        $baseQuery = Issue::with(['polres', 'creator', 'mediaFiles'])
            ->when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
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
        $isNational = $this->aggregatesNational($selectedCode);

        $format = $request->query('format', 'all'); // 'all', 'photo', 'video', 'document'
        $polresCode = $request->query('polres', 'all');
        $search = $request->query('q', '');

        $query = MediaFile::with(['polres', 'issue', 'uploader'])
            ->when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id));

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
            'file_url'    => 'required_without:file|nullable|string|max:500',
            'file'        => 'required_without:file_url|nullable|file|mimes:jpg,jpeg,png,webp,mp4,pdf|max:20480',
            'is_private'  => 'nullable|boolean',
        ]);

        $validated['polres_id'] = !empty($validated['polres_id']) ? $validated['polres_id'] : null;
        $validated['issue_id'] = !empty($validated['issue_id']) ? $validated['issue_id'] : null;
        $validated['uploaded_by'] = auth()->id();

        if ($request->user() && !$request->user()->isSuperAdmin() && $request->user()->polda_id) {
            $validated['polda_id'] = $request->user()->polda_id;
        }

        $validated['review_status'] = 'approved';
        $validated['is_private'] = $request->boolean('is_private', true);

        if ($request->hasFile('file') && $request->file('file')->isValid()) {
            // Berkas nyata: simpan ke disk publik, ukuran & tipe diambil dari berkas itu sendiri.
            $file = $request->file('file');
            $mime = $file->getMimeType();
            $path = $file->storeAs('uploads/gallery/' . $validated['polda_id'], \Illuminate\Support\Str::random(24) . '.' . $file->getClientOriginalExtension(), 'public');

            $validated['file_path'] = $path;
            $validated['file_url'] = '/storage/' . $path;
            $validated['size_bytes'] = $file->getSize();
            $validated['mime_type'] = $mime;
            $validated['file_type'] = str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'image/') ? 'photo' : 'document');
        } else {
            // Tautan eksternal: ukuran tidak diketahui (0), jangan diisi angka karangan.
            $validated['size_bytes'] = 0;
            $validated['mime_type'] = $validated['file_type'] === 'video' ? 'video/mp4' : ($validated['file_type'] === 'document' ? 'application/pdf' : 'image/jpeg');
        }

        unset($validated['file']);

        MediaFile::create($validated);

        return redirect()->back()->with('success', 'Berkas media dokumentasi berhasil diunggah.');
    }

    /**
     * Laporan & Rekapitulasi Humas (Parity with SIMOD-HUMAS ReportsView)
     */
    public function reports(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);
        $isNational = $this->aggregatesNational($selectedCode);

        $categoryFilter = $request->query('category', 'all');
        $statusFilter = $request->query('status', 'all');

        $poldaId = (!$isNational && $selectedPolda) ? $selectedPolda->id : null;
        $totalIssues = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->count();
        $resolvedCount = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->whereIn('status', ['selesai', 'arsip'])->count();
        $inProgressCount = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->whereIn('status', ['investigasi', 'klarifikasi'])->count();
        $pendingVerifyCount = Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->where('status', 'monitoring')->count();

        // Tingkat penyelesaian = laporan selesai/arsip dibanding seluruh laporan. Null bila belum ada data (jangan tampil 100%).
        $slaRate = $totalIssues > 0 ? (int)round(($resolvedCount / $totalIssues) * 100) : null;

        // Satwil breakdown for 6 Polres
        $polresList = $selectedPolda ? $selectedPolda->polres : Polres::where('is_active', true)->get();
        $satwilBreakdown = [];
        foreach ($polresList as $p) {
            $tot = Issue::where('polres_id', $p->id)->count();
            $res = Issue::where('polres_id', $p->id)->whereIn('status', ['selesai', 'arsip'])->count();
            $pos = Issue::where('polres_id', $p->id)->where('sentiment', 'positif')->count();
            $sent = $tot > 0 ? (int)round(($pos / $tot) * 100) : null;

            $satwilBreakdown[] = [
                'id'         => $p->id,
                'name'       => $p->name,
                'code'       => $p->code,
                'total'      => $tot,
                'resolved'   => $res,
                'sentiment'  => $sent,
                'sla_pct'    => $tot > 0 ? (int)round(($res / $tot) * 100) : null,
            ];
        }

        // Sebaran kategori nyata (dari data), bukan daftar kategori tetap.
        $categoryCounts = Issue::when($poldaId, fn ($q) => $q->where('polda_id', $poldaId))
            ->whereNotNull('category')
            ->select('category', \DB::raw('count(*) as c'))
            ->groupBy('category')
            ->orderByDesc('c')
            ->pluck('c', 'category');
        $categoryBreakdown = [];
        foreach ($categoryCounts as $cat => $count) {
            $categoryBreakdown[] = [
                'name'  => $cat,
                'count' => (int) $count,
                'pct'   => $totalIssues > 0 ? (int) round(($count / $totalIssues) * 100) : 0,
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
            'categoryOptions'   => $categoryCounts->keys()->values(),
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
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = $this->aggregatesNational($selectedCode);

        $issues = Issue::with(['polres', 'creator'])
            ->when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
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
     * Manajemen Personalia & Pengguna Berdasarkan Otoritas Berjenjang (RBAC).
     */
    public function personalia(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $roleFilter = $request->query('role', 'all');
        $polresFilter = $request->query('polres', 'all');
        $statusFilter = $request->query('status', 'all');
        $search = $request->query('q', '');
        $isNational = $this->aggregatesNational($selectedCode);

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

        if ($polresFilter !== 'all') {
            if ($polresFilter === 'none') {
                $query->whereNull('polres_id');
            } else {
                $query->where('polres_id', $polresFilter);
            }
        }

        if ($statusFilter !== 'all') {
            $query->where('is_active', $statusFilter === 'active');
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('badge_number', 'like', "%{$search}%")
                  ->orWhere('username', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $users = $query->orderByRaw("CASE WHEN role = 'kadiv_humas' THEN 1 WHEN role = 'super_admin_mabes' THEN 2 WHEN role = 'polda_leader' THEN 3 ELSE 4 END, id ASC")
            ->paginate(15)
            ->withQueryString();

        $polresList = $selectedPolda ? Polres::where('polda_id', $selectedPolda->id)->get() : Polres::where('is_active', true)->get();

        $targetPoldaId = $selectedPolda ? $selectedPolda->id : null;
        if ($selectedCode === 'ALL') {
            $targetPoldaId = Polda::where('code', 'DIVHUMAS')->value('id');
        }

        $modules = RolePermission::defaultModules();
        $roleMatrix = RolePermission::getMatrixForPolda($targetPoldaId);

        // Map role matrix with permission counts for quick inspection
        $rolesWithStats = array_map(function ($r) use ($modules) {
            $grantedCount = 0;
            foreach ($modules as $m) {
                if (!empty($r['permissions'][$m['key']])) {
                    $grantedCount++;
                }
            }
            $r['granted_count'] = $grantedCount;
            $r['total_count'] = count($modules);
            return $r;
        }, $roleMatrix);

        $baseCountQuery = User::when(!$isNational && $selectedPolda, fn($q) => $q->where(fn($sub) => $sub->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id')));

        $personnelStats = [
            'total'       => (clone $baseCountQuery)->count(),
            'active'      => (clone $baseCountQuery)->where('is_active', true)->count(),
            'inactive'    => (clone $baseCountQuery)->where('is_active', false)->count(),
            'kadiv'       => User::where('role', 'kadiv_humas')->count(),
            'superadmin'  => (clone $baseCountQuery)->whereIn('role', ['super_admin_mabes', 'kadiv_humas'])->count(),
            'polda_staff' => (clone $baseCountQuery)->whereIn('role', ['polda_leader', 'verifikator', 'analis'])->count(),
            'satwil'      => (clone $baseCountQuery)->where('role', 'operator')->count(),
        ];

        $authUser = $request->user();
        $canManageAll = $authUser ? $authUser->isSuperAdmin() : false;

        return Inertia::render('Personalia/Index', [
            'allPoldas'       => $allPoldas,
            'selectedPolda'   => $selectedPolda,
            'selectedCode'    => $selectedCode,
            'users'           => $users,
            'polresList'      => $polresList,
            'personnelStats'  => $personnelStats,
            'roleMatrix'      => $rolesWithStats,
            'modules'         => $modules,
            'canManageAll'    => $canManageAll,
            'currentUserId'   => $authUser?->id,
            'currentUserRole' => $authUser?->role,
            'filters'         => [
                'role'   => $roleFilter,
                'polres' => $polresFilter,
                'status' => $statusFilter,
                'q'      => $search,
            ],
            'metrics'         => $metrics,
        ]);
    }

    /**
     * Tambah Akun Personel Baru Berdasarkan Otoritas Berjenjang
     */
    public function storeUser(Request $request): RedirectResponse
    {
        $authUser = $request->user();

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
            'is_active'    => 'nullable|boolean',
        ]);

        // Authority Check: Regional officers cannot create Mabes-level accounts
        if ($authUser && !$authUser->isSuperAdmin()) {
            if (in_array($validated['role'], ['kadiv_humas', 'super_admin_mabes'], true)) {
                return back()->with('error', 'Akses Ditolak: Hanya Mabes Polri yang memiliki wewenang untuk mendaftarkan akun tingkat Kadivhumas atau Superadmin Mabes.');
            }
            if ($authUser->polda_id) {
                $validated['polda_id'] = $authUser->polda_id;
            }
        }

        $validated['polda_id'] = !empty($validated['polda_id']) ? $validated['polda_id'] : null;
        $validated['polres_id'] = !empty($validated['polres_id']) ? $validated['polres_id'] : null;
        $validated['password'] = Hash::make($validated['password']);
        $validated['is_active'] = $request->boolean('is_active', true);

        $user = User::create($validated);

        AuditLog::create([
            'polda_id'     => $user->polda_id,
            'user_id'      => auth()->id(),
            'action'       => 'personalia.create',
            'subject_type' => 'user',
            'subject_id'   => (string) $user->id,
            'metadata'     => [
                'name'         => $user->name,
                'username'     => $user->username,
                'role'         => $user->role,
                'badge_number' => $user->badge_number,
            ],
            'ip_address'   => $request->ip() ?: '127.0.0.1',
            'user_agent'   => $request->userAgent() ?: 'System',
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "Akun personel {$user->name} ({$user->role}) berhasil didaftarkan ke sistem.");
    }

    /**
     * Edit / Perbarui Data Akun Personel & Otoritas
     */
    public function updateUser(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        // Authority Check: Regional officers cannot edit Mabes-level accounts
        if ($authUser && !$authUser->isSuperAdmin()) {
            if (in_array($user->role, ['kadiv_humas', 'super_admin_mabes'], true)) {
                return back()->with('error', 'Akses Ditolak: Anda tidak memiliki wewenang untuk mengedit akun pimpinan Mabes Polri.');
            }
            if ($authUser->polda_id && $user->polda_id && $user->polda_id !== $authUser->polda_id) {
                return back()->with('error', 'Akses Ditolak: Anda hanya memiliki wewenang untuk mengelola personel di wilayah Polda Anda.');
            }
        }

        $validated = $request->validate([
            'name'         => 'required|string|max:255',
            'username'     => ['required', 'string', 'max:100', Rule::unique('users')->ignore($user->id)],
            'email'        => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'badge_number' => 'nullable|string|max:50',
            'role'         => 'required|string',
            'polda_id'     => 'nullable|exists:poldas,id',
            'polres_id'    => 'nullable|exists:polres,id',
            'phone'        => 'nullable|string|max:50',
            'password'     => 'nullable|string|min:6',
            'is_active'    => 'nullable|boolean',
        ]);

        // Prevent self-role-demotion or self-deactivation
        if ($authUser && $authUser->id === $user->id) {
            $validated['role'] = $user->role;
            $validated['is_active'] = true;
        }

        // Authority Check: Non-Mabes cannot promote to Mabes role
        if ($authUser && !$authUser->isSuperAdmin() && in_array($validated['role'], ['kadiv_humas', 'super_admin_mabes'], true)) {
            return back()->with('error', 'Akses Ditolak: Hanya Mabes Polri yang dapat menetapkan role tingkat Mabes.');
        }

        $updateData = [
            'name'         => $validated['name'],
            'username'     => $validated['username'],
            'email'        => $validated['email'],
            'badge_number' => $validated['badge_number'] ?? null,
            'role'         => $validated['role'],
            'polda_id'     => !empty($validated['polda_id']) ? $validated['polda_id'] : null,
            'polres_id'    => !empty($validated['polres_id']) ? $validated['polres_id'] : null,
            'phone'        => $validated['phone'] ?? null,
            'is_active'    => $request->boolean('is_active', true),
        ];

        if (!empty($validated['password'])) {
            $updateData['password'] = Hash::make($validated['password']);
        }

        $user->update($updateData);

        AuditLog::create([
            'polda_id'     => $user->polda_id,
            'user_id'      => auth()->id(),
            'action'       => 'personalia.update',
            'subject_type' => 'user',
            'subject_id'   => (string) $user->id,
            'metadata'     => [
                'name'    => $user->name,
                'role'    => $user->role,
                'changes' => array_keys($updateData),
            ],
            'ip_address'   => $request->ip() ?: '127.0.0.1',
            'user_agent'   => $request->userAgent() ?: 'System',
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "Data personel {$user->name} berhasil diperbarui.");
    }

    /**
     * Ubah Status Aktif / Non-Aktif Personel (Anti-Self-Lockout Protected)
     */
    public function toggleUserStatus(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        // Prevent self-deactivation
        if ($authUser && $authUser->id === $user->id) {
            return back()->with('error', 'Operasi Ditolak: Anda tidak dapat menonaktifkan akun Anda sendiri yang sedang aktif.');
        }

        // Protect primary master accounts
        if ($user->id === 1 || in_array($user->username, ['kadivhumas', 'superadmin'], true)) {
            return back()->with('error', 'Operasi Ditolak: Akun pimpinan utama sistem dilindungi dan tidak dapat dinonaktifkan.');
        }

        if ($authUser && !$authUser->isSuperAdmin() && in_array($user->role, ['kadiv_humas', 'super_admin_mabes'], true)) {
            return back()->with('error', 'Akses Ditolak: Anda tidak memiliki wewenang untuk mengubah status akun pimpinan Mabes Polri.');
        }

        $user->is_active = !$user->is_active;
        $user->save();

        $statusStr = $user->is_active ? 'diaktifkan' : 'dinonaktifkan';

        AuditLog::create([
            'polda_id'     => $user->polda_id,
            'user_id'      => auth()->id(),
            'action'       => 'personalia.toggle_status',
            'subject_type' => 'user',
            'subject_id'   => (string) $user->id,
            'metadata'     => [
                'name'      => $user->name,
                'is_active' => $user->is_active,
            ],
            'ip_address'   => $request->ip() ?: '127.0.0.1',
            'user_agent'   => $request->userAgent() ?: 'System',
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "Akun personel {$user->name} berhasil {$statusStr}.");
    }

    /**
     * Reset Password Dinas Personel
     */
    public function resetUserPassword(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        if ($authUser && !$authUser->isSuperAdmin() && in_array($user->role, ['kadiv_humas', 'super_admin_mabes'], true)) {
            return back()->with('error', 'Akses Ditolak: Anda tidak memiliki wewenang untuk mereset password akun pimpinan Mabes Polri.');
        }

        $validated = $request->validate([
            'new_password' => 'required|string|min:6',
        ]);

        $user->password = Hash::make($validated['new_password']);
        $user->save();

        AuditLog::create([
            'polda_id'     => $user->polda_id,
            'user_id'      => auth()->id(),
            'action'       => 'personalia.reset_password',
            'subject_type' => 'user',
            'subject_id'   => (string) $user->id,
            'metadata'     => [
                'name' => $user->name,
            ],
            'ip_address'   => $request->ip() ?: '127.0.0.1',
            'user_agent'   => $request->userAgent() ?: 'System',
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "Password dinas untuk personel {$user->name} berhasil direset.");
    }

    /**
     * Hapus Akun Personel (Anti-Self-Destruction & Protected Master Accounts)
     */
    public function deleteUser(Request $request, int $id): RedirectResponse
    {
        $user = User::findOrFail($id);
        $authUser = $request->user();

        // Self-delete prevention
        if ($authUser && $authUser->id === $user->id) {
            return back()->with('error', 'Operasi Ditolak: Anda tidak dapat menghapus akun Anda sendiri yang sedang aktif.');
        }

        // Primary master accounts protection
        if ($user->id === 1 || in_array($user->username, ['kadivhumas', 'superadmin'], true)) {
            return back()->with('error', 'Operasi Ditolak: Akun induk pimpinan sistem dilindungi dan kebal dari penghapusan.');
        }

        // Authority Check: Regional officers cannot delete Mabes personnel
        if ($authUser && !$authUser->isSuperAdmin() && in_array($user->role, ['kadiv_humas', 'super_admin_mabes'], true)) {
            return back()->with('error', 'Akses Ditolak: Anda tidak memiliki wewenang untuk menghapus akun pimpinan Mabes Polri.');
        }

        $userName = $user->name;
        $userRole = $user->role;
        $userPolda = $user->polda_id;

        $user->delete();

        AuditLog::create([
            'polda_id'     => $userPolda,
            'user_id'      => auth()->id(),
            'action'       => 'personalia.delete',
            'subject_type' => 'user',
            'subject_id'   => (string) $id,
            'metadata'     => [
                'name' => $userName,
                'role' => $userRole,
            ],
            'ip_address'   => $request->ip() ?: '127.0.0.1',
            'user_agent'   => $request->userAgent() ?: 'System',
            'created_at'   => now(),
        ]);

        return redirect()->back()->with('success', "Akun personel {$userName} ({$userRole}) telah berhasil dihapus dari sistem.");
    }

    /**
     * Profil Pengguna & PIN Cepat
     */
    public function profile(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);
        $user = $request->user()->load(['polda', 'polres']);

        return Inertia::render('Profile/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'profile'       => [
                'name'         => $user->name,
                'username'     => $user->username,
                'email'        => $user->email,
                'phone'        => $user->phone,
                'badge_number' => $user->badge_number,
                'role'         => $user->role,
                'polda'        => $user->polda?->name,
                'polres'       => $user->polres?->name,
            ],
        ]);
    }

    /**
     * Ganti kata sandi akun yang sedang login (wajib kata sandi saat ini).
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => 'required|string',
            'password'         => 'required|string|min:10|max:100|confirmed|different:current_password',
        ], [
            'password.min'       => 'Kata sandi baru minimal 10 karakter.',
            'password.confirmed' => 'Konfirmasi kata sandi baru tidak sama.',
            'password.different' => 'Kata sandi baru harus berbeda dari kata sandi saat ini.',
        ]);

        $user = $request->user();

        if (! \Illuminate\Support\Facades\Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
        }

        $user->update(['password' => $validated['password']]);

        AuditLog::log('auth.password_changed', 'user', (string) $user->id, ['username' => $user->username]);

        return back()->with('success', 'Kata sandi berhasil diperbarui.');
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
     * Pusat Keamanan (Security Center): parameter keamanan yang benar-benar ditegakkan + aktivitas keamanan nyata.
     */
    public function securityCenter(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $setting = SecuritySetting::current($selectedPolda?->id);
        $tz = $selectedCode === 'ALL' ? 'Asia/Jakarta' : ($selectedPolda->timezone ?? 'Asia/Makassar');
        $tzLabel = ['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'][$tz] ?? $tz;

        // Hanya peristiwa keamanan yang boleh dilihat pengguna ini (personel daerah: Polda-nya sendiri).
        $visible = fn () => AuditLog::query()->visibleTo($request->user(), $selectedCode, $selectedPolda)->securityEvents();

        $show = in_array($request->query('tampil'), ['semua', 'gagal', 'masuk', 'perubahan'], true) ? $request->query('tampil') : 'semua';
        $limit = in_array((int) $request->query('limit'), [15, 50, 100], true) ? (int) $request->query('limit') : 15;

        $list = $visible();
        match ($show) {
            'gagal'     => $list->failures(),
            'masuk'     => $list->where('audit_logs.action', 'auth.login'),
            'perubahan' => $list->where('audit_logs.action', 'not like', 'auth.%'),
            default     => null,
        };
        $matching = (clone $list)->count();

        $logs = $list->with('user:id,name,username')->latest('audit_logs.created_at')->latest('audit_logs.id')->limit($limit)->get()
            ->map(fn (AuditLog $log) => [
                'id'       => $log->id,
                'action'   => $log->action,
                'label'    => AuditLog::labelFor($log->action),
                'failed'   => AuditLog::isFailure($log->action),
                'user'     => $log->user?->name ?? (($log->metadata['username'] ?? null) ? $log->metadata['username'] . ' (tidak dikenal)' : 'Tidak dikenal'),
                'ip'       => $log->ip_address ?: '-',
                'time_ago' => \App\Support\FastTime::ago($log->getRawOriginal('created_at')),
                'created_at' => $log->created_at ? $log->created_at->copy()->setTimezone($tz)->format('d M Y, H:i') . ' ' . $tzLabel : '-',
            ]);

        $day = now()->subDay()->format('Y-m-d H:i:s');
        $stats = [
            'total'        => $visible()->count(),
            'failed24'     => $visible()->failures()->where('audit_logs.created_at', '>=', $day)->count(),
            'login24'      => $visible()->where('audit_logs.action', 'auth.login')->where('audit_logs.created_at', '>=', $day)->count(),
            'changes24'    => $visible()->where('audit_logs.action', 'not like', 'auth.%')->where('audit_logs.created_at', '>=', $day)->count(),
        ];

        return Inertia::render('Settings/Security', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'security'      => [
                'max_login_attempts'      => (int) $setting->max_login_attempts,
                'lockout_minutes'         => (int) $setting->lockout_minutes,
                'session_timeout_minutes' => (int) $setting->session_timeout_minutes,
            ],
            'nationalBriefEnabled' => (bool) (SecuritySetting::current(null)->share_national_brief ?? true),
            'securityLogs'  => $logs,
            'securityStats' => $stats,
            'securityView'  => ['show' => $show, 'limit' => $limit, 'matching' => $matching],
        ]);
    }

    /** Saklar global: tampilkan/sembunyikan ringkasan isu nasional di Command Center daerah. */
    public function toggleNationalBrief(Request $request)
    {
        $data = $request->validate(['enabled' => 'required|boolean']);
        $setting = SecuritySetting::current(null);
        $setting->update(['share_national_brief' => (bool) $data['enabled'], 'updated_by' => auth()->id()]);

        AuditLog::log('security.national_brief', 'security_settings', (string) $setting->id, ['enabled' => (bool) $data['enabled']]);

        return back()->with('success', $data['enabled'] ? 'Ringkasan isu nasional ditampilkan di Command Center daerah.' : 'Ringkasan isu nasional disembunyikan dari Command Center daerah.');
    }

    /**
     * Simpan Perubahan Parameter Keamanan ke Database & Audit Log
     */
    public function updateSecurity(Request $request)
    {
        $validated = $request->validate([
            'max_login_attempts'      => 'required|integer|min:1|max:20',
            'lockout_minutes'         => 'required|integer|min:1|max:1440',
            'session_timeout_minutes' => 'required|integer|min:5|max:480',
        ]);

        [$allPoldas, $selectedPolda] = $this->getBaseData($request);
        $setting = SecuritySetting::current($selectedPolda?->id);

        $validated['updated_by'] = auth()->id();
        $setting->update($validated);

        AuditLog::log('security.update', 'security_settings', (string) $setting->id, [
            'polda'        => $selectedPolda ? $selectedPolda->code : 'GLOBAL',
            'max_attempts' => $validated['max_login_attempts'],
            'lockout'      => $validated['lockout_minutes'],
            'idle_timeout' => $validated['session_timeout_minutes'],
        ]);

        return back()->with('success', 'Pengaturan keamanan disimpan dan langsung berlaku untuk login dan sesi berikutnya.');
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

        // Tenant dari sesi server (bukan query/klien); override hanya untuk Super Admin.
        [, , $sessionPoldaId] = $this->resolveActiveTenant($request);
        $targetPoldaId = $this->scopedPoldaId($request, $validated['polda_id'] ?? null) ?: $sessionPoldaId;

        // Hanya peran dan kunci modul yang dikenal sistem yang diterima; nilai dipaksa boolean.
        $knownRoles = array_column(RolePermission::defaultRoles(), 'role_key');
        $knownModules = array_column(RolePermission::defaultModules(), 'key');

        $rolesCount = 0;
        foreach ($validated['matrix'] as $row) {
            $roleKey = $row['role_key'];
            if (! in_array($roleKey, $knownRoles, true)) {
                abort(422, "Peran tidak dikenal: {$roleKey}");
            }
            $perms = collect($row['permissions'])->only($knownModules)->map(fn ($v) => filter_var($v, FILTER_VALIDATE_BOOLEAN))->all();

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
            'polda_id'     => $targetPoldaId,
            'user_id'      => auth()->id(),
            'action'       => 'roles.matrix_update',
            'subject_type' => 'role_permission',
            'subject_id'   => (string) ($targetPoldaId ?? 0),
            'metadata'     => [
                'roles_count' => $rolesCount,
                'summary'     => "Memperbarui matriks hak akses untuk {$rolesCount} peran otoritas pada polda_id: " . ($targetPoldaId ?? 'Nasional'),
            ],
            'ip_address'   => $request->ip() ?? '127.0.0.1',
            'user_agent'   => $request->userAgent() ?? 'System',
            'created_at'   => now(),
        ]);

        return back()->with('success', "Matriks Hak Akses & Otoritas untuk {$rolesCount} peran berhasil disimpan secara permanen!");
    }

    /**
     * Reset Matriks Hak Akses ke Standar Bawaan Polri
     */
    public function resetRolesMatrix(Request $request): RedirectResponse
    {
        [, , $sessionPoldaId] = $this->resolveActiveTenant($request);
        $targetPoldaId = $this->scopedPoldaId($request, $request->input('polda_id')) ?: $sessionPoldaId;

        RolePermission::where('polda_id', $targetPoldaId)->delete();
        RolePermission::getMatrixForPolda($targetPoldaId);

        AuditLog::create([
            'polda_id'     => $targetPoldaId,
            'user_id'      => auth()->id(),
            'action'       => 'roles.matrix_reset',
            'subject_type' => 'role_permission',
            'subject_id'   => (string) ($targetPoldaId ?? 0),
            'metadata'     => [
                'summary' => "Mereset matriks hak akses ke standar Polri Presisi pada polda_id: " . ($targetPoldaId ?? 'Nasional'),
            ],
            'ip_address'   => $request->ip() ?? '127.0.0.1',
            'user_agent'   => $request->userAgent() ?? 'System',
            'created_at'   => now(),
        ]);

        return back()->with('success', "Matriks Hak Akses berhasil direset ke standar resmi Polri Presisi!");
    }


}
