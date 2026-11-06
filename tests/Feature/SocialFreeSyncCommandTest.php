<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\SocialPost;
use App\Support\ScheduledTasks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sinkron otomatis radar medsos lapis gratis: hanya Google News, tidak pernah Apify berbayar.
 */
class SocialFreeSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_free_sync_pulls_syndication_for_active_poldas_without_apify(): void
    {
        $sulbar = Polda::create(['name' => 'Polda Sulawesi Barat', 'code' => 'SULBAR', 'zone' => 'WITA', 'is_active' => true]);
        Polda::create(['name' => 'Polda Nonaktif', 'code' => 'OFF', 'zone' => 'WIB', 'is_active' => false]);

        $rss = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
            . '<item><title>Warganet apresiasi Polda Sulawesi Barat - TikTok</title><link>https://www.tiktok.com/@warga/video/123</link>'
            . '<pubDate>' . now()->subHour()->toRfc2822String() . '</pubDate><description>Polda Sulawesi Barat sigap</description></item>'
            . '</channel></rss>';
        Http::fake([
            'news.google.com/*' => Http::response($rss, 200),
            'api.apify.com/*' => Http::response(['error' => 'tidak boleh dipanggil'], 500),
        ]);

        $this->artisan('social:sync-free')->assertSuccessful();

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'apify.com'));
        $this->assertGreaterThan(0, SocialPost::where('polda_id', $sulbar->id)->count());
        Http::assertNotSent(fn ($request) => str_contains(urldecode($request->url()), 'Polda Nonaktif'));
    }

    public function test_free_sync_schedule_can_be_disabled(): void
    {
        $find = fn () => collect(ScheduledTasks::definitions())->firstWhere('key', 'social_free_sync');

        config(['simod.social_auto_sync' => true]);
        $this->assertTrue(($find()['enabled'])());

        config(['simod.social_auto_sync' => false]);
        $this->assertFalse(($find()['enabled'])());
    }
}
