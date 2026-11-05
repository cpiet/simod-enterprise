<?php

namespace App\Http\Middleware;

use App\Models\RolePermission;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Penegakan hak akses (RBAC) di sisi server berdasarkan matriks modul `/roles`.
 *
 * Pemakaian: `->middleware('module:issues_view')` atau beberapa kunci (salah satu cukup):
 * `->middleware('module:tasks_execute|tasks_disposition')`.
 *
 * Super Admin / Kadivhumas (`User::isSuperAdmin()`) selalu lolos. Pengguna lain harus memiliki
 * minimal satu kunci modul yang diberikan menurut `RolePermission::isGranted()` (kustomisasi per Polda
 * di database, dengan fallback ke peran bawaan).
 */
class EnsureModulePermission
{
    public function handle(Request $request, Closure $next, string $modules): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->guest(route('login'));
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        foreach (explode('|', $modules) as $key) {
            if (RolePermission::isGranted((string) ($user->role ?? 'operator'), trim($key), $user->polda_id)) {
                return $next($request);
            }
        }

        $message = 'Akses ditolak: peran Anda tidak memiliki hak akses ke modul ini.';

        if ($request->expectsJson()) {
            return response()->json(['success' => false, 'message' => $message], 403);
        }

        abort(403, $message);
    }
}
