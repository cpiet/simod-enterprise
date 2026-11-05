<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Kesiapan produksi: header keamanan, halaman galat resmi, CSRF penuh, logout hanya POST, SSRF pada uji feed, throttle. */
class ProductionHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::create(['name' => 'Admin', 'username' => 'adm_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true]);
    }

    public function test_security_headers_are_present_on_web_responses(): void
    {
        $res = $this->get('/login')->assertOk();

        $res->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $this->assertStringContainsString("frame-ancestors 'self'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('camera=()', $res->headers->get('Permissions-Policy'));
    }

    public function test_official_error_page_is_used_when_debug_is_off(): void
    {
        config(['app.debug' => false]);

        $this->actingAs($this->admin())->get('/halaman-yang-tidak-ada')->assertStatus(404)
            ->assertInertia(fn (Assert $p) => $p->component('Error')->where('status', 404));

        $operator = User::create(['name' => 'Op', 'username' => 'op_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'operator', 'is_active' => true]);
        $this->actingAs($operator)->get('/security')->assertStatus(403)
            ->assertInertia(fn (Assert $p) => $p->component('Error')->where('status', 403));
    }

    public function test_json_requests_keep_json_errors(): void
    {
        config(['app.debug' => false]);

        $this->actingAs($this->admin())->getJson('/tidak-ada')->assertStatus(404)->assertJsonStructure(['message']);
    }

    public function test_logout_is_post_only_and_no_csrf_exemptions_remain(): void
    {
        $this->actingAs($this->admin())->get('/logout')->assertStatus(405);
        $this->assertStringNotContainsString('validateCsrfTokens', file_get_contents(base_path('bootstrap/app.php')));
    }

    public function test_feed_test_refuses_internal_addresses(): void
    {
        $admin = $this->admin();
        foreach (['http://127.0.0.1/rss', 'http://169.254.169.254/latest/meta-data/', 'http://localhost:8080/x', 'file:///etc/passwd'] as $url) {
            $this->actingAs($admin)->postJson('/maintenance/rss-sync/feeds/test', ['url' => $url])->assertStatus(422);
        }
        $this->actingAs($admin)->post('/maintenance/rss-sync/feeds', ['name' => 'Internal', 'url' => 'http://10.0.0.1/rss', 'category' => 'X'])->assertSessionHasErrors('url');
    }

    public function test_ai_vendor_and_model_details_are_only_visible_to_admins(): void
    {
        $polda = \App\Models\Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $leader = User::create(['polda_id' => $polda->id, 'name' => 'Kabid', 'username' => 'kb_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);

        $this->actingAs($leader)->get('/media-intelligence')->assertInertia(fn (Assert $p) => $p
            ->where('aiSetting.gemini_model', null)->where('aiSetting.masked_api_key', null));

        $this->actingAs($this->admin())->post('/tenant/switch', ['code' => 'SULBAR']);
        $this->get('/media-intelligence')->assertInertia(fn (Assert $p) => $p->where('aiSetting.gemini_model', fn ($m) => is_string($m) && $m !== ''));
    }

    public function test_sensitive_ai_endpoints_are_throttled(): void
    {
        $uris = ['settings/ai/test', 'media-intelligence/gemini-test', 'social-intelligence/track-keyword', 'media-intelligence/topics/{id}/analyze-ai'];
        $limited = collect(app('router')->getRoutes()->getRoutes())->filter(fn ($r) => in_array($r->uri(), $uris, true));

        $this->assertCount(4, $limited);
        foreach ($limited as $route) {
            $this->assertTrue(collect($route->gatherMiddleware())->contains(fn ($m) => str_starts_with((string) $m, 'throttle:')), $route->uri() . ' harus dibatasi laju');
        }
    }
}
