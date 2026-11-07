<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MonitoredSocialAccount;
use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pencarian Cepat (Ctrl+K): hasil lintas modul, terkunci tenant, mengikuti hak akses peran.
 */
class QuickSearchTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $jatim;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sulbar = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'zone' => 'WITA', 'is_active' => true]);
        $this->jatim = Polda::create(['code' => 'JATIM', 'name' => 'Polda Jatim', 'zone' => 'WIB', 'is_active' => true]);
        foreach ([$this->sulbar, $this->jatim] as $p) {
            MediaArticle::create(['polda_id' => $p->id, 'title' => "Banjir bandang {$p->code}", 'summary' => '-', 'source_name' => 'Portal', 'category' => 'Umum', 'url' => "https://b.test/{$p->code}", 'pub_date' => now(), 'sentiment' => 'netral']);
            Issue::create(['polda_id' => $p->id, 'title' => "Isu banjir {$p->code}", 'summary' => 'Laporan banjir', 'category' => 'Umum', 'priority' => 'high', 'status' => 'monitoring']);
            SocialPost::create(['polda_id' => $p->id, 'platform' => 'x', 'author_name' => 'W', 'author_handle' => '@w', 'post_id' => "p{$p->code}", 'post_url' => "https://x.com/w/status/{$p->id}", 'content' => "Warga keluhkan banjir {$p->code}", 'sentiment' => 'negatif', 'posted_at' => now()]);
        }
        MonitoredSocialAccount::create(['polda_id' => $this->sulbar->id, 'platform' => 'x', 'username' => 'BanjirWatch', 'display_name' => 'Pantau Banjir']);
    }

    public function test_results_are_grouped_and_locked_to_the_users_tenant(): void
    {
        $leader = User::factory()->create(['role' => 'polda_leader', 'polda_id' => $this->sulbar->id]);
        $res = $this->actingAs($leader)->getJson('/quick-search?q=banjir')->assertOk();

        $groups = collect($res->json('groups'))->keyBy('key');
        $this->assertSame(['Banjir bandang SULBAR'], collect($groups['media']['items'])->pluck('title')->all());
        $this->assertSame(['Isu banjir SULBAR'], collect($groups['issues']['items'])->pluck('title')->all());
        $this->assertStringStartsWith('/issues/', $groups['issues']['items'][0]['url']);
        $this->assertSame('Pantau Banjir', $groups['accounts']['items'][0]['title']);
        $this->assertStringNotContainsString('JATIM', json_encode($res->json()), 'data Polda lain tidak bocor');
    }

    public function test_groups_follow_role_permissions(): void
    {
        RolePermission::create(['polda_id' => $this->sulbar->id, 'role_key' => 'operator', 'role_name' => 'Operator', 'permissions' => ['dashboard_view' => true, 'issues_view' => true,
            'media_intelligence_view' => false, 'social_intelligence_view' => false, 'tasks_execute' => false, 'tasks_disposition' => false]]);
        $op = User::factory()->create(['role' => 'operator', 'polda_id' => $this->sulbar->id]);

        $keys = collect($this->actingAs($op)->getJson('/quick-search?q=banjir')->assertOk()->json('groups'))->pluck('key')->all();
        $this->assertSame(['issues'], $keys, 'tanpa izin Radar, berita & medsos tidak ikut dicari');
    }

    public function test_short_query_returns_nothing_and_guest_is_rejected(): void
    {
        $leader = User::factory()->create(['role' => 'polda_leader', 'polda_id' => $this->sulbar->id]);
        $this->actingAs($leader)->getJson('/quick-search?q=b')->assertOk()->assertJson(['groups' => []]);
        auth()->logout();
        $this->getJson('/quick-search?q=banjir')->assertStatus(401);
    }
}
