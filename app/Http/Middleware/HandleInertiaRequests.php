<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        // 1. Multi-Tenant Session & Query Parameter Synchronization
        $selectedCode = $request->query('polda');
        if (!empty($selectedCode)) {
            $request->session()->put('active_polda_code', $selectedCode);
        } else {
            $selectedCode = $request->session()->get('active_polda_code', 'SULBAR');
        }

        $allPoldas = \App\Models\Polda::withCount('polres')->where('is_active', true)->orderBy('name')->get();

        $selectedPolda = null;
        if ($selectedCode !== 'ALL') {
            $selectedPolda = $allPoldas->firstWhere('code', $selectedCode);
            if (!$selectedPolda && $allPoldas->isNotEmpty()) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda->code;
            }
        }

        if ($selectedPolda) {
            $request->session()->put('active_polda_id', $selectedPolda->id);
            config(['app.timezone' => $selectedPolda->timezone ?? 'Asia/Makassar']);
        }

        $poldaId = $selectedPolda?->id;

        $sidebarMetrics = [
            'total_issues'       => \App\Models\Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->count(),
            'pending_verify'     => \App\Models\Issue::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'active_tasks'       => \App\Models\Task::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->where('status', 'in_progress')->count(),
            'total_publications' => \App\Models\Publication::when($poldaId, fn($q) => $q->where('polda_id', $poldaId))->count(),
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return [
            ...parent::share($request),
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'auth' => [
                'user' => $request->user() ? [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                    'role' => $request->user()->role ?? 'operator',
                    'polda' => $request->user()->polda ?? null,
                ] : null,
            ],
            'sidebarMetrics' => $sidebarMetrics,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
