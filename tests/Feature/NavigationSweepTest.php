<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\SocialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Penyapu menyeluruh: untuk setiap peran, SETIAP menu sidebar/tab yang tampil harus dapat dibuka tanpa 403/500,
 * dan token Apify tidak boleh bocor ke browser maupun terhapus oleh form pengaturan.
 */
class NavigationSweepTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role, ?Polda $polda): User
    {
        return User::create(['name' => ucfirst($role), 'username' => $role.'_sweep', 'email' => $role.'@polri.go.id', 'password' => 'KataSandiKuat#2026', 'role' => $role, 'polda_id' => $role === 'super_admin_mabes' || $role === 'kadiv_humas' ? null : $polda?->id, 'is_active' => true]);
    }

    private function poldas(): array
    {
        $div = Polda::create(['code' => 'DIVHUMAS', 'name' => 'Divhumas Polri', 'region' => 'Nasional', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $sul = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);

        return [$div, $sul];
    }

    /** @return array<int,string> */
    private function visibleHrefs(User $u): array
    {
        $nav = $this->actingAs($u)->get('/profile')->viewData('page')['props']['navigation'] ?? [];

        $hrefs = [];
        foreach ($nav['sidebarGroups'] ?? [] as $g) {
            foreach ($g['items'] ?? [] as $i) {
                $hrefs[] = $i['href'];
                foreach ($i['children'] ?? [] as $c) {
                    $hrefs[] = $c['href'];
                }
            }
        }
        foreach ($nav['securityTabs'] ?? [] as $t) {
            $hrefs[] = $t['href'];
        }

        return array_values(array_unique(array_filter($hrefs, fn ($h) => is_string($h) && str_starts_with($h, '/'))));
    }

    public function test_every_visible_menu_item_opens_for_every_role(): void
    {
        [, $sul] = $this->poldas();

        foreach (['super_admin_mabes', 'kadiv_humas', 'polda_leader', 'verifikator', 'analis', 'operator'] as $role) {
            $u = $this->user($role, $sul);
            $hrefs = $this->visibleHrefs($u);
            $this->assertNotEmpty($hrefs, "Menu kosong untuk $role");

            foreach ($hrefs as $href) {
                $status = $this->actingAs($u)->get($href)->getStatusCode();
                $this->assertContains($status, [200, 302], "Peran $role: menu $href menghasilkan HTTP $status");
            }
        }
    }

    public function test_apify_token_is_never_sent_to_browser_and_settings_form_does_not_wipe_it(): void
    {
        [, $sul] = $this->poldas();
        SocialSetting::create(['polda_id' => $sul->id, 'apify_api_token' => 'apify_api_RAHASIA1234567890', 'keywords_tracked' => 'a', 'accounts_tracked' => 'b', 'sync_frequency' => 'daily', 'auto_sync_enabled' => false]);
        $leader = $this->user('polda_leader', $sul);

        $res = $this->actingAs($leader)->get('/social-intelligence');
        $res->assertOk();
        $this->assertStringNotContainsString('RAHASIA1234567890', $res->getContent(), 'Token Apify bocor ke halaman.');
        $res->assertInertia(fn (Assert $p) => $p->where('settings.apify_token_set', true)->missing('settings.apify_api_token')->where('instagramReady', true));

        $this->actingAs($leader)->post('/social-intelligence/settings', ['polda_id' => $sul->id, 'keywords_tracked' => 'baru', 'accounts_tracked' => 'b', 'sync_frequency' => 'daily', 'auto_sync_enabled' => false])->assertSessionHasNoErrors();
        $this->assertSame('apify_api_RAHASIA1234567890', SocialSetting::where('polda_id', $sul->id)->value('apify_api_token'));
    }

    public function test_instagram_not_ready_without_token_and_failure_reason_is_kept(): void
    {
        [, $sul] = $this->poldas();
        $leader = $this->user('polda_leader', $sul);

        $this->actingAs($leader)->get('/social-intelligence')->assertInertia(fn (Assert $p) => $p->where('instagramReady', false));

        $res = $this->actingAs($leader)->postJson('/social-intelligence/instagram-accounts', ['username' => '@mohmahfudmd']);
        $res->assertStatus(422);
        $this->assertStringContainsString('Token Apify belum diatur', $res->json('message'));
        $this->assertStringContainsString('Token Apify', (string) \App\Models\MonitoredInstagramAccount::first()->last_error);
    }

    public function test_every_parameterless_get_route_renders_without_server_error(): void
    {
        [, $sul] = $this->poldas();
        \Illuminate\Support\Facades\Http::fake();
        $admin = $this->user('super_admin_mabes', $sul);

        $checked = 0;
        foreach (app('router')->getRoutes() as $route) {
            $uri = $route->uri();
            if (! in_array('GET', $route->methods(), true) || str_contains($uri, '{') || str_starts_with($uri, 'api/') || in_array($uri, ['logout', 'up', 'sanctum/csrf-cookie', 'storage/{path}'], true)) {
                continue;
            }
            $status = $this->actingAs($admin)->get('/'.ltrim($uri, '/'))->getStatusCode();
            $this->assertLessThan(500, $status, "GET /$uri menghasilkan HTTP $status");
            $checked++;
        }
        $this->assertGreaterThan(20, $checked);
    }
}
