<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\SocialInvestigationCase;
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
            'role'     => 'polda_leader',
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
            ->has('publicTrends')
            ->has('spikeTimeline')
            ->has('wordCloud')
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

    public function test_timeline_uses_filtered_stored_posts_and_drilldown_keeps_the_week(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(12));
        for ($i = 0; $i < 5; $i++) {
            SocialPost::create([
                'polda_id' => $this->polda->id, 'platform' => 'x', 'author_name' => 'Warga', 'author_handle' => '@warga', 'post_url' => 'https://example.com/post',
                'post_id' => 'timeline_'.$i, 'content' => 'Laporan', 'sentiment' => 'negatif',
                'posted_at' => now()->subDay(),
            ]);
        }
        SocialPost::create([
            'polda_id' => $this->polda->id, 'platform' => 'instagram', 'author_name' => 'Warga', 'author_handle' => '@warga', 'post_url' => 'https://example.com/post',
            'post_id' => 'other_platform', 'content' => 'Laporan', 'sentiment' => 'positif', 'posted_at' => now(),
        ]);

        $response = $this->actingAs($this->user)->get('/social-intelligence?platform=x&post_date='.now()->subDay()->format('Y-m-d'));
        $response->assertOk()->assertInertia(fn ($page) => $page
            ->has('posts.data', 5)
            ->where('spikeTimeline.summary.total_7d', 5)
            ->where('spikeTimeline.days.0.total', 0)
            ->where('spikeTimeline.days.5.negatif', 5)
            ->where('spikeTimeline.days.5.is_spike', true)
            ->where('spikeTimeline.days.6.total', 0)
        );
    }

    public function test_empty_timeline_does_not_invent_posts_or_stability(): void
    {
        $this->actingAs($this->user)->get('/social-intelligence')->assertOk()->assertInertia(fn ($page) => $page
            ->where('spikeTimeline.summary.total_7d', 0)
            ->where('spikeTimeline.summary.spike_detected', false)
            ->missing('spikeTimeline.summary.stability_score')
            ->where('spikeTimeline.days.0.total', 0)
        );
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
            'apify_api_token'   => 'apify_test_token_123', // diabaikan: token hanya dikelola admin di Mesin Intelijen AI
            'keywords_tracked'  => 'Polda Sulbar, Mamuju Kritis',
            'sync_frequency'    => 'every_1_hour',
            'auto_sync_enabled' => true,
        ]);

        $response->assertRedirect();

        $setting = SocialSetting::where('polda_id', $this->polda->id)->first();
        $this->assertNotNull($setting);
        $this->assertNotEquals('apify_test_token_123', $setting->apify_api_token, 'Form pengaturan tidak boleh menulis token.');
        $this->assertSame('Polda Sulbar, Mamuju Kritis', $setting->keywords_tracked);
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
        // 1. Missing token (tidak ada token tersimpan)
        SocialSetting::query()->update(['apify_api_token' => null]);
        $responseEmpty = $this->actingAs($this->user)->postJson('/social-intelligence/test-connection');
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

        SocialSetting::updateOrCreate(['polda_id' => $this->polda->id], ['apify_api_token' => 'apify_api_valid_mock_token']);
        $responseValid = $this->actingAs($this->user)->postJson('/social-intelligence/test-connection');
        $responseValid->assertStatus(200);
        $responseValid->assertJson([
            'success' => true,
            'status'  => 'connected',
            'user'    => [
                'username' => 'humas_sulbar',
            ],
        ]);
    }

    public function test_sync_public_trends_endpoint_pulls_google_trends(): void
    {
        \Illuminate\Support\Facades\Http::fake([
            'trends.google.com/*' => \Illuminate\Support\Facades\Http::response(\Tests\Feature\PublicTrendsTest::FEED, 200),
        ]);

        $response = $this->actingAs($this->user)->post('/social-intelligence/sync-trends');
        $response->assertRedirect()->assertSessionHas('success');
        $this->assertCount(2, \Illuminate\Support\Facades\Cache::get(\App\Services\PublicTrendsService::CACHE_KEY)['items']);
    }

    public function test_track_keyword_on_demand_endpoint(): void
    {
        $response = $this->actingAs($this->user)->post('/social-intelligence/track-keyword', [
            'keyword'   => 'Kasus Ponpes Hamalatul Quran',
            'platforms' => ['x', 'tiktok', 'instagram'],
            'polda_id'  => $this->polda->id,
            'max_items' => 10,
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('social_investigation_cases', [
            'polda_id' => $this->polda->id,
            'title'    => 'Kasus Ponpes Hamalatul Quran',
            'status'   => 'active',
        ]);
        $this->assertDatabaseHas('social_posts', [
            'polda_id' => $this->polda->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'apify.keyword_crawl',
        ]);
    }

    public function test_close_and_refresh_investigation_case(): void
    {
        $case = SocialInvestigationCase::create([
            'polda_id'    => $this->polda->id,
            'title'       => 'Penyelidikan Sengketa Lahan',
            'keywords'    => 'sengketa lahan',
            'status'      => 'active',
            'priority'    => 'high',
            'platforms'   => ['x', 'tiktok'],
            'posts_count' => 5,
        ]);

        // Refresh Case
        $refreshRes = $this->actingAs($this->user)->post("/social-intelligence/cases/{$case->id}/refresh");
        $refreshRes->assertRedirect();

        // Close Case
        $closeRes = $this->actingAs($this->user)->post("/social-intelligence/cases/{$case->id}/close");
        $closeRes->assertRedirect();

        $this->assertEquals('archived', $case->fresh()->status);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'social_case.archive',
        ]);
    }

    public function test_analyze_ai_endpoint_returns_json_and_records_audit_log(): void
    {
        // Seed a sample social post for analysis
        SocialPost::create([
            'polda_id'        => $this->polda->id,
            'platform'        => 'x',
            'post_id'         => 'test_post_ai_123',
            'author_name'     => 'Warganet Peduli',
            'author_handle'   => '@warganet_peduli',
            'content'         => 'Apresiasi kesigapan patroli siber Polda Sulbar menjaga situasi kamtibmas tetap kondusif.',
            'sentiment'       => 'positif',
            'post_url'        => 'https://x.com/warganet_peduli/status/123',
            'posted_at'       => now(),
            'virality_score'  => 45,
            'likes_count'     => 120,
            'shares_count'    => 35,
            'comments_count'  => 12,
            'views_count'     => 4500,
            'is_kol'          => false,
        ]);

        $mockGemini = $this->mock(\App\Services\GeminiService::class);
        $mockGemini->shouldReceive('analyzeSocialPosts')
            ->once()
            ->andReturn([
                'success'     => true,
                'model_used'  => 'gemini-3.5-flash-lite',
                'latency_ms'  => 120,
                'posts_count' => 1,
                'data'        => [
                    'executive_brief'    => 'Situasi kondusif di wilayah jajaran.',
                    'top_concern'        => 'Tidak ada isu kritis menonjol.',
                    'sentiment_analysis' => [
                        'positif'           => 80,
                        'netral'            => 15,
                        'negatif'           => 5,
                        'stability_index'   => 85,
                        'stability_label'   => 'Sangat Stabil',
                        'narrative_framing' => 'Apresiasi masyarakat terhadap pelayanan kepolisian.',
                    ],
                    'buzzer_vs_organic' => [
                        'risk_level' => 'rendah',
                        'assessment' => 'Didominasi percakapan warganet organik tanpa pola koordinasi buatan.',
                        'risk_note'  => 'Tidak terdeteksi anomali buzzer serempak.',
                    ],
                    'critical_accounts' => [],
                    'cooling_system'    => [
                        [
                            'action'  => 'Lanjutkan publikasi konten edukatif kamtibmas',
                            'channel' => 'Instagram & TikTok',
                            'timing'  => 'Pukul 12.00 WITA',
                            'pic'     => 'Tim Medsos Bidhumas',
                        ]
                    ],
                    'whatsapp_script' => "*LAPORAN INTELIJEN MEDIA SOSIAL POLRI*\n\nSituasi aman terkendali.",
                ],
            ]);

        $response = $this->actingAs($this->user)->postJson('/social-intelligence/analyze-ai', [
            'polda' => $this->polda->code,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'polda',
            'posts_count',
            'model_used',
            'latency_ms',
            'data' => [
                'executive_brief',
                'top_concern',
                'sentiment_analysis',
                'buzzer_vs_organic',
                'critical_accounts',
                'cooling_system',
                'whatsapp_script',
            ],
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'social_intelligence.ai_analysis',
        ]);
    }
}

