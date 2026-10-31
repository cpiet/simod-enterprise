<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SocialIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::create([
            'name'       => 'Polda Sulawesi Barat',
            'code'       => 'SULBAR',
            'zone'       => 'WITA',
            'latitude'   => -2.677,
            'longitude'  => 118.889,
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->polres = Polres::create([
            'polda_id'   => $this->polda->id,
            'name'       => 'Polresta Mamuju',
            'code'       => 'POLRESTA_MAMUJU',
            'is_active'  => true,
            'created_at' => now(),
        ]);

        $this->user = User::factory()->create([
            'polda_id' => $this->polda->id,
        ]);
    }

    public function test_social_intelligence_page_loads_successfully(): void
    {
        SocialPost::create([
            'polda_id'       => $this->polda->id,
            'polres_id'      => $this->polres->id,
            'platform'       => 'x',
            'author_name'    => 'Pengamat Hukum Mamuju',
            'author_handle'  => '@pengamat_mamuju',
            'is_kol'         => true,
            'kol_category'   => 'Pengamat Hukum',
            'post_id'        => 'post_101',
            'content'        => 'Apresiasi keterbukaan informasi publik jajaran Polda Sulbar.',
            'post_url'       => 'https://x.com/post_101',
            'likes_count'    => 500,
            'shares_count'   => 120,
            'comments_count' => 30,
            'views_count'    => 10000,
            'sentiment'      => 'positif',
            'virality_score' => 75,
            'is_bookmarked'  => false,
            'posted_at'      => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/social-intelligence?polda=SULBAR');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('SocialIntelligence/Index')
            ->has('posts.data', 1)
            ->has('metrics')
            ->where('metrics.total_posts', 1)
        );
    }

    public function test_filter_by_platform_and_sentiment(): void
    {
        SocialPost::create([
            'polda_id'       => $this->polda->id,
            'platform'       => 'tiktok',
            'author_name'    => 'Warga Mamuju',
            'author_handle'  => '@wargamamuju',
            'post_id'        => 'post_tt_1',
            'content'        => 'Video antrean di Mamuju',
            'post_url'       => 'https://tiktok.com/post_1',
            'sentiment'      => 'negatif',
            'virality_score' => 85,
            'posted_at'      => now(),
        ]);

        SocialPost::create([
            'polda_id'       => $this->polda->id,
            'platform'       => 'instagram',
            'author_name'    => 'Info Polman',
            'author_handle'  => '@infopolman',
            'post_id'        => 'post_ig_1',
            'content'        => 'Foto kegiatan polisi',
            'post_url'       => 'https://instagram.com/post_1',
            'sentiment'      => 'positif',
            'virality_score' => 60,
            'posted_at'      => now(),
        ]);

        // Filter platform=tiktok
        $resTiktok = $this->actingAs($this->user)->get('/social-intelligence?polda=SULBAR&platform=tiktok');
        $resTiktok->assertStatus(200);
        $resTiktok->assertInertia(fn ($page) => $page->has('posts.data', 1));

        // Filter sentiment=positif
        $resPositif = $this->actingAs($this->user)->get('/social-intelligence?polda=SULBAR&sentiment=positif');
        $resPositif->assertStatus(200);
        $resPositif->assertInertia(fn ($page) => $page->has('posts.data', 1));
    }

    public function test_toggle_bookmark_persists(): void
    {
        $post = SocialPost::create([
            'polda_id'       => $this->polda->id,
            'platform'       => 'x',
            'author_name'    => 'Test User',
            'author_handle'  => '@testuser',
            'post_id'        => 'post_bm_1',
            'content'        => 'Testing bookmark feature',
            'post_url'       => 'https://x.com/bm_1',
            'is_bookmarked'  => false,
            'posted_at'      => now(),
        ]);

        $this->actingAs($this->user)->post("/social-intelligence/{$post->id}/bookmark");
        $this->assertTrue($post->fresh()->is_bookmarked);

        // Toggle back
        $this->actingAs($this->user)->post("/social-intelligence/{$post->id}/bookmark");
        $this->assertFalse($post->fresh()->is_bookmarked);
    }

    public function test_update_settings_persists_and_writes_audit_log(): void
    {
        $response = $this->actingAs($this->user)->post('/social-intelligence/settings', [
            'polda_id'          => $this->polda->id,
            'apify_api_token'   => 'apify_test_token_123',
            'keywords_tracked'  => 'Polda Sulbar, Mamuju Kritis',
            'sync_frequency'    => 'every_1_hour',
            'auto_sync_enabled' => true,
        ]);

        $response->assertRedirect();

        $setting = SocialSetting::where('polda_id', $this->polda->id)->first();
        $this->assertNotNull($setting);
        $this->assertEquals('apify_test_token_123', $setting->apify_api_token);
        $this->assertDatabaseHas('audit_logs', [
            'polda_id' => $this->polda->id,
            'action'   => 'social_settings.update',
        ]);
    }

    public function test_sync_triggers_successfully(): void
    {
        $response = $this->actingAs($this->user)->post('/social-intelligence/sync', [
            'polda_id' => $this->polda->id,
        ]);

        $response->assertRedirect();
    }

    public function test_apify_test_connection_endpoint(): void
    {
        // 1. Missing token
        $responseEmpty = $this->actingAs($this->user)->postJson('/social-intelligence/test-connection', [
            'apify_api_token' => '',
        ]);
        $responseEmpty->assertStatus(200);
        $responseEmpty->assertJson(['success' => false, 'status' => 'missing_token']);

        // 2. Simulated call
        \Illuminate\Support\Facades\Http::fake([
            'api.apify.com/v2/users/me' => \Illuminate\Support\Facades\Http::response([
                'data' => [
                    'id'       => 'test_user_id',
                    'username' => 'humas_sulbar',
                    'email'    => 'humas@sulbar.polri.go.id',
                    'plan'     => ['name' => 'Free ($5/mo)'],
                ],
            ], 200),
        ]);

        $responseValid = $this->actingAs($this->user)->postJson('/social-intelligence/test-connection', [
            'apify_api_token' => 'apify_api_valid_mock_token',
        ]);
        $responseValid->assertStatus(200);
        $responseValid->assertJson([
            'success' => true,
            'status'  => 'connected',
            'user'    => [
                'username' => 'humas_sulbar',
            ],
        ]);
    }
}

