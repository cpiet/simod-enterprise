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
    /**
     * Apakah kode tenant aktif berarti agregasi seluruh Polda? Secara bawaan TIDAK: Divhumas (kode ALL) hanya melihat data
     * tenant Divhumas sendiri; agregasi nasional hanya bila `simod.national_aggregate` diaktifkan.
     */
    protected function aggregatesNational(?string $selectedCode): bool
    {
        return $selectedCode === 'ALL' && (bool) config('simod.national_aggregate', false);
    }

    /**
     * Pengguna berwenang melihat telemetri teknis AI (nama model, jalur kunci)? Hanya pemegang izin Keamanan & Pemeliharaan.
     * Selain itu antarmuka hanya menampilkan label netral ("Analisis AI", "SADEWA AI").
     */
    protected function isAiAdmin(): bool
    {
        $user = request()->user();

        return (bool) ($user && ($user->isSuperAdmin() || $user->hasPermission('security_center')));
    }

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

        // 2. Mabes / Super Admin: baca dari session. Parameter ?polda= HANYA dihormati di lingkungan
        //    local/testing (kompatibilitas test/CLI); di produksi diabaikan sepenuhnya.
        $queryCode = app()->environment(['local', 'testing']) ? $request->query('polda') : null;
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

    /**
     * Override tenant dari input klien (polda_id / polda_code) -- HANYA untuk Super Admin/Kadivhumas.
     * Personel kewilayahan selalu memakai tenant aktifnya sendiri (mencegah IDOR lintas-Polda).
     */
    protected function requestedPoldaOverride(Request $request, ?Polda $current): ?Polda
    {
        $user = $request->user();
        if (! $user || ! $user->isSuperAdmin()) {
            return $current;
        }

        if ($request->filled('polda_id')) {
            return Polda::find($request->input('polda_id')) ?? $current;
        }
        if ($request->filled('polda_code')) {
            return Polda::where('code', strtoupper((string) $request->input('polda_code')))->first() ?? $current;
        }

        return $current;
    }

    /**
     * ID Polda target untuk operasi tulis: personel kewilayahan dikunci ke Polda-nya, Super Admin boleh memilih.
     */
    protected function scopedPoldaId(Request $request, mixed $requested = null): mixed
    {
        $user = $request->user();
        if ($user && ! $user->isSuperAdmin() && $user->polda_id) {
            return $user->polda_id;
        }

        return $requested ?? $request->session()->get('active_polda_id');
    }

    /**
     * Proteksi Direct Parameter Tampering (IDOR) antar-satwil:
     * Memastikan personel kewilayahan tidak dapat mengakses atau memanipulasi ID record milik Polda lain.
     */
    protected function authorizeTenantRecord(Request $request, object $record): void
    {
        $user = $request->user();
        if ($user && !$user->isSuperAdmin() && $user->polda_id && isset($record->polda_id)) {
            if ((int) $record->polda_id !== (int) $user->polda_id) {
                abort(403, 'Akses Ditolak: Anda tidak memiliki otoritas untuk mengakses atau memodifikasi data milik satuan wilayah lain.');
            }
        }
    }

    /**
     * Data dasar layout (daftar Polda, tenant aktif, metrik sidebar) untuk halaman modul sistem.
     *
     * @return array{0: \Illuminate\Support\Collection, 1: ?\App\Models\Polda, 2: string, 3: array<string, mixed>}
     */
    protected function getBaseData(Request $request): array
    {
        $allPoldas = \App\Models\Polda::withCount('polres')->where('is_active', true)->get();
        [$selectedCode, $selectedPolda, $poldaId] = $this->resolveActiveTenant($request);
        $isNational = $this->aggregatesNational($selectedCode);

        $metrics = [
            'total_issues'       => \App\Models\Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'     => \App\Models\Issue::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'active_tasks'       => \App\Models\Task::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => \App\Models\Publication::when(!$isNational && $selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ($isNational ? ' WIB' : ' WITA'),
        ];

        return [$allPoldas, $selectedPolda, $selectedCode, $metrics];
    }
}
