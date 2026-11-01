<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\MenuItem;
use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DynamicMenuAndSecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    protected Polda $divhumas;
    protected Polda $sulbar;
    protected User $superAdmin;
    protected User $regionalOfficer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->divhumas = Polda::firstOrCreate(
            ['code' => 'DIVHUMAS'],
            ['name' => 'Divhumas Polri', 'region' => 'Mabes Polri', 'timezone' => 'Asia/Jakarta', 'is_active' => true]
        );

        $this->sulbar = Polda::firstOrCreate(
            ['code' => 'SULBAR'],
            ['name' => 'Polda Sulawesi Barat', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]
        );

        $this->superAdmin = User::create([
            'polda_id'     => $this->divhumas->id,
            'name'         => 'Irjen Pol. Johnny Eddizon Isir',
            'username'     => 'kadivhumas',
            'email'        => 'kadivhumas@polri.go.id',
            'password'     => bcrypt('DivhumasPolri#2026'),
            'role'         => 'kadiv_humas',
            'badge_number' => '960001',
            'is_active'    => true,
        ]);

        $this->regionalOfficer = User::create([
            'polda_id'     => $this->sulbar->id,
            'name'         => 'Bripka Operator Sulbar',
            'username'     => 'op_sulbar',
            'email'        => 'op.sulbar@polri.go.id',
            'password'     => bcrypt('SulbarHumas#2026'),
            'role'         => 'operator',
            'badge_number' => '890122',
            'is_active'    => true,
        ]);
    }

    public function test_dynamic_menu_management_crud_and_ordering(): void
    {
        $this->actingAs($this->superAdmin);

        // 1. Load menu management page (auto-seeds default 4 pillars & 10 tabs)
        $res = $this->get('/settings/menus');
        $res->assertOk();
        $this->assertGreaterThanOrEqual(25, MenuItem::count());

        $pilar1 = MenuItem::whereNull('parent_id')->where('menu_location', 'sidebar')->orderBy('order_index')->first();
        $this->assertNotNull($pilar1);

        // 2. Create custom menu item under Pillar 1
        $storeRes = $this->post('/settings/menus', [
            'title'               => 'Pantauan Siber Khusus',
            'description'         => 'Sub-modul intelijen siber kustom',
            'route_or_url'        => '/cyber-radar',
            'icon'                => 'Globe',
            'parent_id'           => $pilar1->id,
            'menu_location'       => 'sidebar',
            'order_index'         => 9,
            'permission_required' => 'media_intelligence_view',
            'badge_text'          => 'CUSTOM',
            'is_active'           => true,
        ]);
        $storeRes->assertRedirect();

        $customMenu = MenuItem::where('title', 'Pantauan Siber Khusus')->first();
        $this->assertNotNull($customMenu);
        $this->assertEquals('/cyber-radar', $customMenu->route_or_url);

        // 3. Update custom menu item
        $updateRes = $this->put("/settings/menus/{$customMenu->id}", [
            'title'               => 'Pantauan Siber Terpadu v2',
            'description'         => 'Diperbarui oleh Admin',
            'route_or_url'        => '/cyber-radar-v2',
            'icon'                => 'Shield',
            'parent_id'           => $pilar1->id,
            'menu_location'       => 'sidebar',
            'order_index'         => 2,
            'permission_required' => 'security_center',
            'badge_text'          => 'V2',
            'is_active'           => true,
        ]);
        $updateRes->assertRedirect();
        $this->assertEquals('Pantauan Siber Terpadu v2', $customMenu->fresh()->title);

        // 4. Toggle active status
        $this->post("/settings/menus/{$customMenu->id}/toggle")->assertRedirect();
        $this->assertFalse($customMenu->fresh()->is_active);

        $this->post("/settings/menus/{$customMenu->id}/toggle")->assertRedirect();
        $this->assertTrue($customMenu->fresh()->is_active);

        // 5. Move ordering up
        $this->post("/settings/menus/{$customMenu->id}/move", ['direction' => 'up'])->assertRedirect();

        // 6. Delete custom menu item
        $this->delete("/settings/menus/{$customMenu->id}")->assertRedirect();
        $this->assertNull(MenuItem::find($customMenu->id));

        // 7. Reset defaults
        $this->post('/settings/menus/reset')->assertRedirect();
        $this->assertGreaterThanOrEqual(25, MenuItem::count());
    }

    public function test_dynamic_menu_rbac_filtering_hides_unauthorized_items(): void
    {
        MenuItem::ensureDefaultMenusSeeded();

        $operatorPerms = RolePermission::getPermissionsForRole('operator', $this->sulbar->id);
        $navForOperator = MenuItem::getNavigationForUser('operator', $operatorPerms);

        // Operator does not have 'security_center' or 'personalia_manage' by default
        $allOperatorHrefs = collect($navForOperator['sidebarGroups'])
            ->pluck('items')
            ->flatten(1)
            ->pluck('href')
            ->toArray();

        $this->assertContains('/', $allOperatorHrefs);
        $this->assertContains('/profile', $allOperatorHrefs);
        $this->assertNotContains('/personalia', $allOperatorHrefs);
        $this->assertNotContains('/security', $allOperatorHrefs);
    }

    public function test_guest_middleware_redirects_authenticated_user_away_from_login(): void
    {
        // Unauthenticated visitor can view /login
        $guestRes = $this->get('/login');
        $guestRes->assertOk();

        // Authenticated user visiting /login is redirected to /
        $authRes = $this->actingAs($this->superAdmin)->get('/login');
        $authRes->assertRedirect('/');
    }

    public function test_prevent_back_history_headers_and_session_invalidation_on_logout(): void
    {
        $this->actingAs($this->superAdmin);

        $dashboardRes = $this->get('/');
        $dashboardRes->assertOk();

        // Verify PreventBackHistory headers
        $cacheControl = $dashboardRes->headers->get('Cache-Control');
        $this->assertStringContainsString('no-cache', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('must-revalidate', $cacheControl);
        $this->assertEquals('no-cache', $dashboardRes->headers->get('Pragma'));
        $this->assertEquals('Sun, 02 Jan 1990 00:00:00 GMT', $dashboardRes->headers->get('Expires'));

        // Execute POST /logout
        $logoutRes = $this->post('/logout');
        $logoutRes->assertRedirect('/login');
        $this->assertGuest();

        // Attempting to visit / immediately after logout in the same session redirects to /login
        $afterLogoutRes = $this->get('/');
        $afterLogoutRes->assertRedirect('/login');
    }

    public function test_multi_tenant_idor_protection_prevents_cross_polda_issue_tampering(): void
    {
        // Create an issue belonging to Divhumas / another Polda
        $otherPoldaIssue = Issue::create([
            'polda_id'    => $this->divhumas->id,
            'report_code' => 'SIM-2026-99999',
            'title'       => 'Isu Rahasia Tingkat Mabes',
            'slug'        => 'isu-rahasia-mabes-999',
            'summary'     => 'Detail isu sensitif khusus Mabes Polri.',
            'category'    => 'Kamtibmas',
            'priority'    => 'high',
            'sentiment'   => 'netral',
            'status'      => 'monitoring',
        ]);

        // Regional officer from Sulbar attempts to view or verify Divhumas issue by ID
        $this->actingAs($this->regionalOfficer);

        $showRes = $this->get("/issues/{$otherPoldaIssue->id}");
        $showRes->assertStatus(403);

        $verifyRes = $this->post("/issues/{$otherPoldaIssue->id}/verify");
        $verifyRes->assertStatus(403);
    }
}

