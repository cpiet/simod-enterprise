<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Matriks hak akses hanya menerima peran & modul yang dikenal, dan tenant diambil dari sesi (bukan query). */
class RolesMatrixValidationTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sulbar = Polda::create(['code' => 'SULBAR', 'name' => 'Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->gorontalo = Polda::create(['code' => 'GORONTALO', 'name' => 'Gorontalo', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->admin = User::create(['polda_id' => $this->sulbar->id, 'name' => 'Admin', 'username' => 'a_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);
        RolePermission::create(['polda_id' => $this->sulbar->id, 'role_key' => 'polda_leader', 'role_name' => 'Pimpinan', 'permissions' => ['dashboard_view' => true, 'roles_matrix_manage' => true]]);
    }

    public function test_unknown_roles_are_rejected_and_unknown_modules_are_dropped(): void
    {
        $this->actingAs($this->admin)->post('/roles', ['matrix' => [['role_key' => 'hacker', 'permissions' => ['dashboard_view' => true]]]])->assertStatus(422);
        $this->assertSame(0, RolePermission::where('role_key', 'hacker')->count());

        $this->actingAs($this->admin)->post('/roles', ['matrix' => [[
            'role_key' => 'operator', 'permissions' => ['dashboard_view' => '1', 'issues_view' => 'false', 'modul_palsu' => true],
        ]]])->assertRedirect();

        $perms = RolePermission::where('role_key', 'operator')->where('polda_id', $this->sulbar->id)->value('permissions');
        $this->assertSame(['dashboard_view' => true, 'issues_view' => false], $perms);
    }

    public function test_target_polda_comes_from_session_not_query_string(): void
    {
        $this->actingAs($this->admin)->post('/roles?polda=GORONTALO', ['matrix' => [['role_key' => 'analis', 'permissions' => ['dashboard_view' => true]]], 'polda_id' => $this->gorontalo->id])->assertRedirect();

        $this->assertSame(1, RolePermission::where('role_key', 'analis')->where('polda_id', $this->sulbar->id)->count());
        $this->assertSame(0, RolePermission::where('role_key', 'analis')->where('polda_id', $this->gorontalo->id)->count());
    }
}
