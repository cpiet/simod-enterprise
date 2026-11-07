<?php

namespace Tests\Feature;

use App\Models\MonitoredSocialAccount;
use App\Models\Polda;
use App\Models\User;
use App\Support\InstagramHighlights;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Penanda "unggahan menonjol" pada pantauan Instagram: aturan tertulis, tanpa AI, tanpa angka karangan.
 */
class InstagramHighlightsTest extends TestCase
{
    use RefreshDatabase;

    private function igPost(int $likes, int $comments, string $caption = 'Kegiatan hari ini'): array
    {
        return ['url' => 'https://www.instagram.com/p/' . uniqid() . '/', 'caption' => $caption, 'likes' => $likes, 'comments' => $comments, 'posted_at' => now()->toIso8601String()];
    }

    public function test_engagement_spike_and_kamtibmas_keywords_are_flagged(): void
    {
        $posts = InstagramHighlights::annotate([
            $this->igPost(90, 10), $this->igPost(95, 5), $this->igPost(100, 0), $this->igPost(80, 20),
            $this->igPost(900, 100),                                     // 1000 vs median 100 → 10×
            $this->igPost(50, 0, 'Apresiasi polisi mengamankan demo'), // kata kunci kamtibmas
        ]);

        $this->assertNull($posts[0]['highlight']);
        $this->assertSame(10.0, $posts[4]['highlight']['ratio']);
        $this->assertSame([], $posts[4]['highlight']['keywords']);
        $this->assertNull($posts[5]['highlight']['ratio']);
        $this->assertSame(['polisi', 'demo'], $posts[5]['highlight']['keywords']);
    }

    public function test_small_samples_and_missing_metrics_do_not_invent_spikes(): void
    {
        $posts = InstagramHighlights::annotate([
            $this->igPost(10, 0), $this->igPost(500, 0), // hanya 2 unggahan bermetrik: tidak ada median
            ['url' => 'https://www.instagram.com/p/x/', 'caption' => 'tanpa metrik', 'likes' => null, 'comments' => null],
        ]);

        $this->assertNull($posts[0]['highlight']);
        $this->assertNull($posts[1]['highlight']);
        $this->assertNull($posts[2]['highlight']);
    }

    public function test_page_sends_annotated_posts_for_active_tenant_only(): void
    {
        $polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'zone' => 'WITA', 'is_active' => true]);
        $other = Polda::create(['code' => 'JATIM', 'name' => 'Polda Jatim', 'zone' => 'WIB', 'is_active' => true]);
        MonitoredSocialAccount::create(['polda_id' => $polda->id, 'username' => 'tokoh', 'posts' => [$this->igPost(1, 0, 'Polisi patroli malam')]]);
        MonitoredSocialAccount::create(['polda_id' => $other->id, 'username' => 'lain', 'posts' => []]);
        $user = User::factory()->create(['role' => 'polda_leader', 'polda_id' => $polda->id]);

        $this->actingAs($user)->get('/social-intelligence?tab=kol')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('monitoredAccounts', 1)
                ->where('monitoredAccounts.0.username', 'tokoh')
                ->where('monitoredAccounts.0.posts.0.highlight.keywords', ['polisi'])
            );
    }
}
