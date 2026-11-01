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

        $query = Publication::with(['polres', 'issue'])->latest();

        if (!$isNational && $selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }

        if ($channelFilter !== 'all') {
            $query->where('channel', $channelFilter);
        }

        $publications = $query->paginate(12)->withQueryString();

        $metrics = [
            'total'        => Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'tribratanews' => Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('channel', 'tribratanews')->count(),
            'instagram'    => Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('channel', 'instagram')->count(),
            'tiktok'       => Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('channel', 'tiktok')->count(),
        ];

        return Inertia::render('Publications/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'publications'  => $publications,
            'channelFilter' => $channelFilter,
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

        Publication::create($validated);

        return redirect()->back()->with('success', 'Konten narasi publikasi berhasil diterbitkan!');
    }
}

