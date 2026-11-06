<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Masuk cepat dengan PIN 6 angka di perangkat tepercaya.
 *
 * Urutan wajib: (1) pengguna masuk dengan nama pengguna + kata sandi yang benar, (2) di Profil memasang PIN dengan
 * konfirmasi kata sandi sehingga perangkat didaftarkan, (3) baru setelah itu halaman masuk di perangkat itu menawarkan PIN
 * (dengan tombol angka virtual). PIN saja tidak cukup: harus disertai cookie perangkat terenkripsi yang hanya ada di
 * perangkat terdaftar. Lima PIN salah mencabut perangkat; ganti/reset kata sandi atau penonaktifan akun mencabut semua perangkat.
 */
class QuickLoginController extends Controller
{
    /** Perangkat sah dari cookie (belum kedaluwarsa, pemilik aktif); null bila tidak ada. */
    public static function deviceFrom(Request $request): ?TrustedDevice
    {
        $token = $request->cookie(TrustedDevice::COOKIE);
        if (! is_string($token) || strlen($token) !== 64) {
            return null;
        }

        $device = TrustedDevice::with('user')
            ->where('token_hash', TrustedDevice::hashToken($token))
            ->where('expires_at', '>', now())
            ->first();

        return $device && $device->user && $device->user->is_active ? $device : null;
    }

    /** "lutfi_alfandra" -> "lu***ra": cukup dikenali pemiliknya, tidak membuka nama pengguna utuh. */
    public static function maskUsername(string $username): string
    {
        $len = mb_strlen($username);
        if ($len <= 4) {
            return mb_substr($username, 0, 1) . str_repeat('*', max(1, $len - 1));
        }

        return mb_substr($username, 0, 2) . '***' . mb_substr($username, -2);
    }

    private function deviceCookie(string $token)
    {
        return cookie(
            TrustedDevice::COOKIE,
            $token,
            TrustedDevice::LIFETIME_DAYS * 24 * 60,
            config('session.path', '/'),
            config('session.domain'),
            (bool) (config('session.secure') ?? request()->isSecure()),
            true,   // httpOnly: tidak dapat dibaca skrip halaman
            false,
            'lax'
        );
    }

    private function audit(Request $request, ?User $user, string $action, array $meta = []): void
    {
        AuditLog::create([
            'polda_id'     => $user?->polda_id,
            'user_id'      => $user?->id,
            'action'       => $action,
            'subject_type' => 'user',
            'subject_id'   => (string) ($user?->id ?? ''),
            'ip_address'   => $request->ip(),
            'user_agent'   => substr((string) $request->userAgent(), 0, 250),
            'metadata'     => $meta,
            'created_at'   => now(),
        ]);
    }

    /** Pasang PIN & daftarkan perangkat ini (pengguna sudah login; wajib kata sandi saat ini). */
    public function enroll(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string',
            'pin'              => 'required|digits:6|confirmed',
        ], [
            'pin.digits'    => 'PIN harus tepat 6 angka.',
            'pin.confirmed' => 'Konfirmasi PIN tidak sama.',
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Kata sandi saat ini tidak sesuai.']);
        }
        if (TrustedDevice::isWeakPin($data['pin'])) {
            return back()->withErrors(['pin' => 'PIN terlalu mudah ditebak (angka kembar atau berurutan). Pilih kombinasi lain.']);
        }

        // Ganti pendaftaran lama di perangkat ini (bila ada).
        $existing = $request->cookie(TrustedDevice::COOKIE);
        if (is_string($existing) && strlen($existing) === 64) {
            TrustedDevice::where('user_id', $user->id)->where('token_hash', TrustedDevice::hashToken($existing))->delete();
        }

        $token = Str::random(64);
        TrustedDevice::create([
            'user_id'      => $user->id,
            'token_hash'   => TrustedDevice::hashToken($token),
            'pin_hash'     => Hash::make($data['pin']),
            'label'        => mb_substr((string) $request->userAgent(), 0, 160),
            'last_ip'      => $request->ip(),
            'last_used_at' => now(),
            'expires_at'   => now()->addDays(TrustedDevice::LIFETIME_DAYS),
        ]);

        $this->audit($request, $user, 'auth.pin_enabled');

        return back()->with('success', 'PIN dipasang. Di perangkat ini Anda dapat masuk cepat dengan PIN 6 angka.')
            ->withCookie($this->deviceCookie($token));
    }

    /** Cabut perangkat ini, atau semua perangkat milik pengguna (`all=1`). */
    public function revoke(Request $request)
    {
        $user = $request->user();

        if ($request->boolean('all')) {
            TrustedDevice::where('user_id', $user->id)->delete();
        } else {
            $token = $request->cookie(TrustedDevice::COOKIE);
            if (is_string($token) && strlen($token) === 64) {
                TrustedDevice::where('user_id', $user->id)->where('token_hash', TrustedDevice::hashToken($token))->delete();
            }
        }

        $this->audit($request, $user, 'auth.pin_disabled', ['all' => $request->boolean('all')]);

        return back()->with('success', $request->boolean('all') ? 'PIN dicabut dari semua perangkat.' : 'PIN dicabut dari perangkat ini.')
            ->withCookie(Cookie::forget(TrustedDevice::COOKIE, config('session.path', '/'), config('session.domain')));
    }

    /** "Bukan Anda?" di halaman masuk: lupakan perangkat ini tanpa harus login. */
    public function forget(Request $request)
    {
        $device = self::deviceFrom($request);
        if ($device) {
            $device->delete();
        }

        return redirect()->route('login')
            ->withCookie(Cookie::forget(TrustedDevice::COOKIE, config('session.path', '/'), config('session.domain')));
    }

    /** Masuk dengan PIN (hanya bersama cookie perangkat terdaftar). */
    public function login(Request $request)
    {
        $data = $request->validate(['pin' => 'required|digits:6']);

        $device = self::deviceFrom($request);
        $fail = fn (string $msg) => back()->withErrors(['pin' => $msg]);

        if (! $device) {
            return $fail('Perangkat ini belum terdaftar untuk masuk dengan PIN. Masuk dengan kata sandi.')
                ->withCookie(Cookie::forget(TrustedDevice::COOKIE, config('session.path', '/'), config('session.domain')));
        }

        $user = $device->user;

        if (! Hash::check($data['pin'], $device->pin_hash)) {
            $device->increment('failed_attempts');
            $this->audit($request, $user, 'auth.pin_failed', ['attempts' => $device->failed_attempts]);

            if ($device->failed_attempts >= TrustedDevice::MAX_ATTEMPTS) {
                $device->delete();
                $this->audit($request, $user, 'auth.pin_revoked', ['reason' => 'terlalu banyak PIN salah']);

                return $fail('PIN salah terlalu sering. Perangkat dicabut; masuk dengan nama pengguna dan kata sandi.')
                    ->withCookie(Cookie::forget(TrustedDevice::COOKIE, config('session.path', '/'), config('session.domain')));
            }

            $left = TrustedDevice::MAX_ATTEMPTS - $device->failed_attempts;

            return $fail("PIN tidak sesuai. Sisa percobaan: {$left}.");
        }

        // Berhasil: putar token perangkat, perpanjang masa berlaku, nolkan percobaan gagal.
        $token = Str::random(64);
        $device->update([
            'token_hash'      => TrustedDevice::hashToken($token),
            'failed_attempts' => 0,
            'last_used_at'    => now(),
            'last_ip'         => $request->ip(),
            'expires_at'      => now()->addDays(TrustedDevice::LIFETIME_DAYS),
        ]);

        return app(AuthController::class)->completeLogin($request, $user, 'auth.login_pin')
            ->withCookie($this->deviceCookie($token));
    }
}
