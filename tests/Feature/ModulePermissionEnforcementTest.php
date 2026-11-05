<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Memastikan matriks RBAC (/roles) ditegakkan di sisi server oleh middleware `module:*`,
 * bukan hanya menyembunyikan menu di frontend.
 */
class ModulePermissionEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::create([
            'code' => 'SULBAR', 'name' => 'Polda Sulawesi Barat', 'region' => 'Sulawesi Barat',
            'timezone' => 'Asia/Makassar', 'is_active' => true,
        ]);
    }

    private function user(string $role): User
    {
        return User::create([
            'polda_id'  => $this->polda->id,
            'name'      => 'Uji ' . $role,
            'username'  => 'rbac_' . $role . '_' . uniqid(),
            'email'     => uniqid() . '@polri.go.id',
            'password'  => 'RahasiaUji#2026',
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    public function test_operator_is_denied_admin_and_management_modules(): void
    {
        $operator = $this->user('operator');

        foreach (['/personalia', '/security', '/roles', '/settings/ai', '/database/backup', '/maintenance/rss-sync', '/settings/menus', '/audit-logs', '/reports', '/publications'] as $uri) {
            $this->actingAs($operator)->get($uri)->assertForbidden();
        }

        $this->actingAs($operator)->post('/tasks', ['title' => 'x'])->assertForbidden();
        $this->actingAs($operator)->post('/issues/1/verify')->assertForbidden();
        $this->actingAs($operator)->postJson('/media-intelligence/analyze-gemini')->assertForbidden();
        $this->actingAs($operator)->postJson('/social-intelligence/sync')->assertForbidden();
    }

    public function test_operator_can_open_modules_granted_by_default_matrix(): void
    {
        $operator = $this->user('operator');

        $this->actingAs($operator)->get('/')->assertOk();
        $this->actingAs($operator)->get('/media-intelligence')->assertOk();
        $this->actingAs($operator)->get('/issues')->assertOk();
        $this->actingAs($operator)->get('/issues/create')->assertOk();
        $this->actingAs($operator)->get('/profile')->assertOk();
    }

    public function test_analis_can_run_media_ai_but_cannot_sync_social_or_verify_issues(): void
    {
        $analis = $this->user('analis');

        $this->actingAs($analis)->postJson('/social-intelligence/sync')->assertForbidden();
        $this->actingAs($analis)->post('/issues/1/verify')->assertForbidden();
        $this->actingAs($analis)->get('/reports')->assertOk();
    }

    public function test_super_admin_and_kadiv_bypass_module_checks(): void
    {
        foreach (['super_admin_mabes', 'kadiv_humas', 'superadmin'] as $role) {
            $admin = User::create([
                'name' => 'Admin ' . $role, 'username' => 'adm_' . $role, 'email' => $role . '@polri.go.id',
                'password' => 'RahasiaUji#2026', 'role' => $role, 'is_active' => true,
            ]);

            $this->actingAs($admin)->get('/roles')->assertOk();
            $this->actingAs($admin)->get('/security')->assertOk();
        }
    }

    public function test_revoking_a_module_in_custom_matrix_blocks_the_route_immediately(): void
    {
        $leader = $this->user('polda_leader');

        $this->actingAs($leader)->get('/personalia')->assertOk();

        $permissions = array_fill_keys(array_column(RolePermission::defaultModules(), 'key'), true);
        $permissions['personalia_manage'] = false;

        RolePermission::updateOrCreate(
            ['polda_id' => $this->polda->id, 'role_key' => 'polda_leader'],
            ['role_name' => 'Pimpinan Polda (Kabid Humas)', 'description' => 'Uji pencabutan modul', 'permissions' => $permissions]
        );

        $this->actingAs($leader)->get('/personalia')->assertForbidden();
        $this->actingAs($leader)->get('/issues')->assertOk();
    }

    public function test_default_roles_only_reference_existing_module_keys(): void
    {
        $valid = array_column(RolePermission::defaultModules(), 'key');

        foreach (RolePermission::defaultRoles() as $role) {
            foreach ($role['granted_keys'] ?? [] as $key) {
                $this->assertContains($key, $valid, "Kunci '{$key}' pada peran {$role['role_key']} tidak ada di defaultModules().");
            }
        }
    }
}
