<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueStatusHistory;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class IssueController extends Controller
{
    /**
     * Display a listing of issues for the active tenant.
     */
    public function index(Request $request): Response
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = $this->aggregatesNational($selectedCode);
        $canManage = $this->canManageIssues($request);
        $user = $request->user();

        $statusFilter = (string) $request->query('status', 'all');
        $priorityFilter = (string) $request->query('priority', 'all');
        $categoryFilter = (string) $request->query('category', 'all');
        $polresFilter = (string) $request->query('polres', 'all');
        $search = (string) $request->query('q', '');
        $sort = in_array($request->query('sort'), ['terlama', 'prioritas', 'diperbarui'], true) ? (string) $request->query('sort') : 'terbaru';
        $perPage = in_array((int) $request->query('per_page'), [15, 30, 50], true) ? (int) $request->query('per_page') : 15;

        // Tab Sampah hanya untuk pengelola laporan; selain itu diabaikan.
        $trashView = $statusFilter === 'trash' && $canManage;
        if ($statusFilter === 'trash' && ! $canManage) {
            $statusFilter = 'all';
        }

        $polresList = $selectedPolda ? $selectedPolda->polres : Polres::where('is_active', true)->get();

        $query = Issue::with(['polres', 'polda', 'creator'])->withCount('mediaFiles');
        if ($trashView) {
            $query->onlyTrashed();
        }

        // Multi-Tenant Scoping: Jika tingkat Polda spesifik, filter polda_id. Jika Mabes (ALL), cakup konsolidasi nasional.
        if (!$isNational && $selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }

        if ($trashView) {
            // semua status di Sampah
        } elseif ($statusFilter === 'all') {
            // Arsip hanya tampil di tab Arsip agar daftar kerja tidak bercampur dengan laporan yang sudah ditutup.
            $query->whereNotIn('status', Issue::STATUS_GROUPS['archived']);
        } else {
            $query->whereIn('status', Issue::statusesForFilter($statusFilter));
        }

        if ($priorityFilter === 'attention' || $priorityFilter === 'atensi') {
            $query->whereIn('priority', ['critical', 'high'])->whereNotIn('status', $this->closedStatuses());
        } elseif ($priorityFilter !== 'all') {
            $query->where('priority', $priorityFilter);
        }

        if ($categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }

        if ($polresFilter !== 'all') {
            $query->whereHas('polres', fn($q) => $q->where('code', $polresFilter));
        }

        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('report_code', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('location_text', 'like', "%{$search}%");
            });
        }

        match ($sort) {
            'terlama'    => $query->orderBy('created_at')->orderBy('id'),
            'diperbarui' => $query->orderByDesc('updated_at')->orderByDesc('id'),
            'prioritas'  => $query->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")->orderByDesc('created_at'),
            default      => $query->orderByDesc('created_at')->orderByDesc('id'),
        };

        $issues = $query->paginate($perPage)->withQueryString()->through(function (Issue $issue) use ($request, $canManage, $trashView) {
            $issue->setAttribute('can_archive', $canManage && ! $trashView && ! in_array($issue->status, array_merge(Issue::STATUS_GROUPS['archived'], Issue::STATUS_GROUPS['draft']), true));
            $issue->setAttribute('can_restore', $canManage && ($trashView || in_array($issue->status, Issue::STATUS_GROUPS['archived'], true)));
            $issue->setAttribute('can_delete', ! $trashView && $this->canTrash($request, $issue));
            $issue->setAttribute('can_force_delete', $canManage && $trashView);

            return $issue;
        });

        $metrics = [
            'total_issues'    => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'  => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['submitted', 'unit_verified', 'monitoring', 'draft'])->count(),
            'in_progress'     => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['in_progress', 'polda_verified', 'investigasi'])->count(),
            'resolved'        => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['resolved', 'closed', 'selesai', 'klarifikasi'])->count(),
            'high_priority'   => Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('priority', ['high', 'critical'])->count(),
            'server_wita'     => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ($isNational ? ' WIB' : ' WITA'),
        ];

        // Hitungan per kelompok status (tab filter) & daftar kategori nyata untuk filter.
        $scoped = fn () => Issue::when(!$isNational && $selectedPolda, fn ($q) => $q->where('polda_id', $selectedPolda->id));
        $byStatus = $scoped()->select('status', \Illuminate\Support\Facades\DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $groupCounts = [];
        foreach (Issue::STATUS_GROUPS as $group => $statuses) {
            $groupCounts[$group] = (int) collect($statuses)->sum(fn ($st) => $byStatus[$st] ?? 0);
        }
        $groupCounts['all'] = (int) $byStatus->sum() - $groupCounts['archived'];
        $groupCounts['attention'] = $scoped()->whereIn('priority', ['critical', 'high'])->whereNotIn('status', $this->closedStatuses())->count();
        $groupCounts['trash'] = $canManage ? $scoped()->onlyTrashed()->count() : 0;
        $categoryOptions = $scoped()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category');

        return Inertia::render('Issues/Index', [
            'groupCounts'    => $groupCounts,
            'categoryOptions' => $categoryOptions,
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'polresList'     => $polresList,
            'issues'         => $issues,
            'filters'        => [
                'status'   => $statusFilter,
                'priority' => $priorityFilter,
                'category' => $categoryFilter,
                'polres'   => $polresFilter,
                'q'        => $search,
                'sort'     => $sort,
                'per_page' => $perPage,
            ],
            'canManage'      => $canManage,
            'canBulkDelete'  => $canManage,
            'metrics'        => $metrics,
        ]);
    }

    /**
     * Show the detailed view of an issue / report (Parity with SIMOD-HUMAS IssueDetailView).
     */
    public function show(Request $request, $id): Response
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);

        $issue = Issue::with([
            'polda',
            'polres',
            'creator',
            'mediaFiles',
            'statusHistories.actor',
            'tasks',
            'publications',
            'pressReleases',
        ])->withTrashed()->findOrFail($id);

        $this->authorizeTenantRecord($request, $issue);
        $canManage = $this->canManageIssues($request);
        if ($issue->trashed() && ! $canManage) {
            abort(404);
        }

        $metrics = [
            'total_issues'   => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify' => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'server_wita'    => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return Inertia::render('Issues/Show', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'issue'         => $issue,
            'metrics'       => $metrics,
            'manage'        => [
                'trashed'      => $issue->trashed(),
                'can_archive'  => $canManage && ! $issue->trashed() && ! in_array($issue->status, array_merge(Issue::STATUS_GROUPS['archived'], Issue::STATUS_GROUPS['draft']), true),
                'can_restore'  => $canManage && ($issue->trashed() || in_array($issue->status, Issue::STATUS_GROUPS['archived'], true)),
                'can_delete'   => ! $issue->trashed() && $this->canTrash($request, $issue),
                'can_force'    => $canManage && $issue->trashed(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new issue / field report.
     */
    public function create(Request $request): Response
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);

        $polresList = $selectedPolda ? $selectedPolda->polres : Polres::where('is_active', true)->get();

        return Inertia::render('Issues/Create', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'polresList'    => $polresList,
            'metrics'       => [
                'total_issues'   => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
                'server_wita'    => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
            ],
        ]);
    }

    /**
     * Store a newly created issue in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'polda_id'         => 'required|exists:poldas,id',
            'polres_id'        => 'nullable|exists:polres,id',
            'title'            => 'required|string|min:5|max:255',
            'summary'          => 'required|string|min:10',
            'location_text'    => 'nullable|string|max:255',
            'occurred_at'      => 'nullable|date',
            'category'         => 'required|string',
            'priority'         => 'required|in:low,medium,high,critical',
            'sentiment'        => 'nullable|in:positif,netral,negatif',
            'status'           => 'nullable|string',
            'source_media'     => 'nullable|string|max:100',
            'source_url'       => 'nullable|string|max:500',
            'assigned_unit'    => 'nullable|string|max:100',
            'is_hoax'          => 'nullable|boolean',
            'files.*'          => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,pdf|max:20480',
        ]);

        $year = date('Y');
        $countYear = Issue::whereYear('created_at', $year)->count() + 1;
        $reportCode = sprintf('SIM-%s-%05d', $year, $countYear);

        if ($request->user() && !$request->user()->isSuperAdmin() && $request->user()->polda_id) {
            $validated['polda_id'] = $request->user()->polda_id;
        }

        $validated['polres_id'] = !empty($validated['polres_id']) ? $validated['polres_id'] : null;
        $validated['report_code'] = $reportCode;
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(5);
        // Hanya dua status awal yang sah: draf, atau diajukan ke antrean verifikasi (monitoring).
        $validated['status'] = $request->input('status') === 'draft' ? 'draft' : 'monitoring';
        $validated['sentiment'] = $validated['sentiment'] ?? 'netral';
        // Laporan lapangan tidak memiliki metrik viralitas; jangan diisi angka acak.
        $validated['virality_score'] = 0;
        $validated['engagement_count'] = 0;
        $validated['created_by'] = auth()->id();

        unset($validated['files']);

        $issue = Issue::create($validated);

        // Upload berkas bukti pendukung ke media_files
        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                if ($file && $file->isValid()) {
                    $ext = $file->getClientOriginalExtension();
                    $filename = Str::random(24) . '.' . $ext;
                    $path = $file->storeAs('uploads/issues/' . $issue->id, $filename, 'public');
                    
                    $mime = $file->getMimeType();
                    $fileType = str_starts_with($mime, 'video/') ? 'video' : (str_starts_with($mime, 'image/') ? 'photo' : 'document');

                    MediaFile::create([
                        'polda_id'      => $issue->polda_id,
                        'polres_id'     => $issue->polres_id,
                        'issue_id'      => $issue->id,
                        'uploaded_by'   => auth()->id(),
                        'title'         => $file->getClientOriginalName(),
                        'file_type'     => $fileType,
                        'file_path'     => $path,
                        'file_url'      => '/storage/' . $path,
                        'size_bytes'    => $file->getSize(),
                        'mime_type'     => $mime,
                        'review_status' => 'approved',
                        'is_private'    => false,
                    ]);
                }
            }
        }

        // Record initial status history
        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => 'none',
            'to_status'   => $issue->status,
            'reason'      => $issue->status === 'draft' ? 'Pembuatan draf laporan awal' : 'Pendaftaran laporan baru diajukan ke antrean verifikasi',
        ]);

        // Audit log
        AuditLog::create([
            'polda_id'     => $issue->polda_id,
            'user_id'      => auth()->id(),
            'action'       => 'issue.create',
            'subject_type' => 'issue',
            'subject_id'   => $issue->id,
            'ip_address'   => $request->ip(),
            'user_agent'   => $request->userAgent(),
            'metadata'     => ['report_code' => $reportCode, 'title' => $issue->title],
            'created_at'   => now(),
        ]);

        return redirect()->route('issues.index')
            ->with('success', "✓ Laporan {$reportCode} berhasil dibuat dan diajukan ke sistem.");
    }

    /**
     * Ekspor CSV daftar laporan sesuai filter aktif (Standar SIMOD-HUMAS).
     */
    public function exportCsv(Request $request)
    {
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = $this->aggregatesNational($selectedCode);
        $statusFilter = $request->query('status', 'all');
        $priorityFilter = $request->query('priority', 'all');
        $categoryFilter = $request->query('category', 'all');
        $polresFilter = $request->query('polres', 'all');
        $search = $request->query('q', '');

        $query = Issue::with(['polres', 'polda', 'creator'])->latest();

        if (!$isNational && $selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }
        if ($statusFilter !== 'all') {
            $query->whereIn('status', Issue::statusesForFilter($statusFilter));
        }
        if ($priorityFilter === 'attention' || $priorityFilter === 'atensi') {
            $query->whereIn('priority', ['critical', 'high'])->whereNotIn('status', ['selesai']);
        } elseif ($priorityFilter !== 'all') {
            $query->where('priority', $priorityFilter);
        }
        if ($categoryFilter !== 'all') {
            $query->where('category', $categoryFilter);
        }
        if ($polresFilter !== 'all') {
            $query->whereHas('polres', fn($q) => $q->where('code', $polresFilter));
        }
        if (!empty($search)) {
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('report_code', 'like', "%{$search}%")
                  ->orWhere('summary', 'like', "%{$search}%")
                  ->orWhere('location_text', 'like', "%{$search}%");
            });
        }

        $issues = $query->get();
        $filename = 'Laporan-Kamtibmas-' . ($selectedPolda ? $selectedPolda->code : 'POLDA') . '-' . date('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($issues) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));
            
            fputcsv($handle, [
                'No',
                'Kode Laporan',
                'Tanggal Kejadian',
                'Satker / Wilayah',
                'Kategori',
                'Prioritas',
                'Status Verifikasi',
                'Judul Laporan',
                'Lokasi Kejadian',
                'Ringkasan Kronologi',
                'Sumber Media / Tautan',
            ]);

            foreach ($issues as $index => $issue) {
                fputcsv($handle, [
                    $index + 1,
                    $issue->report_code ?? "SIM-{$issue->id}",
                    $issue->occurred_at ? date('d/m/Y H:i', strtotime($issue->occurred_at)) : date('d/m/Y H:i', strtotime($issue->created_at)),
                    $issue->polres ? $issue->polres->name : ($issue->polda ? $issue->polda->name : 'Polda Sulbar'),
                    $issue->category,
                    strtoupper($issue->priority),
                    $issue->status,
                    $issue->title,
                    $issue->location_text ?? '-',
                    str_replace(["\r", "\n"], ' ', $issue->summary),
                    $issue->source_url ?? $issue->source_media ?? '-',
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Verifikasi & Setujui laporan (Kasihumas / Verifikator Polda).
     */
    public function verify(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        $request->validate(['target_status' => 'nullable|in:investigasi,klarifikasi']);

        if (! in_array($issue->status, ['monitoring', 'investigasi'], true)) {
            return redirect()->back()->with('error', 'Laporan ini tidak berada pada tahap telaah verifikasi (status saat ini: ' . $issue->status . ').');
        }

        $fromStatus = $issue->status;
        $toStatus = $request->input('target_status', 'investigasi');
        $reason = $request->input('reason', 'Laporan telah ditelaah fakta lapangannya dan dinyatakan terverifikasi sah.');

        $issue->update([
            'status'     => $toStatus,
            'priority'   => $request->input('priority', $issue->priority),
            'updated_at' => now(),
        ]);

        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => $fromStatus,
            'to_status'   => $toStatus,
            'reason'      => $reason,
        ]);

        return redirect()->back()->with('success', "✓ Laporan {$issue->report_code} berhasil diverifikasi.");
    }

    /**
     * Ajukan draf laporan ke antrean verifikasi (draft -> monitoring).
     */
    public function submitDraft(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        if ($issue->status !== 'draft') {
            return redirect()->back()->with('error', 'Hanya laporan berstatus draf yang dapat diajukan ke verifikasi.');
        }

        $issue->update(['status' => 'monitoring', 'updated_at' => now()]);

        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => 'draft',
            'to_status'   => 'monitoring',
            'reason'      => 'Draf laporan diajukan ke antrean verifikasi',
        ]);

        return redirect()->back()->with('success', "Laporan {$issue->report_code} diajukan ke antrean verifikasi.");
    }

    /**
     * Kembalikan laporan untuk revisi / klarifikasi tambahan.
     */
    public function returnIssue(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);
        $reason = $request->input('reason');
        if (empty(trim((string) $reason))) {
            return redirect()->back()->with('error', 'Wajib mencantumkan alasan pengembalian/catatan revisi.');
        }

        $fromStatus = $issue->status;
        $issue->update([
            'status'     => 'monitoring',
            'updated_at' => now(),
        ]);

        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => $fromStatus,
            'to_status'   => 'monitoring',
            'reason'      => "Dikembalikan untuk revisi: {$reason}",
        ]);

        return redirect()->back()->with('success', "Laporan dikembalikan ke pelapor dengan catatan perbaikan.");
    }

    /**
     * Selesaikan laporan (Penyelesaian / Kontra Narasi Berhasil).
     */
    public function resolve(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);
        $resolvedNote = $request->input('resolved_note');
        if (empty(trim((string) $resolvedNote))) {
            return redirect()->back()->with('error', 'Wajib mencantumkan catatan hasil penanganan tuntas.');
        }

        $fromStatus = $issue->status;
        $issue->update([
            'status'        => 'selesai',
            'resolved_note' => $resolvedNote,
            'updated_at'    => now(),
        ]);

        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => $fromStatus,
            'to_status'   => 'selesai',
            'reason'      => "Laporan diselesaikan: {$resolvedNote}",
        ]);

        return redirect()->back()->with('success', "✓ Status laporan {$issue->report_code} berhasil diperbarui menjadi SELESAI.");
    }

    /**
     * Tutup dan arsipkan laporan.
     */
    public function close(Request $request, $id)
    {
        return $this->archive($request, $id);
    }

    /**
     * Arsipkan laporan. Tahap sebelumnya disimpan sehingga arsip dapat dipulihkan ke tahap semula.
     */
    public function archive(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        $skip = $this->archiveOne($issue, $this->reasonFrom($request));
        if ($skip !== null) {
            return back()->with('error', "Laporan tidak dapat diarsipkan: {$skip}.");
        }
        $this->audit('issue.archive', $issue);

        return back()->with('success', 'Laporan diarsipkan. Pulihkan kapan saja dari tab Arsip.');
    }

    /**
     * Pulihkan laporan dari Arsip (ke tahap semula) atau dari Sampah.
     */
    public function restore(Request $request, $id)
    {
        $issue = Issue::withTrashed()->findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        $wasTrashed = $issue->trashed();
        $skip = $this->restoreOne($issue);
        if ($skip !== null) {
            return back()->with('error', "Laporan tidak dapat dipulihkan: {$skip}.");
        }
        $this->audit('issue.restore', $issue);

        return back()->with('success', $wasTrashed ? 'Laporan dipulihkan dari Sampah.' : 'Laporan dipulihkan dari Arsip.');
    }

    /**
     * Hapus laporan = pindah ke Sampah (dapat dipulihkan). Pengelola dapat menghapus laporan apa pun;
     * pembuat laporan hanya dapat menghapus drafnya sendiri.
     */
    public function destroy(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);
        abort_unless($this->canTrash($request, $issue), 403, 'Anda tidak berwenang menghapus laporan ini.');

        $skip = $this->trashOne($issue, $this->reasonFrom($request));
        if ($skip !== null) {
            return back()->with('error', "Laporan tidak dapat dihapus: {$skip}.");
        }
        $this->audit('issue.delete', $issue);

        $message = 'Laporan dipindahkan ke Sampah. Dapat dipulihkan dari tab Sampah.';

        return $request->boolean('from_show') ? redirect()->route('issues.index')->with('success', $message) : back()->with('success', $message);
    }

    /**
     * Hapus permanen laporan yang sudah di Sampah (beserta foto buktinya). Tidak dapat dibatalkan.
     */
    public function forceDestroy(Request $request, $id)
    {
        $issue = Issue::onlyTrashed()->findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);
        abort_unless($this->canManageIssues($request), 403);

        if (strtoupper(trim((string) $request->input('confirm'))) !== 'HAPUS') {
            return back()->with('error', 'Ketik HAPUS untuk mengonfirmasi penghapusan permanen.');
        }

        $this->audit('issue.delete_permanent', $issue);
        $this->forceDeleteOne($issue);

        $message = 'Laporan dihapus permanen.';

        return $request->boolean('from_show') ? redirect()->route('issues.index', ['status' => 'trash'])->with('success', $message) : back()->with('success', $message);
    }

    /**
     * Aksi massal: arsipkan, pulihkan, hapus (ke Sampah), hapus permanen, atau ubah prioritas.
     * Laporan di luar wilayah pengguna atau yang tidak memenuhi syarat dilewati dan dilaporkan.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action'   => 'required|in:archive,restore,delete,delete_permanent,priority',
            'ids'      => 'required|array|min:1|max:100',
            'ids.*'    => 'integer',
            'priority' => 'required_if:action,priority|nullable|in:low,medium,high,critical',
            'reason'   => 'nullable|string|max:300',
            'confirm'  => 'nullable|string|max:20',
        ]);

        if ($data['action'] === 'delete_permanent' && strtoupper(trim((string) ($data['confirm'] ?? ''))) !== 'HAPUS') {
            return back()->with('error', 'Ketik HAPUS untuk mengonfirmasi penghapusan permanen.');
        }

        $user = $request->user();
        $issues = Issue::withTrashed()->whereIn('id', $data['ids'])->get();
        $done = 0;
        $skipped = [];
        $titles = [];

        foreach ($issues as $issue) {
            $inTenant = $user->isSuperAdmin() || ! $user->polda_id || (int) $issue->polda_id === (int) $user->polda_id;
            if (! $inTenant) {
                $skipped['di luar wilayah Anda'] = ($skipped['di luar wilayah Anda'] ?? 0) + 1;
                continue;
            }

            $skip = match ($data['action']) {
                'archive'          => $this->archiveOne($issue, $data['reason'] ?? null),
                'restore'          => $this->restoreOne($issue),
                'delete'           => $this->canTrash($request, $issue) ? $this->trashOne($issue, $data['reason'] ?? null) : 'tidak berwenang',
                'delete_permanent' => $issue->trashed() ? $this->forceDeleteOne($issue) : 'belum di Sampah',
                'priority'         => $issue->trashed() ? 'berada di Sampah' : $this->setPriority($issue, $data['priority']),
            };

            if ($skip === null) {
                $done++;
                $titles[] = $issue->report_code ?: ('#' . $issue->id);
            } else {
                $skipped[$skip] = ($skipped[$skip] ?? 0) + 1;
            }
        }

        $missing = count($data['ids']) - $issues->count();
        if ($missing > 0) {
            $skipped['tidak ditemukan'] = $missing;
        }

        AuditLog::log('issue.bulk', 'issues', null, [
            'summary' => "Aksi massal {$data['action']}: {$done} laporan diproses, " . array_sum($skipped) . ' dilewati.',
            'action'  => $data['action'],
            'done'    => $done,
            'skipped' => $skipped,
            'codes'   => array_slice($titles, 0, 50),
        ]);

        $verb = ['archive' => 'diarsipkan', 'restore' => 'dipulihkan', 'delete' => 'dipindahkan ke Sampah', 'delete_permanent' => 'dihapus permanen', 'priority' => 'diubah prioritasnya'][$data['action']];
        $message = "{$done} laporan {$verb}.";
        if ($skipped) {
            $message .= ' Dilewati: ' . collect($skipped)->map(fn ($n, $why) => "{$n} ({$why})")->implode(', ') . '.';
        }

        return $done > 0 ? back()->with('success', $message) : back()->with('error', $message);
    }

    // ------------------------------------------------------------------ pembantu arsip/hapus

    /** Pengelola laporan: verifikator ke atas (izin `issues_verify`). */
    private function canManageIssues(Request $request): bool
    {
        $user = $request->user();

        return (bool) ($user && ($user->isSuperAdmin() || $user->hasPermission('issues_verify')));
    }

    /** Pengelola boleh menghapus apa pun; pembuat hanya drafnya sendiri. */
    private function canTrash(Request $request, Issue $issue): bool
    {
        if ($issue->trashed()) {
            return false;
        }
        if ($this->canManageIssues($request)) {
            return true;
        }
        $user = $request->user();

        return $user && $user->hasPermission('issues_create') && $issue->status === 'draft' && (int) $issue->created_by === (int) $user->id;
    }

    /** Status yang menandai laporan sudah ditutup (tidak lagi "aktif"). */
    private function closedStatuses(): array
    {
        return array_merge(Issue::STATUS_GROUPS['done'], Issue::STATUS_GROUPS['archived']);
    }

    private function reasonFrom(Request $request): ?string
    {
        $reason = trim((string) $request->input('reason', ''));

        return $reason === '' ? null : mb_substr($reason, 0, 300);
    }

    private function history(Issue $issue, ?string $from, string $to, string $reason): void
    {
        IssueStatusHistory::create(['issue_id' => $issue->id, 'actor_id' => auth()->id(), 'from_status' => $from, 'to_status' => $to, 'reason' => $reason]);
    }

    private function audit(string $action, Issue $issue): void
    {
        AuditLog::log($action, 'issue', (string) $issue->id, ['report_code' => $issue->report_code, 'title' => $issue->title]);
    }

    /** @return string|null alasan dilewati; null bila berhasil */
    private function archiveOne(Issue $issue, ?string $reason): ?string
    {
        if ($issue->trashed()) {
            return 'berada di Sampah';
        }
        if (in_array($issue->status, Issue::STATUS_GROUPS['archived'], true)) {
            return 'sudah diarsipkan';
        }
        if ($issue->status === 'draft') {
            return 'masih draf (hapus saja bila tidak diperlukan)';
        }

        $from = $issue->status;
        $issue->update(['status' => 'arsip', 'archived_at' => now(), 'status_before_archive' => $from]);
        $this->history($issue, $from, 'arsip', $reason ?: 'Laporan diarsipkan.');

        return null;
    }

    /** @return string|null */
    private function restoreOne(Issue $issue): ?string
    {
        if ($issue->trashed()) {
            $issue->restore();
            $issue->update(['deleted_by' => null]);
            $this->history($issue, 'dihapus', $issue->status, 'Dipulihkan dari Sampah.');

            return null;
        }
        if (! in_array($issue->status, Issue::STATUS_GROUPS['archived'], true)) {
            return 'tidak berada di Arsip atau Sampah';
        }

        $to = $issue->status_before_archive && $issue->status_before_archive !== 'arsip' ? $issue->status_before_archive : 'selesai';
        $issue->update(['status' => $to, 'archived_at' => null, 'status_before_archive' => null]);
        $this->history($issue, 'arsip', $to, 'Dipulihkan dari Arsip ke tahap semula.');

        return null;
    }

    /** @return string|null */
    private function trashOne(Issue $issue, ?string $reason): ?string
    {
        if ($issue->trashed()) {
            return 'sudah di Sampah';
        }

        $this->history($issue, $issue->status, 'dihapus', $reason ?: 'Laporan dipindahkan ke Sampah.');
        $issue->update(['deleted_by' => auth()->id()]);
        $issue->delete();

        return null;
    }

    /** @return string|null */
    private function setPriority(Issue $issue, string $priority): ?string
    {
        if ($issue->priority === $priority) {
            return 'prioritas sudah sama';
        }
        $from = $issue->priority;
        $issue->update(['priority' => $priority]);
        $this->history($issue, $issue->status, $issue->status, "Prioritas diubah dari {$from} menjadi {$priority}.");

        return null;
    }

    /** Hapus permanen beserta berkas foto bukti. @return string|null */
    private function forceDeleteOne(Issue $issue): ?string
    {
        foreach (\App\Models\MediaFile::where('issue_id', $issue->id)->get() as $file) {
            try {
                if ($file->file_path) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete(ltrim(preg_replace('#^/?storage/#', '', (string) $file->file_path), '/'));
                }
            } catch (\Throwable $e) {
                // berkas fisik tidak ditemukan: lanjutkan
            }
            $file->delete();
        }
        $issue->forceDelete();

        return null;
    }

    /**
     * Update clarification / Fact Check result.
     */
    public function updateClarification(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        $validated = $request->validate([
            'is_hoax'                => 'required|boolean',
            'fact_check_result'      => 'required|string',
            'official_clarification' => 'required|string',
            'clarification_url'      => 'nullable|string|max:500',
        ]);

        $fromStatus = $issue->status;
        $issue->update([
            'is_hoax'                => $validated['is_hoax'],
            'fact_check_result'      => $validated['fact_check_result'],
            'official_clarification' => $validated['official_clarification'],
            'clarification_url'      => $validated['clarification_url'] ?? null,
            'status'                 => 'klarifikasi',
            'clarified_at'           => now(),
        ]);

        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => $fromStatus,
            'to_status'   => 'klarifikasi',
            'reason'      => 'Klarifikasi resmi diterbitkan: ' . ($validated['is_hoax'] ? 'TERBUKTI HOAKS' : 'FAKTA TERKONFIRMASI'),
        ]);

        return redirect()->back()->with('success', 'Klarifikasi resmi dan hasil fact-check berhasil diperbarui!');
    }

    /**
     * Terbitkan Disposisi Tugas Cepat langsung dari Detail Isu.
     */
    public function createTask(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        $validated = $request->validate([
            'title'            => 'required|string|max:255',
            'instructions'     => 'required|string',
            'assigned_unit'    => 'required|string|max:100',
            'assigned_officer' => 'nullable|string|max:100',
            'priority'         => 'required|in:low,medium,high,urgent',
            'due_at'           => 'nullable|date',
        ]);

        $task = Task::create([
            'polda_id'         => $issue->polda_id,
            'polres_id'        => $issue->polres_id,
            'issue_id'         => $issue->id,
            'created_by'       => auth()->id(),
            'title'            => $validated['title'],
            'instructions'     => $validated['instructions'],
            'assigned_unit'    => $validated['assigned_unit'],
            'assigned_officer' => $validated['assigned_officer'] ?? null,
            'priority'         => $validated['priority'],
            'due_at'           => $validated['due_at'] ?? now()->addDays(2),
            'status'           => 'in_progress',
        ]);

        return redirect()->back()->with('success', "Disposisi tugas berhasil diterbitkan kepada {$task->assigned_unit}.");
    }

    /**
     * Terbitkan Draf Siaran Pers / Publikasi langsung dari Detail Isu.
     */
    public function createRelease(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $this->authorizeTenantRecord($request, $issue);

        $validated = $request->validate([
            'channel'       => 'required|in:tribratanews,instagram,facebook,twitter,tiktok,youtube,siaran_pers',
            'title'         => 'required|string|max:255',
            'content_draft' => 'required|string',
            'url'           => 'nullable|string|max:500',
        ]);

        Publication::create([
            'polda_id'        => $issue->polda_id,
            'polres_id'       => $issue->polres_id,
            'issue_id'        => $issue->id,
            'author_id'       => auth()->id(),
            'channel'         => $validated['channel'],
            'title'           => $validated['title'],
            'content_draft'   => $validated['content_draft'],
            'url'             => $validated['url'] ?? null,
            'approval_status' => 'pending_approval',
        ]);

        return redirect()->back()->with('success', 'Draf publikasi berhasil dibuat dan diajukan ke antrean persetujuan.');
    }
}
