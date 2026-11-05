<?php

namespace App\Http\Middleware;

use App\Models\SecuritySetting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengakhiri sesi pengguna yang tidak aktif melebihi `session_timeout_minutes`
 * (Pengaturan Keamanan, per Polda atau global).
 */
class EnforceSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            $minutes = (int) SecuritySetting::current($user->polda_id)->session_timeout_minutes;
            $last = (int) $request->session()->get('last_activity_at', 0);

            if ($minutes > 0 && $last > 0 && (time() - $last) > ($minutes * 60)) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->header('X-Inertia') || $request->expectsJson()) {
                    return response('', 409)->header('X-Inertia-Location', route('login'));
                }

                return redirect()->route('login')->with('error', 'Sesi berakhir karena tidak aktif selama ' . $minutes . ' menit. Silakan masuk kembali.');
            }

            $request->session()->put('last_activity_at', time());
        }

        return $next($request);
    }
}
