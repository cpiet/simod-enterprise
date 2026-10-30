<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\Polda;
use App\Services\NewsCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function article(Polda $p, string $url, int $daysOld, bool $bookmarked = false): MediaArticle
    {
        return MediaArticle::create([
            'polda_id' => $p->id, 'title' => 't', 'source_name' => 'S', 'category' => 'c',
            'url' => $url, 'pub_date' => now()->subDays($daysOld), 'is_bookmarked' => $bookmarked,
        ]);
    }

    public function test_purge_removes_old_non_bookmarked_only_for_chosen_polda(): void
    {
        $a = Polda::create(['code' => 'AAA', 'name' => 'Polda A', 'region' => 'A', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $b = Polda::create(['code' => 'BBB', 'name' => 'Polda B', 'region' => 'B', 'timezone' => 'Asia/Makassar', 'is_active' => true]);

        $this->article($a, 'u1', 100);        // dihapus
        $this->article($a, 'u2', 100, true);  // bookmark -> aman
        $this->article($a, 'u3', 5);          // baru -> aman
        $this->article($b, 'u4', 100);        // Polda lain -> aman

        $deleted = app(NewsCleanupService::class)->purge($a->id, 90);

        $this->assertSame(1, $deleted);
        $this->assertSame(3, MediaArticle::count());
        $this->assertTrue(MediaArticle::where('url', 'u2')->exists());
        $this->assertTrue(MediaArticle::where('url', 'u4')->exists());
    }

    public function test_prune_command_and_cleanup_page(): void
    {
        $a = Polda::create(['code' => 'AAA', 'name' => 'Polda A', 'region' => 'A', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->article($a, 'u1', 200);
        $this->article($a, 'u2', 200, true);

        $this->get('/maintenance/news-cleanup?polda=AAA&days=90')->assertOk();

        $this->artisan('media:prune', ['--days' => 90, '--polda' => 'AAA'])->assertSuccessful();
        $this->assertSame(1, MediaArticle::count());
        $this->assertTrue(MediaArticle::first()->is_bookmarked);
    }

    public function test_bookmark_toggle_persists(): void
    {
        $a = Polda::create(['code' => 'AAA', 'name' => 'Polda A', 'region' => 'A', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $art = $this->article($a, 'u1', 1);

        $this->postJson("/media-intelligence/articles/{$art->id}/bookmark")->assertOk()->assertJson(['is_bookmarked' => true]);
        $this->assertTrue($art->fresh()->is_bookmarked);
    }
}
