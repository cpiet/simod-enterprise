<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\ApifySyncService;
use App\Services\SocialSyndicationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

/**
 * "Perbarui Briefing" di Command Center memanggil sinkron medsos dalam mode hemat (economy).
 */
class CommandCenterRefreshTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;
    private User $leader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->leader = User::create([
            'polda_id' => $this->polda->id, 'name' => 'Pimpinan', 'username' => 'cc_' . uniqid(), 'email' => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true,
        ]);
    }

    private function mockServices(int $apifyCalls): void
    {
        $this->mock(SocialSyndicationService::class, function ($m) {
            $m->shouldReceive('syncRadarForPolda')->once()->andReturn(['success' => true, 'new_posts' => 0, 'message' => 'Tidak ada postingan baru.']);
        });
        $this->mock(ApifySyncService::class, function ($m) use ($apifyCalls) {
            $m->shouldReceive('syncPolda')->times($apifyCalls)->andReturn(['success' => true, 'new_posts' => 3, 'message' => 'Apify OK']);
        });
    }

    private function seedPost(): void
    {
        SocialPost::create([
            'polda_id' => $this->polda->id, 'platform' => 'x', 'author_name' => 'A', 'author_handle' => 'a',
            'content' => 'uji', 'post_url' => 'https://x.com/1', 'sentiment' => 'netral', 'posted_at' => now(),
        ]);
    }

    public function test_economy_mode_skips_paid_apify_when_social_data_is_fresh(): void
    {
        $this->seedPost(); // data baru (< 60 menit)
        $this->mockServices(0);

        $this->actingAs($this->leader)->postJson('/social-intelligence/sync', ['economy' => 1])
            ->assertOk()->assertJsonPath('success', true)->assertJsonPath('apify_used', false);
    }

    public function test_economy_mode_uses_apify_when_social_data_is_stale(): void
    {
        $this->seedPost();
        SocialPost::query()->update(['created_at' => now()->subHours(3)]);
        $this->mockServices(1);

        $this->actingAs($this->leader)->postJson('/social-intelligence/sync', ['economy' => 1])
            ->assertOk()->assertJsonPath('new_posts', 3)->assertJsonPath('apify_used', true);
    }

    public function test_normal_sync_from_social_page_still_uses_apify_when_syndication_empty(): void
    {
        $this->seedPost();
        $this->mockServices(1);

        $this->actingAs($this->leader)->postJson('/social-intelligence/sync')->assertOk()->assertJsonPath('apify_used', true);
    }

    public function test_sync_requires_social_sync_permission(): void
    {
        $viewer = User::create([
            'polda_id' => $this->polda->id, 'name' => 'Lihat', 'username' => 'cc_' . uniqid(), 'email' => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => 'leader_viewer_none', 'is_active' => true,
        ]);

        $this->actingAs($viewer)->postJson('/social-intelligence/sync', ['economy' => 1])->assertForbidden();
    }

    public function test_dashboard_exposes_report_timestamps_for_refresh_status(): void
    {
        $this->actingAs($this->leader)->get('/')->assertOk()
            ->assertInertia(fn ($p) => $p->has('latestMediaReport')->has('latestSocialReport'));
    }
}
