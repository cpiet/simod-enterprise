<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueStatusHistory;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\PressRelease;
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
        $selectedCode = $request->query('polda', 'SULBAR');
        $statusFilter = $request->query('status', 'all');
        $priorityFilter = $request->query('priority', 'all');
        $categoryFilter = $request->query('category', 'all');
        $polresFilter = $request->query('polres', 'all');
        $search = $request->query('q', '');

        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::with(['polres'])->where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        $query = Issue::with(['polres', 'polda', 'creator'])->withCount('mediaFiles')->latest();

        if ($selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($priorityFilter !== 'all') {
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

        $issues = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_issues'    => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'monitoring'      => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'monitoring')->count(),
            'investigasi'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'investigasi')->count(),
            'klarifikasi'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'klarifikasi')->count(),
            'hoax_count'      => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('is_hoax', true)->count(),
            'server_wita'     => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return Inertia::render('Issues/Index', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'issues'         => $issues,
            'filters'        => [
                'status'   => $statusFilter,
                'priority' => $priorityFilter,
                'category' => $categoryFilter,
                'polres'   => $polresFilter,
                'q'        => $search,
            ],
            'metrics'        => $metrics,
        ]);
    }

    /**
     * Show the detailed view of an issue / report (Parity with SIMOD-HUMAS IssueDetailView).
     */
    public function show(Request $request, $id): Response
    {
        $selectedCode = $request->query('polda', 'SULBAR');
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::with(['polres'])->where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        $issue = Issue::with([
            'polda',
            'polres',
            'creator',
            'mediaFiles',
            'statusHistories.actor',
            'tasks',
            'publications',
            'pressReleases',
        ])->findOrFail($id);

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
        ]);
    }

    /**
     * Show the form for creating a new issue / field report.
     */
    public function create(Request $request): Response
    {
        $selectedCode = $request->query('polda', 'SULBAR');
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::with(['polres'])->where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

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
            'title'            => 'required|string|max:255',
            'summary'          => 'required|string',
            'location_text'    => 'nullable|string|max:255',
            'occurred_at'      => 'nullable|date',
            'category'         => 'required|string',
            'priority'         => 'required|in:low,medium,high,critical',
            'sentiment'        => 'required|in:positif,netral,negatif',
            'source_media'     => 'nullable|string|max:100',
            'source_url'       => 'nullable|string|max:500',
            'assigned_unit'    => 'nullable|string|max:100',
            'is_hoax'          => 'nullable|boolean',
        ]);

        $year = date('Y');
        $countYear = Issue::whereYear('created_at', $year)->count() + 1;
        $reportCode = sprintf('SIM-%s-%05d', $year, $countYear);

        $validated['report_code'] = $reportCode;
        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(5);
        $validated['status'] = 'monitoring';
        $validated['virality_score'] = rand(40, 85);
        $validated['engagement_count'] = rand(1000, 15000);
        $validated['created_by'] = auth()->id();

        $issue = Issue::create($validated);

        // Record initial status history
        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => 'draft',
            'to_status'   => 'monitoring',
            'reason'      => 'Pendaftaran laporan baru ke dalam pemantauan sistem intelijen',
        ]);

        // Audit log
        AuditLog::create([
            'polda_id'    => $issue->polda_id,
            'user_id'     => auth()->id(),
            'action'      => 'issue.create',
            'target_type' => 'issue',
            'target_id'   => $issue->id,
            'ip_address'  => $request->ip(),
            'user_agent'  => $request->userAgent(),
            'details'     => ['report_code' => $reportCode, 'title' => $issue->title],
        ]);

        return redirect()->route('issues.show', ['id' => $issue->id, 'polda' => $request->query('polda', 'SULBAR')])
            ->with('success', "Laporan {$reportCode} berhasil dibuat dan tercatat di sistem.");
    }

    /**
     * Verifikasi & Setujui laporan (Kasihumas / Verifikator Polda).
     */
    public function verify(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
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
     * Kembalikan laporan untuk revisi / klarifikasi tambahan.
     */
    public function returnIssue(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);
        $reason = $request->input('reason');
        if (empty($reason)) {
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
        $resolvedNote = $request->input('resolved_note');
        if (empty($resolvedNote)) {
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
        $issue = Issue::findOrFail($id);
        $fromStatus = $issue->status;

        $issue->update([
            'status'     => 'arsip',
            'updated_at' => now(),
        ]);

        IssueStatusHistory::create([
            'issue_id'    => $issue->id,
            'actor_id'    => auth()->id(),
            'from_status' => $fromStatus,
            'to_status'   => 'arsip',
            'reason'      => 'Laporan ditutup dan dipindahkan ke arsip dinas kepolisian.',
        ]);

        return redirect()->back()->with('success', "Laporan berhasil diarsipkan.");
    }

    /**
     * Update clarification / Fact Check result.
     */
    public function updateClarification(Request $request, $id)
    {
        $issue = Issue::findOrFail($id);

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
