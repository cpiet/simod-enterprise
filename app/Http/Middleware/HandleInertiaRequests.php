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
        // 1. Multi-Tenant Session & Security Enforcement (Anti-Tampering & RBAC Lock)
        $user = $request->user();
        if ($user && !$user->isSuperAdmin() && $user->polda_id) {
            // Regional Officer: Hard-locked to their designated Polda
            $userPolda = $user->polda ?? \App\Models\Polda::find($user->polda_id);
            $selectedCode = $userPolda?->code ?? 'SULBAR';
            $request->session()->put('active_polda_code', $selectedCode);
            $request->session()->put('active_polda_id', $userPolda?->id);
        } else {
            // Mabes / Super Admin: Read from query (if explicitly passed in test/cli) or session
            $queryCode = $request->query('polda');
            if (!empty($queryCode)) {
                $selectedCode = strtoupper(trim($queryCode));
                $request->session()->put('active_polda_code', $selectedCode);
            } else {
                $selectedCode = $request->session()->get('active_polda_code', 'ALL');
            }
        }

        $allPoldas = \App\Models\Polda::withCount('polres')->where('is_active', true)->orderBy('name')->get();

        $selectedPolda = null;
        if ($selectedCode === 'ALL' || $selectedCode === 'DIVHUMAS') {
            $selectedPolda = $allPoldas->firstWhere('code', 'DIVHUMAS') ?? \App\Models\Polda::where('code', 'DIVHUMAS')->first();
            $selectedCode = 'ALL';
        } else {
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
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ($selectedCode === 'ALL' ? ' WIB' : ' WITA'),
        ];

        // Resolusi Peran Aktif & Hak Akses (RBAC)
        $user = $request->user();
        $simulatedRole = $request->query('role') ?? $request->session()->get('active_simulated_role');
        if ($request->has('role')) {
            $request->session()->put('active_simulated_role', $request->query('role'));
            $simulatedRole = $request->query('role');
        }

        $activeRole = $simulatedRole ?: ($user?->role ?: ($selectedCode === 'ALL' ? 'kadiv_humas' : 'polda_leader'));
        $targetPoldaId = $selectedPolda?->id ?: (\App\Models\Polda::where('code', 'DIVHUMAS')->value('id'));
        $activePermissions = \App\Models\RolePermission::getPermissionsForRole($activeRole, $targetPoldaId);
        $dynamicNavigation = \App\Models\MenuItem::getNavigationForUser($activeRole, $activePermissions);

        return [
            ...parent::share($request),
            'allPoldas'      => $allPoldas,
            'selectedPolda'  => $selectedPolda,
            'selectedCode'   => $selectedCode,
            'navigation'     => $dynamicNavigation,
            'auth' => [
                'user' => $user ? [
                    'id'    => $user->id,
                    'name'  => $user->name,
                    'email' => $user->email,
                    'role'  => $activeRole,
                    'polda' => $user->polda ?? null,
                ] : [
                    'id'    => 1,
                    'name'  => $selectedCode === 'ALL' ? 'Irjen Pol. Johnny Eddizon Isir, S.I.K., M.T.C.P.' : ($selectedPolda?->kabid_humas_name ?? 'Kombes Pol. Slamet Wahyudi'),
                    'email' => 'pimpinan@polri.go.id',
                    'role'  => $activeRole,
                ],
                'role'        => $activeRole,
                'permissions' => $activePermissions,
            ],
            'sidebarMetrics' => $sidebarMetrics,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
