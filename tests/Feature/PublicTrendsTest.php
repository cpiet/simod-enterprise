<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\PublicTrendsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Tab Tren Radar Media Sosial: tren pencarian Google (nyata), jam ramai & awan kata tanpa angka karangan,
 * dan grafik 7 hari yang dikelompokkan per tanggal zona waktu tenant.
 */
class PublicTrendsTest extends TestCase
{
    use RefreshDatabase;

    public const FEED = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss xmlns:ht="https://trends.google.com/trending/rss" version="2.0"><channel><title>Daily Search Trends</title>
<item><title>demo ojol jakarta</title><ht:approx_traffic>20K+</ht:approx_traffic><pubDate>Thu, 5 Nov 2026 19:10:00 -0800</pubDate>
<ht:picture>https://encrypted-tbn2.gstatic.com/images?q=abc</ht:picture>
<ht:news_item><ht:news_item_title>Polisi kawal demo ojol di Jakarta</ht:news_item_title><ht:news_item_url>https://contoh.id/berita-1</ht:news_item_url><ht:news_item_source>Contoh</ht:news_item_source></ht:news_item>
<ht:news_item><ht:news_item_title>Bukan tautan</ht:news_item_title><ht:news_item_url>javascript:alert(1)</ht:news_item_url></ht:news_item>
</item>
<item><title>jadwal motogp</title><ht:approx_traffic>500+</ht:approx_traffic><pubDate>Thu, 5 Nov 2026 18:00:00 -0800</pubDate>
<ht:picture>https://evil.example/x.png</ht:picture></item>
</channel></rss>
XML;

    private Polda $polda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->polda = Polda::create(['name' => 'Polda Sulawesi Barat', 'code' => 'SULBAR', 'zone' => 'WITA', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
    }

    private function sulbarUser(): User
    {
        return User::factory()->create(['role' => 'polda_leader', 'polda_id' => $this->polda->id]);
    }

    public function test_refresh_parses_feed_flags_kamtibmas_and_rejects_unsafe_urls(): void
    {
        Http::fake(['trends.google.com/*' => Http::response(self::FEED, 200)]);

        $result = app(PublicTrendsService::class)->refresh();
        $this->assertTrue($result['success']);

        $data = app(PublicTrendsService::class)->cached();
        $this->assertTrue($data['available']);
        $this->assertNotNull($data['fetched_at']);
        [$first, $second] = $data['items'];

        $this->assertSame('demo ojol jakarta', $first['title']);
        $this->assertSame(20000, $first['traffic_num']);
        $this->assertSame(['polisi', 'demo'], $first['kamtibmas']);
        $this->assertCount(1, $first['news'], 'tautan non-http dibuang');
        $this->assertStringStartsWith('https://encrypted-tbn2.gstatic.com/', $first['picture']);

        $this->assertSame([], $second['kamtibmas']);
        $this->assertSame(500, $second['traffic_num']);
        $this->assertNull($second['picture'], 'gambar hanya dari domain gstatic');
    }

    public function test_failed_refresh_keeps_last_good_data_and_records_error(): void
    {
        Http::fake(['trends.google.com/*' => Http::sequence()->push(self::FEED, 200)->push('', 503)]);
        $service = app(PublicTrendsService::class);
        $service->refresh();

        $result = $service->refresh();
        $this->assertFalse($result['success']);
        $data = $service->cached();
        $this->assertCount(2, $data['items']);
        $this->assertStringContainsString('503', $data['last_error']);
    }

    public function test_scheduled_command_refreshes_trends(): void
    {
        Http::fake(['trends.google.com/*' => Http::response(self::FEED, 200)]);
        $this->artisan('social:trends-refresh')->assertSuccessful();
        $this->assertCount(2, Cache::get(PublicTrendsService::CACHE_KEY)['items']);
    }

    public function test_empty_tenant_shows_no_invented_hashtags_words_or_peak_hours(): void
    {
        $this->actingAs($this->sulbarUser())->get('/social-intelligence?tab=trends')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('trendingHashtags', [])
                ->where('wordCloud.words', [])
                ->where('wordCloud.top_keyword', null)
                ->where('peakHours.peak', null)
                ->where('peakHours.sample', 0)
                ->where('publicTrends.available', false)
                ->missing('supportedActors')
            );
    }

    public function test_peak_hours_are_computed_in_tenant_timezone(): void
    {
        // 11.00 UTC = 19.00 WITA
        for ($i = 0; $i < 20; $i++) {
            $this->post_('peak_' . $i, now()->subDays($i % 5)->setTime(11, 15));
        }

        $this->actingAs($this->sulbarUser())->get('/social-intelligence?tab=trends')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('peakHours.sample', 20)
                ->where('peakHours.hours.19', 20)
                ->where('peakHours.peak.start', 19)
                ->where('peakHours.peak.window', '19.00 – 21.00 WITA')
                ->where('peakHours.peak.pct', 100)
                ->where('peakHours.peak.release_at', '18.00 WITA')
            );
    }

    public function test_timeline_groups_posts_by_local_date_and_drilldown_matches(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-11-06 04:00:00', 'UTC')); // 12.00 WITA
        // 5 Nov 23.30 UTC = 6 Nov 07.30 WITA → harus masuk hari ini (6 Nov), bukan kemarin.
        $this->post_('pagi_wita', \Carbon\Carbon::parse('2026-11-05 23:30:00', 'UTC'));

        $user = $this->sulbarUser();
        $this->actingAs($user)->get('/social-intelligence')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('spikeTimeline.days.6.date', '2026-11-06')
                ->where('spikeTimeline.days.6.total', 1)
                ->where('spikeTimeline.days.5.total', 0)
                ->where('spikeTimeline.summary.timezone', 'WITA')
            );

        $this->actingAs($user)->get('/social-intelligence?post_date=2026-11-06')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts.data', 1));
        $this->actingAs($user)->get('/social-intelligence?post_date=2026-11-05')->assertOk()
            ->assertInertia(fn ($page) => $page->has('posts.data', 0));
    }

    private function post_(string $id, $postedAt): void
    {
        SocialPost::create([
            'polda_id' => $this->polda->id, 'platform' => 'x', 'author_name' => 'Warga', 'author_handle' => '@warga',
            'post_id' => $id, 'post_url' => 'https://x.com/warga/status/' . $id, 'content' => 'Laporan warga soal lalu lintas',
            'sentiment' => 'netral', 'posted_at' => $postedAt,
        ]);
    }
}
