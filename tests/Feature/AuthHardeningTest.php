<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengujian penguatan autentikasi: gerbang login, kredensial wajib valid,
 * pembatasan simulasi peran, dan token API ingest.
 */
class AuthHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $role = 'operator', ?int $poldaId = null, bool $active = true): User
    {
        return User::create([
            'polda_id'  => $poldaId,
            'name'      => 'Petugas Uji ' . $role,
            'username'  => 'uji_' . $role . '_' . uniqid(),
            'email'     => uniqid() . '@polri.go.id',
            'password'  => 'RahasiaUji#2026',
            'role'      => $role,
            'is_active' => $active,
        ]);
    }

    public function test_protected_routes_redirect_guests_to_login(): void
    {
        foreach (['/', '/media-intelligence', '/issues', '/personalia', '/security', '/roles', '/settings/ai', '/database/backup'] as $uri) {
            $this->get($uri)->assertRedirect('/login');
        }

        $this->post('/tenant/switch', ['code' => 'ALL'])->assertRedirect('/login');
    }

    public function test_login_with_valid_credentials_succeeds_and_writes_audit_log(): void
    {
        $user = $this->makeUser('super_admin_mabes');

        $this->post('/login', ['username' => $user->username, 'password' => 'RahasiaUji#2026'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login', 'user_id' => $user->id]);
    }

    public function test_login_rejects_wrong_empty_or_missing_password(): void
    {
        $user = $this->makeUser('super_admin_mabes');

        $this->post('/login', ['username' => $user->username, 'password' => 'salah'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        $this->post('/login', ['username' => $user->username, 'password' => ''])
            ->assertSessionHasErrors('password');
        $this->assertGuest();

        $this->post('/login', ['username' => '', 'password' => ''])
            ->assertSessionHasErrors(['username', 'password']);
        $this->assertGuest();

        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.login_failed', 'user_id' => $user->id]);
    }

    public function test_login_rejects_unknown_user_and_legacy_demo_passwords(): void
    {
        $this->post('/login', ['username' => 'tidak_ada', 'password' => 'DivhumasPolri#2026'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();

        // Sandi demo lama tidak lagi berlaku sebagai bypass untuk akun yang kata sandinya berbeda.
        $user = $this->makeUser('operator');
        $this->post('/login', ['username' => $user->username, 'password' => 'DivhumasPolri#2026'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_login_rejects_inactive_account(): void
    {
        $user = $this->makeUser('operator', null, false);

        $this->post('/login', ['username' => $user->username, 'password' => 'RahasiaUji#2026'])
            ->assertSessionHasErrors('username');
        $this->assertGuest();
    }

    public function test_simulated_role_query_is_ignored_for_non_super_admin(): void
    {
        $polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $operator = $this->makeUser('operator', $polda->id);

        $this->actingAs($operator)->get('/?role=kadiv_humas')
            ->assertInertia(fn ($page) => $page->where('auth.role', 'operator'));
    }

    public function test_simulated_role_query_is_honored_for_super_admin_only_for_display(): void
    {
        $admin = $this->makeUser('super_admin_mabes');

        $this->actingAs($admin)->get('/?role=analis')
            ->assertInertia(fn ($page) => $page->where('auth.role', 'analis'));
    }

    public function test_ingest_api_is_fail_closed_without_configured_token(): void
    {
        config(['simod.ingest_token' => '']);

        $this->postJson('/api/v1/ingest/article', [])->assertStatus(503);
    }

    public function test_ingest_api_requires_valid_token(): void
    {
        config(['simod.ingest_token' => 'token-rahasia-uji']);
        Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);

        $payload = [
            'polda_code'  => 'SULBAR',
            'title'       => 'Berita Uji Ingest',
            'source_name' => 'UJI',
            'url'         => 'https://contoh.test/berita-uji',
        ];

        $this->postJson('/api/v1/ingest/article', $payload)->assertStatus(401);
        $this->postJson('/api/v1/ingest/article', $payload, ['Authorization' => 'Bearer salah'])->assertStatus(401);
        $this->getJson('/api/v1/poldas')->assertStatus(401);

        $this->postJson('/api/v1/ingest/article', $payload, ['Authorization' => 'Bearer token-rahasia-uji'])
            ->assertSuccessful();
        $this->postJson('/api/v1/ingest/article', $payload, ['X-Ingest-Token' => 'token-rahasia-uji'])
            ->assertSuccessful();

        // Health check tetap publik.
        $this->getJson('/api/v1/health')->assertOk();
    }
}
