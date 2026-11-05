<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Polda;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Pengaturan keamanan harus benar-benar berlaku (bukan sekadar tersimpan), dan halaman tanpa fungsi sudah dihapus.
 */
class AdminSystemEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'operator', ?int $poldaId = null): User
    {
        return User::create([
            'polda_id' => $poldaId,
            'name'     => 'Uji ' . $role,
            'username' => 'adm_' . $role . '_' . uniqid(),
            'email'    => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026',
            'role'     => $role,
            'is_active' => true,
        ]);
    }

    public function test_login_is_locked_after_configured_failed_attempts(): void
    {
        $setting = SecuritySetting::current();
        $setting->update(['max_login_attempts' => 3, 'lockout_minutes' => 10]);
        RateLimiter::clear('login:x');

        $user = $this->makeUser('super_admin_mabes');

        for ($i = 0; $i < 3; $i++) {
            $this->post('/login', ['username' => $user->username, 'password' => 'salah'])->assertSessionHasErrors('username');
        }

        // Percobaan ke-4 ditolak walaupun kata sandi benar.
        $res = $this->post('/login', ['username' => $user->username, 'password' => 'RahasiaUji#2026']);
        $res->assertSessionHasErrors('username');
        $this->assertStringContainsString('Terlalu banyak percobaan', session('errors')->first('username'));
        $this->assertGuest();
    }

    public function test_idle_session_is_ended_after_configured_timeout(): void
    {
        SecuritySetting::current()->update(['session_timeout_minutes' => 5]);
        $user = $this->makeUser('super_admin_mabes');

        $this->actingAs($user)->withSession(['last_activity_at' => time() - 600])->get('/')->assertRedirect(route('login'));
        $this->assertGuest();

        $this->actingAs($user)->withSession(['last_activity_at' => time() - 60])->get('/')->assertOk();
    }

    public function test_profile_password_change_requires_current_password_and_strength(): void
    {
        $user = $this->makeUser('operator');

        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'salah',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])->assertSessionHasErrors('current_password');

        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'RahasiaUji#2026',
            'password' => 'pendek',
            'password_confirmation' => 'pendek',
        ])->assertSessionHasErrors('password');

        $this->actingAs($user)->post('/profile/password', [
            'current_password' => 'RahasiaUji#2026',
            'password' => 'PasswordBaru#2026',
            'password_confirmation' => 'PasswordBaru#2026',
        ])->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('PasswordBaru#2026', $user->fresh()->password));
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.password_changed', 'user_id' => $user->id]);
    }

    public function test_nonfunctional_admin_pages_are_removed(): void
    {
        $admin = $this->makeUser('super_admin_mabes');

        $this->actingAs($admin)->get('/settings/mail')->assertNotFound();
        $this->actingAs($admin)->get('/maintenance')->assertNotFound();

        Polda::create(['code' => 'DIVHUMAS', 'name' => 'Divhumas', 'region' => 'Mabes', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        MenuItem::resetToDefaults();
        $this->assertSame(0, MenuItem::whereIn('route_or_url', ['/settings/mail', '/maintenance'])->count());
        $this->assertSame(8, MenuItem::where('menu_location', 'security_tabs')->count());
    }
}
