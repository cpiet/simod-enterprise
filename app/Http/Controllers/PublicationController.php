<?php

namespace App\Http\Controllers;

use App\Models\Polda;
use App\Models\Publication;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PublicationController extends Controller
{
    /**
     * Display listing of multi-channel publications.
     */
    public function index(Request $request): Response
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = ($selectedCode === 'ALL');
        $channelFilter = $request->query('channel', 'all');
        $statusFilter = $request->query('status', 'all');

        $query = Publication::with(['polres', 'issue'])->latest();

        if (!$isNational && $selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }

        if ($channelFilter !== 'all') {
            $query->where('channel', $channelFilter);
        }

        if (isset(Publication::STATUS_GROUPS[$statusFilter])) {
            $query->whereIn('approval_status', Publication::STATUS_GROUPS[$statusFilter]);
        }

        $publications = $query->paginate(12)->withQueryString();

        $scoped = fn () => Publication::when(!$isNational && $selectedPolda, fn ($q) => $q->where('polda_id', $selectedPolda->id));
        $byChannel = $scoped()->select('channel', \DB::raw('count(*) as c'))->groupBy('channel')->pluck('c', 'channel');
        $byApproval = $scoped()->select('approval_status', \DB::raw('count(*) as c'))->groupBy('approval_status')->pluck('c', 'approval_status');

        $statusCounts = ['all' => (int) $byApproval->sum()];
        foreach (Publication::STATUS_GROUPS as $group => $statuses) {
            $statusCounts[$group] = (int) collect($statuses)->sum(fn ($st) => $byApproval[$st] ?? 0);
        }

        $metrics = [
            'total'    => $statusCounts['all'],
            'channels' => $byChannel,
        ];

        return Inertia::render('Publications/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'publications'  => $publications,
            'channelFilter' => $channelFilter,
            'statusFilter'  => $statusFilter,
            'statusCounts'  => $statusCounts,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Store new publication across channels.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'polda_id'      => 'required|exists:poldas,id',
            'channel'       => 'required|in:tribratanews,instagram,facebook,twitter,tiktok,youtube,siaran_pers',
            'title'         => 'required|string|max:255',
            'content_draft' => 'required|string',
            'url'           => 'nullable|string|max:500',
        ]);

        $validated['approval_status'] = 'published';
        $validated['author_id'] = auth()->id();
        $validated['reviewed_at'] = now();

        if ($request->user() && !$request->user()->isSuperAdmin() && $request->user()->polda_id) {
            $validated['polda_id'] = $request->user()->polda_id;
        }

        Publication::create($validated);

        return redirect()->back()->with('success', 'Konten narasi publikasi berhasil diterbitkan!');
    }

    /**
     * Setujui draf publikasi (mis. rilis dari detail isu) -> terbit.
     */
    public function approve(Request $request, $id)
    {
        $publication = Publication::findOrFail($id);
        $this->authorizeTenantRecord($request, $publication);

        if (! in_array($publication->approval_status, Publication::STATUS_GROUPS['pending'], true)) {
            return redirect()->back()->with('error', 'Hanya draf yang menunggu persetujuan yang dapat disetujui.');
        }

        $publication->update([
            'approval_status' => 'published',
            'reviewed_by'     => auth()->id(),
            'reviewed_at'     => now(),
            'review_notes'    => $request->input('review_notes'),
        ]);

        return redirect()->back()->with('success', 'Publikasi disetujui dan ditandai terbit.');
    }

    /**
     * Tolak draf publikasi dengan catatan revisi wajib.
     */
    public function reject(Request $request, $id)
    {
        $publication = Publication::findOrFail($id);
        $this->authorizeTenantRecord($request, $publication);

        $validated = $request->validate(['review_notes' => 'required|string|max:1000']);

        if (! in_array($publication->approval_status, Publication::STATUS_GROUPS['pending'], true)) {
            return redirect()->back()->with('error', 'Hanya draf yang menunggu persetujuan yang dapat ditolak.');
        }

        $publication->update([
            'approval_status' => 'rejected',
            'reviewed_by'     => auth()->id(),
            'reviewed_at'     => now(),
            'review_notes'    => $validated['review_notes'],
        ]);

        return redirect()->back()->with('success', 'Publikasi ditolak dan catatan revisi dicatat.');
    }
}

