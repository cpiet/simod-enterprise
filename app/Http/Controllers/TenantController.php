<?php

namespace App\Http\Controllers;

use App\Models\Polda;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    /**
     * Ganti Tenant secara aman (Server-Side Session, CSRF Protected, RBAC Enforced).
     * Mencegah URL Parameter Tampering / IDOR pada tautan ?polda=...
     */
    public function switchTenant(Request $request): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'code' => 'required|string|max:50',
        ]);

        $targetCode = strtoupper(trim($validated['code']));
        $user = $request->user();

        // RBAC Enforcement: Cegah IDOR / BOLA / Parameter Tampering
        if ($user && !$user->isSuperAdmin() && $user->polda_id) {
            $userPoldaCode = $user->polda?->code ?? 'SULBAR';
            if ($targetCode !== $userPoldaCode) {
                if ($request->wantsJson()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Akses ditolak: Personel kewilayahan hanya memiliki hak akses di wilayah hukum Polda masing-masing.',
                    ], 403);
                }
                return back()->with('error', 'Akses ditolak: Anda tidak memiliki wewenang untuk berpindah wilayah komando.');
            }
        }

        // Resolusi Polda
        if ($targetCode === 'ALL' || $targetCode === 'DIVHUMAS') {
            $polda = Polda::where('code', 'DIVHUMAS')->first();
            $sessionCode = 'ALL';
        } else {
            $polda = Polda::where('code', $targetCode)->where('is_active', true)->first();
            $sessionCode = $polda ? $polda->code : 'SULBAR';
        }

        if (!$polda) {
            return back()->with('error', 'Wilayah komando tidak ditemukan atau sedang tidak aktif.');
        }

        // Simpan ke Session terenkripsi server
        $request->session()->put('active_polda_code', $sessionCode);
        $request->session()->put('active_polda_id', $polda->id);

        if ($request->wantsJson()) {
            return response()->json([
                'success'       => true,
                'selected_code' => $sessionCode,
                'polda_name'    => $polda->name,
            ]);
        }

        return back()->with('success', "Beralih ke {$polda->name}.");
    }
}

