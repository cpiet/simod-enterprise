<?php

namespace App\Http\Controllers;

use App\Models\Polda;
use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Resolusi Tenant Komando secara aman berbasis Server-Side Session & RBAC Lock.
     * Mencegah manipulasi URL parameter ?polda=... dari peretas/user tidak berwenang.
     *
     * @return array{0: string, 1: ?Polda, 2: ?int} [$selectedCode, $selectedPolda, $poldaId]
     */
    protected function resolveActiveTenant(Request $request): array
    {
        $user = $request->user();

        // 1. RBAC Hard-Lock: Jika user kewilayahan (bukan Super Admin Mabes / Kadiv Humas), kunci ke Polda user
        if ($user && !$user->isSuperAdmin() && $user->polda_id) {
            $polda = $user->polda ?? Polda::find($user->polda_id);
            $selectedCode = $polda?->code ?? 'SULBAR';
            $request->session()->put('active_polda_code', $selectedCode);
            $request->session()->put('active_polda_id', $polda?->id);
            return [$selectedCode, $polda, $polda?->id];
        }

        // 2. Mabes / Super Admin: Baca dari parameter (jika ada, untuk backward compatibility/testing) atau session
        $queryCode = $request->query('polda');
        if (!empty($queryCode)) {
            $selectedCode = strtoupper(trim($queryCode));
            $request->session()->put('active_polda_code', $selectedCode);
        } else {
            $selectedCode = $request->session()->get('active_polda_code', 'ALL');
        }

        $allPoldas = Polda::where('is_active', true)->get();

        if ($selectedCode === 'ALL' || $selectedCode === 'DIVHUMAS') {
            $selectedPolda = $allPoldas->firstWhere('code', 'DIVHUMAS') ?? Polda::where('code', 'DIVHUMAS')->first();
            $selectedCode = 'ALL';
        } else {
            $selectedPolda = $allPoldas->firstWhere('code', $selectedCode);
            if (!$selectedPolda && $allPoldas->isNotEmpty()) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda->code;
            }
        }

        if (!$selectedPolda) {
            $selectedPolda = $allPoldas->first() ?? Polda::first();
        }

        if ($selectedPolda) {
            $request->session()->put('active_polda_id', $selectedPolda->id);
        }

        return [$selectedCode, $selectedPolda, $selectedPolda?->id];
    }
}
