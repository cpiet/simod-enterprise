<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Show the Multi-Tenant Login Form.
     * Protected by 'guest' middleware: redirects to dashboard if already authenticated.
     */
    public function showLogin(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        // Tanpa daftar Polda/akun ke klien: halaman masuk tidak membocorkan struktur organisasi maupun nama akun.
        return Inertia::render('Auth/Login');
    }

    /**
     * Handle Login Submission (Password or 6-Digit PIN Mode).
     */
    public function login(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'username'   => 'required|string|max:120',
            'password'   => 'required|string|max:255',
            'remember'   => 'nullable|boolean',
        ]);

        $username = trim($validated['username']);
        $password = $validated['password'];

        $user = User::where('username', $username)
            ->orWhere('email', $username)
            ->orWhere('badge_number', $username)
            ->first();

        // Pesan galat seragam agar username yang valid tidak dapat dienumerasi.
        $invalid = ['username' => 'Kredensial username atau kata sandi dinas tidak sesuai.'];

        // Penguncian akun nyata: batas percobaan & durasi kunci dari Pengaturan Keamanan.
        $security = SecuritySetting::current($user?->polda_id);
        $lockKey = 'login:' . strtolower($username) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($lockKey, (int) $security->max_login_attempts)) {
            $minutes = (int) ceil(RateLimiter::availableIn($lockKey) / 60);

            return back()->withErrors([
                'username' => 'Terlalu banyak percobaan masuk. Coba lagi dalam ' . max(1, $minutes) . ' menit.',
            ])->onlyInput('username');
        }

        if (! $user || ! Hash::check($password, $user->password)) {
            RateLimiter::hit($lockKey, (int) $security->lockout_minutes * 60);

            AuditLog::create([
                'polda_id'     => $user?->polda_id,
                'user_id'      => $user?->id,
                'action'       => 'auth.login_failed',
                'subject_type' => 'user',
                'subject_id'   => (string) ($user?->id ?? $username),
                'ip_address'   => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 250),
                'metadata'     => ['username' => $username],
                'created_at'   => now(),
            ]);

            return back()->withErrors($invalid)->onlyInput('username');
        }

        if (! $user->is_active) {
            return back()->withErrors([
                'username' => 'Akun personel ini sedang dinonaktifkan oleh Administrator.',
            ])->onlyInput('username');
        }

        RateLimiter::clear($lockKey);
        Auth::login($user, $request->boolean('remember', false));

        // Clear explicit logout lock & regenerate session ID to prevent session fixation
        $request->session()->forget('simod_logged_out');
        $request->session()->regenerate();

        // Tenant aktif ditentukan oleh akun (bukan pilihan di formulir): personel daerah terkunci ke Polda-nya,
        // Super Admin/Kadivhumas masuk ke lingkup Divhumas dan dapat berpindah lewat POST /tenant/switch.
        $targetCode = 'ALL';
        if (! $user->isSuperAdmin() && $user->polda_id) {
            $userPolda = Polda::find($user->polda_id);
            $targetCode = $userPolda?->code ?? 'SULBAR';
            $request->session()->put('active_polda_id', $userPolda?->id);
        }

        $request->session()->put('active_polda_code', $targetCode);

        AuditLog::create([
            'polda_id'     => $user?->polda_id ?? $request->session()->get('active_polda_id'),
            'user_id'      => $user?->id,
            'action'       => 'auth.login',
            'subject_type' => 'user',
            'subject_id'   => (string) ($user?->id ?? $username),
            'ip_address'   => $request->ip(),
            'user_agent'   => substr((string) $request->userAgent(), 0, 250),
            'metadata'     => ['username' => $username, 'tenant' => $targetCode],
            'created_at'   => now(),
        ]);

        return redirect()->route('dashboard');
    }

    /**
     * Handle Secure Logout:
     * 1. Auth::logout()
     * 2. $request->session()->invalidate()
     * 3. $request->session()->regenerateToken()
     * 4. Clear browser auth cookies & prevent back-button access
     */
    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            AuditLog::create([
                'polda_id'     => $user->polda_id ?? $request->session()->get('active_polda_id'),
                'user_id'      => $user->id,
                'action'       => 'auth.logout',
                'subject_type' => 'user',
                'subject_id'   => (string) $user->id,
                'ip_address'   => $request->ip(),
                'user_agent'   => substr((string) $request->userAgent(), 0, 250),
                'metadata'     => ['username' => $user->username],
                'created_at'   => now(),
            ]);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
        $request->session()->put('simod_logged_out', true);

        $sessionCookie = config('session.cookie');
        $recallerCookie = Auth::guard('web')->getRecallerName();

        return redirect()->route('login')
            ->withCookie(cookie()->forget($sessionCookie))
            ->withCookie(cookie()->forget($recallerCookie))
            ->with('success', 'Sesi dinas Anda telah diakhiri dan diamankan.');
    }
}
