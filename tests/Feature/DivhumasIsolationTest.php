<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Demo Divhumas + Polda percontohan (Sulbar): tampilan Divhumas HANYA berisi data Divhumas, Sulbar hanya data Sulbar,
 * dan berita dari feed yang sama di dua Polda tidak saling "mengambil".
 */
class DivhumasIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Polda $divhumas;
    private Polda $sulbar;
    private User $admin;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code) => Polda::create(['code' => $code, 'name' => "Polda {$code}", 'region' => $code, 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->divhumas = $mk('DIVHUMAS');
        $this->sulbar = $mk('SULBAR');
        $this->admin = User::create(['name' => 'Kadiv', 'username' => 'kd_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'kadiv_humas', 'is_active' => true]);

        foreach ([[$this->divhumas, 3], [$this->sulbar, 5]] as [$polda, $n]) {
            foreach (range(1, $n) as $i) {
                $this->seq++;
                MediaArticle::create(['polda_id' => $polda->id, 'title' => "Berita {$polda->code} nomor {$i} cukup panjang", 'source_name' => 'P', 'category' => 'Umum', 'url' => "https://t.test/{$polda->code}/{$i}", 'summary' => 's', 'pub_date' => now()->subMinutes($this->seq), 'sentiment' => 'netral']);
                Issue::create(['polda_id' => $polda->id, 'title' => "Isu {$polda->code} {$i}", 'summary' => 's', 'category' => 'Umum', 'priority' => 'medium', 'status' => 'investigasi']);
                SocialPost::create(['polda_id' => $polda->id, 'platform' => 'x', 'post_id' => "{$polda->code}-{$i}", 'author_name' => 'A', 'author_handle' => 'a', 'content' => 'c', 'post_url' => "https://x.com/{$polda->code}/{$i}", 'sentiment' => 'netral', 'posted_at' => now()]);
            }
        }
    }

    public function test_divhumas_view_shows_only_divhumas_data_by_default(): void
    {
        $this->actingAs($this->admin)->post('/tenant/switch', ['code' => 'ALL']);

        $this->get('/media-intelligence')->assertInertia(fn (Assert $p) => $p
            ->where('selectedCode', 'ALL')->where('isNational', false)
            ->where('articlesMeta.total', 3)->where('facets.wilayah', []));

        $this->get('/issues')->assertInertia(fn (Assert $p) => $p->where('issues.total', 3));

        $this->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('isNational', false)
            ->where('kpi.total_articles', 3)->where('kpi.field_reports', 3)
            ->where('socialStats.total_posts', 3)
            ->where('topIssues', fn ($rows) => collect($rows)->every(fn ($i) => str_contains($i['title'], 'DIVHUMAS')))
            ->where('situationMap.mode', 'national')
            ->where('situationMap.total_articles', 3) // hanya berita Divhumas; berita Polda Sulbar tidak ikut dipetakan
            ->where('situationMap.regions', fn ($regions) => collect($regions)->firstWhere('code', 'SULBAR')['articles_count'] === 0));
    }

    public function test_switching_to_sulbar_shows_only_sulbar_and_sulbar_users_never_see_divhumas(): void
    {
        $this->actingAs($this->admin)->post('/tenant/switch', ['code' => 'SULBAR']);
        $this->get('/media-intelligence')->assertInertia(fn (Assert $p) => $p->where('articlesMeta.total', 5));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('kpi.field_reports', 5)->where('socialStats.total_posts', 5));

        $leader = User::create(['polda_id' => $this->sulbar->id, 'name' => 'Kabid', 'username' => 'kb_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);
        $this->actingAs($leader)->post('/tenant/switch', ['code' => 'ALL'])->assertSessionHas('error');
        $this->get('/media-intelligence?polda=ALL')->assertInertia(fn (Assert $p) => $p->where('articlesMeta.total', 5));
        $this->get('/issues')->assertInertia(fn (Assert $p) => $p->where('issues.total', 5));
    }

    public function test_aggregation_is_opt_in_via_config(): void
    {
        config(['simod.national_aggregate' => true]);
        $this->actingAs($this->admin)->post('/tenant/switch', ['code' => 'ALL']);

        $this->get('/media-intelligence')->assertInertia(fn (Assert $p) => $p->where('isNational', true)->where('articlesMeta.total', 8));
        $this->get('/issues')->assertInertia(fn (Assert $p) => $p->where('issues.total', 8));
    }

    public function test_same_article_url_is_kept_separately_for_each_polda(): void
    {
        config(['simod.ingest_token' => 'token-uji']);
        $payload = fn (string $code, string $title) => ['polda_code' => $code, 'title' => $title, 'url' => 'https://antaranews.com/berita/sama-di-dua-polda', 'source_name' => 'ANTARA', 'summary' => 's'];
        $headers = ['Authorization' => 'Bearer token-uji'];

        $this->postJson('/api/v1/ingest/article', $payload('SULBAR', 'Berita bersama untuk Sulbar'), $headers)->assertSuccessful();
        $this->postJson('/api/v1/ingest/article', $payload('DIVHUMAS', 'Berita bersama untuk Divhumas'), $headers)->assertSuccessful();

        $this->assertSame(2, MediaArticle::where('url', 'https://antaranews.com/berita/sama-di-dua-polda')->count());
        $this->assertSame(1, MediaArticle::where('polda_id', $this->sulbar->id)->where('url', 'https://antaranews.com/berita/sama-di-dua-polda')->count());
        $this->assertSame(1, MediaArticle::where('polda_id', $this->divhumas->id)->where('url', 'https://antaranews.com/berita/sama-di-dua-polda')->count());

        // Kiriman ulang untuk Polda yang sama memperbarui, bukan menggandakan.
        $this->postJson('/api/v1/ingest/article', $payload('SULBAR', 'Berita bersama untuk Sulbar'), $headers)->assertSuccessful();
        $this->assertSame(2, MediaArticle::where('url', 'https://antaranews.com/berita/sama-di-dua-polda')->count());
    }
}
