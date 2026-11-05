<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Tamu (belum login): tidak ada data tenant, navigasi, metrik, atau identitas pimpinan yang dibagikan.
        if (! $user) {
            return [
                ...parent::share($request),
                'csrf_token'     => csrf_token(),
                'allPoldas'      => [],
                'selectedPolda'  => null,
                'selectedCode'   => null,
                'navigation'     => [],
                'auth'           => ['user' => null, 'role' => null, 'permissions' => []],
                'sidebarMetrics' => [],
                'flash' => [
                    'success' => fn () => $request->session()->get('success'),
                    'error'   => fn () => $request->session()->get('error'),
                ],
            ];
        }

        if ($user && !$user->isSuperAdmin() && $user->polda_id) {
            // Regional Officer: Hard-locked to their designated Polda
            $userPolda = $user->polda ?? \App\Models\Polda::find($user->polda_id);
            $selectedCode = $userPolda?->code ?? 'SULBAR';
            $request->session()->put('active_polda_code', $selectedCode);
            $request->session()->put('active_polda_id', $userPolda?->id);
        } else {
            // Mabes / Super Admin: session. Query ?polda= hanya dihormati di local/testing.
            $queryCode = app()->environment(['local', 'testing']) ? $request->query('polda') : null;
            if (!empty($queryCode)) {
                $selectedCode = strtoupper(trim($queryCode));
                $request->session()->put('active_polda_code', $selectedCode);
            } else {
                $selectedCode = $request->session()->get('active_polda_code', 'ALL');
            }
        }

        // Data bersama dicache; versi dinaikkan otomatis saat Polda/Polres/Menu/RolePermission berubah.
        $ver = Cache::get('simod.shell_version', '0');

        $allPoldas = Cache::remember("simod.share.poldas.$ver", 300, fn () => \App\Models\Polda::withCount('polres')->where('is_active', true)->orderBy('name')->get());

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

        // Hitungan badge sidebar dicache singkat (15 dtk) per tenant; jam server selalu dihitung segar.
        $counts = Cache::remember("simod.share.metrics.$ver.$poldaId", 15, fn () => [
            'total_issues'       => \App\Models\Issue::when($poldaId, fn ($q) => $q->where('polda_id', $poldaId))->count(),
            'pending_verify'     => \App\Models\Issue::when($poldaId, fn ($q) => $q->where('polda_id', $poldaId))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'active_tasks'       => \App\Models\Task::when($poldaId, fn ($q) => $q->where('polda_id', $poldaId))->where('status', 'in_progress')->count(),
            'total_publications' => \App\Models\Publication::when($poldaId, fn ($q) => $q->where('polda_id', $poldaId))->count(),
        ]);

        $sidebarMetrics = $counts + [
            'server_wita' => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ($selectedCode === 'ALL' ? ' WIB' : ' WITA'),
        ];

        // Resolusi Peran Aktif & Hak Akses (RBAC)
        $user = $request->user();

        // Simulasi peran (?role=) hanya untuk Super Admin/Kadiv yang sedang login dan hanya memengaruhi
        // tampilan menu; hak akses backend tidak berubah. Pengguna lain: simulasi dibersihkan.
        $simulatedRole = null;
        if ($user && $user->isSuperAdmin()) {
            if ($request->has('role')) {
                $request->session()->put('active_simulated_role', $request->query('role'));
            }
            $simulatedRole = $request->session()->get('active_simulated_role');
        } else {
            $request->session()->forget('active_simulated_role');
        }

        $activeRole = $simulatedRole ?: ($user?->role ?: ($selectedCode === 'ALL' ? 'kadiv_humas' : 'polda_leader'));
        $targetPoldaId = $selectedPolda?->id ?: (\App\Models\Polda::where('code', 'DIVHUMAS')->value('id'));
        [$activePermissions, $dynamicNavigation] = Cache::remember("simod.share.nav.$ver.$activeRole.$targetPoldaId", 300, function () use ($activeRole, $targetPoldaId) {
            $perms = \App\Models\RolePermission::getPermissionsForRole($activeRole, $targetPoldaId);

            return [$perms, \App\Models\MenuItem::getNavigationForUser($activeRole, $perms)];
        });

        return [
            ...parent::share($request),
            'csrf_token'     => csrf_token(),
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
                    'real_role' => $user->role,
                    'can_simulate_role' => $user->isSuperAdmin(),
                    'polda' => $user->polda ?? null,
                ] : null,
                'role'        => $activeRole,
                'permissions' => $activePermissions,
            ],
            // Indikator pemakaian AI untuk pengguna (netral, tanpa vendor/model); dievaluasi malas per permintaan.
            'aiQuota' => fn () => $user ? app(\App\Services\AiQuota::class)->publicStatus($user, $selectedPolda?->id) : null,
            'sidebarMetrics' => $sidebarMetrics,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error'   => fn () => $request->session()->get('error'),
            ],
        ];
    }
}
