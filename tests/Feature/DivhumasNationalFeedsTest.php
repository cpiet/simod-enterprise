<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\Polda;
use App\Models\User;
use Database\Seeders\DivhumasNationalRssFeedsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DivhumasNationalFeedsTest extends TestCase
{
    use RefreshDatabase;

    protected Polda $polda;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::firstOrCreate(
            ['code' => 'SULBAR'],
            ['name' => 'Polda Sulawesi Barat', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'test_officer'],
            [
                'polda_id'     => $this->polda->id,
                'name'         => 'Bripka Test Pengujian',
                'email'        => 'test.officer@polri.go.id',
                'password'     => bcrypt('secret123'),
                'role'         => 'superadmin',
                'is_active'    => true,
            ]
        );

        $this->seed(DivhumasNationalRssFeedsSeeder::class);
    }

    public function test_divhumas_national_feeds_are_seeded(): void
    {
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();
        $this->assertNotNull($divhumas);
        $this->assertEquals('DIVHUMAS', $divhumas->code);

        $feeds = MediaFeed::where('polda_id', $divhumas->id)->get();
        $this->assertCount(22, $feeds);

        $activeFeeds = $feeds->where('is_active', true);
        $this->assertCount(22, $activeFeeds);
    }

    public function test_media_intelligence_loads_for_national_scope(): void
    {
        $response = $this->actingAs($this->user)->get('/media-intelligence?polda=ALL');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) =>
            $page->component('MediaIntelligence/Index')
                ->where('selectedCode', 'ALL')
                ->has('mediaFeeds', 22)
        );
    }

    public function test_articles_with_images_for_divhumas(): void
    {
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        // Seed an article with cover image
        $article = MediaArticle::create([
            'polda_id'    => $divhumas->id,
            'title'       => 'Polri Gelar Patroli Siber Nasional',
            'source_name' => 'ANTARA NEWS TERKINI',
            'category'    => 'Nasional & Umum',
            'url'         => 'https://antaranews.com/berita/sample-1',
            'image_url'   => 'https://img.antaranews.com/photo/sample.jpg',
            'pub_date'    => now(),
            'sentiment'   => 'positif',
        ]);

        $this->assertNotNull($article->image_url);
        $this->assertEquals('https://img.antaranews.com/photo/sample.jpg', $article->image_url);

        $response = $this->actingAs($this->user)->get('/media-intelligence?polda=ALL');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) =>
            $page->component('MediaIntelligence/Index')
                ->has('sampleArticles', 1)
                ->where('sampleArticles.0.image_url', 'https://img.antaranews.com/photo/sample.jpg')
        );
    }
}
