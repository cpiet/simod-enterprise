<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Masuk cepat PIN: hanya setelah kata sandi benar + perangkat didaftarkan; PIN tanpa cookie perangkat tidak berguna. */
class QuickPinLoginTest extends TestCase
{
    use RefreshDatabase;

    private const PASS = 'KataSandiKuat#2026';

    private function user(array $over = []): User
    {
        return User::create($over + ['name' => 'Lutfi Alfandra', 'username' => 'lutfi_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => self::PASS, 'role' => 'super_admin_mabes', 'is_active' => true]);
    }

    /** Daftarkan perangkat lewat Profil; mengembalikan nilai cookie perangkat. */
    private function enroll(User $u, string $pin = '251081'): string
    {
        $res = $this->actingAs($u)->post('/profile/pin', ['current_password' => self::PASS, 'pin' => $pin, 'pin_confirmation' => $pin]);
        $res->assertSessionHasNoErrors();
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === TrustedDevice::COOKIE);
        $this->assertNotNull($cookie, 'Cookie perangkat harus terpasang.');
        $this->assertTrue($cookie->isHttpOnly());

        auth()->logout();
        $this->flushSession();

        return $cookie->getValue();
    }

    public function test_pin_cannot_be_enabled_without_correct_password_or_with_weak_pin(): void
    {
        $u = $this->user();

        $this->actingAs($u)->post('/profile/pin', ['current_password' => 'salah-sekali-12', 'pin' => '251081', 'pin_confirmation' => '251081'])->assertSessionHasErrors('current_password');
        $this->actingAs($u)->post('/profile/pin', ['current_password' => self::PASS, 'pin' => '123456', 'pin_confirmation' => '123456'])->assertSessionHasErrors('pin');
        $this->actingAs($u)->post('/profile/pin', ['current_password' => self::PASS, 'pin' => '111111', 'pin_confirmation' => '111111'])->assertSessionHasErrors('pin');
        $this->actingAs($u)->post('/profile/pin', ['current_password' => self::PASS, 'pin' => '12ab56', 'pin_confirmation' => '12ab56'])->assertSessionHasErrors('pin');
        $this->assertSame(0, TrustedDevice::count());
    }

    public function test_login_page_offers_pin_only_on_registered_device_and_stores_hashes_only(): void
    {
        $u = $this->user();
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->where('quickUser', null));

        $cookie = $this->enroll($u);
        $dev = TrustedDevice::first();
        $plain = \Illuminate\Cookie\CookieValuePrefix::remove(app('encrypter')->decrypt($cookie, false));
        $this->assertSame(64, strlen($plain));
        $this->assertNotSame($plain, $dev->token_hash, 'Token tidak disimpan polos.');
        $this->assertSame(hash('sha256', $plain), $dev->token_hash);
        $this->assertNotSame('251081', $dev->pin_hash);

        $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->get('/login')
            ->assertInertia(fn (Assert $p) => $p->where('quickUser.name', 'Lutfi Alfandra')->missing('quickUser.email')->has('quickUser.username'));
    }

    public function test_pin_login_works_with_device_cookie_and_rotates_token(): void
    {
        $u = $this->user();
        $cookie = $this->enroll($u);

        $res = $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/pin', ['pin' => '251081']);
        $res->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_pin', 'user_id' => $u->id]);

        $new = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === TrustedDevice::COOKIE);
        $this->assertNotSame($cookie, $new->getValue(), 'Token perangkat diputar setiap dipakai.');
    }

    public function test_pin_login_works_after_explicit_logout(): void
    {
        $u = $this->user();
        $res = $this->actingAs($u)->post('/profile/pin', ['current_password' => self::PASS, 'pin' => '251081', 'pin_confirmation' => '251081']);
        $cookie = collect($res->headers->getCookies())->first(fn ($c) => $c->getName() === TrustedDevice::COOKIE)->getValue();

        $this->post('/logout')->assertRedirect(route('login'));   // menandai sesi sebagai keluar eksplisit
        $this->assertGuest();

        $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/pin', ['pin' => '251081'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($u);
    }

    public function test_pin_without_device_cookie_is_useless(): void
    {
        $u = $this->user();
        $this->enroll($u);

        $this->post('/login/pin', ['pin' => '251081'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_five_wrong_pins_revoke_device(): void
    {
        $u = $this->user();
        $cookie = $this->enroll($u);

        for ($i = 0; $i < 4; $i++) {
            $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/pin', ['pin' => '000001'])->assertSessionHasErrors('pin');
        }
        $this->assertSame(1, TrustedDevice::count());

        $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/pin', ['pin' => '000001'])->assertSessionHasErrors('pin');
        $this->assertSame(0, TrustedDevice::count(), 'Perangkat dicabut setelah 5 PIN salah.');

        $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/pin', ['pin' => '251081'])->assertSessionHasErrors('pin');
        $this->assertGuest();
    }

    public function test_password_change_or_deactivation_revokes_devices(): void
    {
        $u = $this->user();
        $this->enroll($u);
        $u->update(['password' => 'KataSandiBaru#2027']);
        $this->assertSame(0, TrustedDevice::count());

        $u2 = $this->user();
        $cookie = $this->enroll($u2);
        $u2->update(['is_active' => false]);
        $this->assertSame(0, TrustedDevice::count());
        $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/pin', ['pin' => '251081'])->assertSessionHasErrors('pin');
    }

    public function test_user_can_revoke_and_device_can_be_forgotten_from_login_page(): void
    {
        $u = $this->user();
        $cookie = $this->enroll($u);

        $this->withUnencryptedCookie(TrustedDevice::COOKIE, $cookie)->post('/login/forget-device')->assertRedirect(route('login'));
        $this->assertSame(0, TrustedDevice::count());

        $cookie = $this->enroll($u);
        $this->actingAs($u)->delete('/profile/pin', ['all' => true])->assertSessionHasNoErrors();
        $this->assertSame(0, TrustedDevice::count());
    }

    public function test_super_admin_sees_accounts_of_all_poldas_in_divhumas_view(): void
    {
        $div = Polda::create(['code' => 'DIVHUMAS', 'name' => 'Divhumas Polri', 'region' => 'Nasional', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $sul = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $admin = $this->user();
        $this->user(['name' => 'Memoardian', 'username' => 'memoardian', 'polda_id' => $sul->id, 'role' => 'operator']);

        $this->actingAs($admin)->get('/personalia')->assertInertia(fn (Assert $p) => $p
            ->where('users.data', fn ($rows) => collect($rows)->pluck('username')->contains('memoardian')));

        $leader = $this->user(['polda_id' => $sul->id, 'role' => 'polda_leader', 'username' => 'kb_x']);
        $this->actingAs($leader)->get('/personalia')->assertInertia(fn (Assert $p) => $p
            ->where('users.data', fn ($rows) => collect($rows)->pluck('username')->doesntContain($admin->username)));
    }
}
