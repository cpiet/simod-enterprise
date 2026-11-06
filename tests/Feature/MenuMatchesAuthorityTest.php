<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Menu & pemilih wilayah hanya menampilkan yang benar-benar boleh diakses pengguna (tidak ada menu yang berujung 403). */
class MenuMatchesAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private function leader(): User
    {
        $sul = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);

        return User::create(['name' => 'Kabid', 'username' => 'kabid_x', 'email' => 'k@polri.go.id', 'password' => 'KataSandiKuat#2026', 'role' => 'polda_leader', 'polda_id' => $sul->id, 'is_active' => true]);
    }

    public function test_menu_hides_tabs_the_server_would_deny_even_when_matrix_row_is_incomplete(): void
    {
        Polda::create(['code' => 'DIVHUMAS', 'name' => 'Divhumas Polri', 'region' => 'Nasional', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $leader = $this->leader();

        // Baris matriks tersimpan tanpa kunci roles_matrix_manage (kunci tak tercatat dulu dianggap "boleh" oleh menu).
        RolePermission::create(['role_key' => 'polda_leader', 'role_name' => 'Kabid Humas Polda', 'polda_id' => $leader->polda_id, 'permissions' => ['dashboard_view' => true, 'security_center' => true]]);

        $this->actingAs($leader)->get('/roles')->assertForbidden();

        $this->actingAs($leader)->get('/security')->assertInertia(fn (Assert $p) => $p
            ->where('navigation.securityTabs', fn ($tabs) => collect($tabs)->pluck('href')->doesntContain('/roles')
                && collect($tabs)->pluck('href')->doesntContain('/settings/menus')));
    }

    public function test_only_authorised_users_get_the_tenant_switcher(): void
    {
        $leader = $this->leader();
        $admin = User::create(['name' => 'Admin', 'username' => 'adm_x', 'email' => 'a@polri.go.id', 'password' => 'KataSandiKuat#2026', 'role' => 'super_admin_mabes', 'is_active' => true]);

        $this->actingAs($leader)->get('/profile')->assertInertia(fn (Assert $p) => $p->where('auth.user.can_switch_tenant', false));
        $this->actingAs($admin)->get('/profile')->assertInertia(fn (Assert $p) => $p->where('auth.user.can_switch_tenant', true));
    }
}
