<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EnterpriseDatabaseSeeder::class);
    }

    public function test_mabes_user_can_switch_tenant_via_post(): void
    {
        $mabesUser = User::where('role', 'kadiv_humas')->first()
            ?? User::where('role', 'super_admin_mabes')->first()
            ?? User::factory()->create([
                'role'     => 'super_admin_mabes',
                'polda_id' => null,
            ]);

        $this->actingAs($mabesUser);

        $response = $this->post('/tenant/switch', [
            'code' => 'SULBAR',
        ]);

        $response->assertStatus(302);
        $this->assertEquals('SULBAR', session('active_polda_code'));

        // Visit without query parameter; session should persist
        $dashResponse = $this->get('/');
        $dashResponse->assertStatus(200);
        $this->assertEquals('SULBAR', session('active_polda_code'));
    }

    public function test_regional_user_is_locked_to_assigned_polda_and_cannot_switch(): void
    {
        $sulbar = Polda::where('code', 'SULBAR')->first();

        $regionalUser = User::where('role', 'polda_leader')->where('polda_id', $sulbar->id)->first()
            ?? User::where('polda_id', $sulbar->id)->first()
            ?? User::factory()->create([
                'role'     => 'polda_leader',
                'polda_id' => $sulbar->id,
            ]);

        $this->actingAs($regionalUser);

        // Attempting to switch to ALL/DIVHUMAS must be rejected (403 Forbidden)
        $response = $this->postJson('/tenant/switch', [
            'code' => 'ALL',
        ]);

        $response->assertStatus(403);

        // Even with malicious query parameter in GET, session remains locked to user's Polda
        $getRes = $this->get('/media-intelligence?polda=ALL');
        $getRes->assertStatus(200);
        $this->assertEquals('SULBAR', session('active_polda_code'));
    }

    public function test_canonical_clean_urls_render_successfully(): void
    {
        $user = User::first();
        $this->actingAs($user);

        $pages = [
            '/',
            '/media-intelligence',
            '/social-intelligence',
            '/issues',
            '/tasks',
            '/publications',
            '/security',
            '/settings/ai',
        ];

        foreach ($pages as $url) {
            $res = $this->get($url);
            $res->assertStatus(200);
        }
    }
}
