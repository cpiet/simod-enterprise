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
        $selectedCode = $request->query('polda', 'SULBAR');
        $selectedPolda = null;
        if ($selectedCode !== 'ALL') {
            $selectedPolda = \App\Models\Polda::where('code', $selectedCode)->first();
        }

        $sidebarMetrics = [
            'total_issues'       => \App\Models\Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'     => \App\Models\Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'active_tasks'       => \App\Models\Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => \App\Models\Publication::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return [
            ...parent::share($request),
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
