<?php

namespace App\Http\Controllers;

use App\Models\Issue;
use App\Models\Polda;
use App\Models\Polres;
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

        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::with(['polres'])->where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        $query = Issue::with(['polres', 'polda'])->latest();

        if ($selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        if ($priorityFilter !== 'all') {
            $query->where('priority', $priorityFilter);
        }

        $issues = $query->paginate(15)->withQueryString();

        $metrics = [
            'total_issues'    => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'monitoring'      => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'monitoring')->count(),
            'investigasi'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'investigasi')->count(),
            'klarifikasi'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'klarifikasi')->count(),
            'hoax_count'      => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('is_hoax', true)->count(),
        ];

        return Inertia::render('Issues/Index', [
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'issues'         => $issues,
            'filters'        => [
                'status'   => $statusFilter,
                'priority' => $priorityFilter,
            ],
            'metrics'        => $metrics,
        ]);
    }

    /**
     * Store a newly created issue in storage (e.g. from + Buat Isu card modal).
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'polda_id'         => 'required|exists:poldas,id',
            'polres_id'        => 'nullable|exists:polres,id',
            'title'            => 'required|string|max:255',
            'summary'          => 'required|string',
            'category'         => 'required|string',
            'priority'         => 'required|in:low,medium,high,critical',
            'sentiment'        => 'required|in:positif,netral,negatif',
            'source_media'     => 'nullable|string|max:100',
            'source_url'       => 'nullable|string|max:500',
            'assigned_unit'    => 'nullable|string|max:100',
            'is_hoax'          => 'nullable|boolean',
        ]);

        $validated['slug'] = Str::slug($validated['title']) . '-' . Str::random(5);
        $validated['status'] = 'monitoring';
        $validated['virality_score'] = rand(40, 85);
        $validated['engagement_count'] = rand(1000, 15000);

        Issue::create($validated);

        return redirect()->back()->with('success', 'Isu humas berhasil dibuat dan masuk ke antrean pemantauan!');
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

        $issue->update([
            'is_hoax'                => $validated['is_hoax'],
            'fact_check_result'      => $validated['fact_check_result'],
            'official_clarification' => $validated['official_clarification'],
            'clarification_url'      => $validated['clarification_url'] ?? null,
            'status'                 => 'klarifikasi',
            'clarified_at'           => now(),
        ]);

        return redirect()->back()->with('success', 'Klarifikasi resmi dan hasil fact-check berhasil diperbarui!');
    }
}
