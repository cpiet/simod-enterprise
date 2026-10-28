<?php

namespace App\Http\Controllers;

use App\Models\Polda;
use App\Models\Task;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TaskController extends Controller
{
    /**
     * Display listing of tasks & dispatch.
     */
    public function index(Request $request): Response
    {
        $selectedCode = $request->query('polda', 'SULBAR');
        $statusFilter = $request->query('status', 'all');

        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        $query = Task::with(['polres', 'issue', 'updates'])->latest();

        if ($selectedPolda) {
            $query->where('polda_id', $selectedPolda->id);
        }

        if ($statusFilter !== 'all') {
            $query->where('status', $statusFilter);
        }

        $tasks = $query->paginate(15)->withQueryString();

        $metrics = [
            'total'       => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'in_progress' => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'urgent'      => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('priority', 'urgent')->count(),
            'completed'   => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'completed')->count(),
        ];

        return Inertia::render('Tasks/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'tasks'         => $tasks,
            'statusFilter'  => $statusFilter,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Store a new task dispatch.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'polda_id'         => 'required|exists:poldas,id',
            'title'            => 'required|string|max:255',
            'instructions'     => 'required|string',
            'assigned_unit'    => 'required|string|max:150',
            'assigned_officer' => 'nullable|string|max:100',
            'priority'         => 'required|in:low,medium,high,urgent',
            'due_at'           => 'nullable|date',
        ]);

        $validated['status'] = 'in_progress';

        Task::create($validated);

        return redirect()->back()->with('success', 'Disposisi tugas berhasil dikirimkan ke Satker/Personel terkait!');
    }

    /**
     * Add update/progress to task.
     */
    public function addUpdate(Request $request, $id)
    {
        $task = Task::findOrFail($id);
        $request->validate(['note' => 'required|string']);

        $task->updates()->create([
            'note' => $request->input('note'),
        ]);

        if ($request->boolean('mark_completed', false)) {
            $task->update(['status' => 'completed', 'closed_at' => now()]);
        }

        return redirect()->back()->with('success', 'Laporan progres tugas berhasil ditambahkan!');
    }
}
