<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\RssSyncLog;
use App\Models\RssSyncSetting;
use App\Models\User;
use App\Services\RssFeedService;
use App\Services\RssSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class RssSyncTest extends TestCase
{
    use RefreshDatabase;

    protected Polda $polda;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::create([
            'name'       => 'Polda Sulawesi Barat',
            'code'       => 'SULBAR',
            'alias'      => 'Sulbar',
            'latitude'   => -2.6775,
            'longitude'  => 118.8894,
            'is_active'  => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->user = User::factory()->create([
            'polda_id' => $this->polda->id,
            'role'     => 'superadmin',
        ]);
    }

    public function test_rss_sync_page_loads_successfully()
    {
        $response = $this->actingAs($this->user)->get('/maintenance/rss-sync?polda=SULBAR');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/RssSync')
            ->has('setting')
            ->has('telemetry')
            ->has('recentLogs')
        );
    }

    public function test_update_rss_sync_settings_persists_and_writes_audit_log()
    {
        $payload = [
            'polda_id'                        => $this->polda->id,
            'sync_mode'                       => 'scheduled_times',
            'interval_minutes'                => 180,
            'scheduled_times'                 => ['06:00', '12:00', '18:00', '21:00'],
            'max_articles_per_feed'           => 30,
            'max_retained_articles_per_polda' => 1200,
            'auto_prune_days'                 => 60,
            'timeout_seconds'                 => 10,
            'is_active'                       => true,
            'apply_to_all_poldas'             => true,
        ];

        $response = $this->actingAs($this->user)->post('/maintenance/rss-sync', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = RssSyncSetting::where('polda_id', $this->polda->id)->first();
        $this->assertNotNull($setting);
        $this->assertEquals('scheduled_times', $setting->sync_mode);
        $this->assertEquals(1200, $setting->max_retained_articles_per_polda);
        $this->assertContains('21:00', $setting->scheduled_times);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_RSS_SYNC_SETTINGS',
        ]);
    }

    public function test_mutex_lock_prevents_overlapping_sync()
    {
        // Simulasi lock aktif
        $lock = Cache::lock('rss_sync_engine_lock', 60);
        $lock->get();

        $syncService = new RssSyncService();
        $result = $syncService->syncAll('automatic', false);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['locked']);

        $this->assertDatabaseHas('rss_sync_logs', [
            'status' => 'skipped_locked',
        ]);

        $lock->release();
    }

    public function test_auto_capping_prunes_only_old_unbookmarked_articles()
    {
        // Buat 10 artikel unbookmarked dan 2 artikel bookmarked
        for ($i = 1; $i <= 10; $i++) {
            MediaArticle::create([
                'polda_id'      => $this->polda->id,
                'title'         => "Berita Reguler #{$i}",
                'url'           => "https://tribratanews.polri.go.id/news-{$i}",
                'source_name'   => 'Tribratanews',
                'category'      => 'Hukum & Kriminal',
                'pub_date'      => now()->subDays(20 - $i),
                'is_bookmarked' => false,
            ]);
        }

        $bookmarked = MediaArticle::create([
            'polda_id'      => $this->polda->id,
            'title'         => 'Berita Penting Dijadikan Bukti',
            'url'           => 'https://tribratanews.polri.go.id/news-bookmarked',
            'source_name'   => 'Tribratanews',
            'category'      => 'Hukum & Kriminal',
            'pub_date'      => now()->subDays(100),
            'is_bookmarked' => true,
        ]);

        // Mock RssFeedService to return empty so sync only exercises autoCapArticles
        $mockFeedService = $this->createMock(RssFeedService::class);
        $mockFeedService->method('fetchAggregatedFeeds')->willReturn([]);

        $syncService = new RssSyncService($mockFeedService);

        // Set max retained articles to 5
        $setting = RssSyncSetting::forPolda($this->polda->id);
        $setting->update(['max_retained_articles_per_polda' => 5]);

        // Create active media feed so internal sync activates
        MediaFeed::create([
            'polda_id'  => $this->polda->id,
            'name'      => 'Tribratanews Sulbar',
            'url'       => 'https://tribratanews.sulbar.polri.go.id/feed',
            'is_active' => true,
        ]);

        $res = $syncService->syncPolda($this->polda, 'manual_ui', true);

        $this->assertTrue($res['success']);
        $this->assertEquals(5, $res['pruned_count']); // 10 - 5 = 5 dipangkas

        // Pastikan berita bookmark TIDAK pernah dihapus
        $this->assertDatabaseHas('media_articles', [
            'id'            => $bookmarked->id,
            'is_bookmarked' => true,
        ]);

        // Total unbookmarked articles tersisa tepat 5
        $remaining = MediaArticle::where('polda_id', $this->polda->id)
            ->where('is_bookmarked', false)
            ->count();
        $this->assertEquals(5, $remaining);
    }
}

