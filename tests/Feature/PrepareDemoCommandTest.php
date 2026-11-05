<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\SocialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** simod:demo-prepare: dry-run aman secara bawaan; --force membersihkan data contoh tanpa menyentuh data inti. */
class PrepareDemoCommandTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $divhumas;
    private Polda $gorontalo;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code, bool $active = true) => Polda::create(['code' => $code, 'name' => "Polda {$code}", 'region' => $code, 'timezone' => 'Asia/Makassar', 'is_active' => $active]);
        $this->divhumas = $mk('DIVHUMAS', false);   // sengaja nonaktif: harus diaktifkan oleh perintah
        $this->sulbar = $mk('SULBAR');
        $this->gorontalo = $mk('GORONTALO');
        User::create(['polda_id' => $this->sulbar->id, 'name' => 'Kabid', 'username' => 'kb_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);
        MediaFeed::create(['polda_id' => $this->sulbar->id, 'name' => 'Feed Uji', 'url' => 'https://a.test/rss', 'category' => 'Regional', 'is_active' => true]);
        SocialSetting::create(['polda_id' => $this->sulbar->id, 'keywords_tracked' => 'polda sulbar', 'last_synced_at' => now()->subMinutes(15)]);

        Issue::create(['polda_id' => $this->sulbar->id, 'title' => 'Isu contoh', 'summary' => 's', 'category' => 'Umum', 'priority' => 'high', 'status' => 'investigasi']);
        SocialPost::create(['polda_id' => $this->sulbar->id, 'platform' => 'x', 'post_id' => 'contoh-1', 'author_name' => 'A', 'author_handle' => 'a', 'content' => 'c', 'post_url' => 'https://x.com/contoh/1', 'sentiment' => 'netral', 'posted_at' => now()]);
        MediaTopic::create(['polda_id' => $this->sulbar->id, 'title' => 'Topik contoh', 'keywords' => 'kata', 'priority' => 'high', 'status' => 'active']);
        foreach ([['biasa', false], ['disimpan', true]] as $i => [$label, $bookmark]) {
            MediaArticle::create(['polda_id' => $this->sulbar->id, 'title' => "Berita {$label} cukup panjang", 'source_name' => 'P', 'category' => 'Umum', 'url' => "https://t.test/{$i}", 'summary' => 's', 'pub_date' => now(), 'sentiment' => 'netral', 'is_bookmarked' => $bookmark]);
        }
    }

    public function test_dry_run_changes_nothing(): void
    {
        $this->artisan('simod:demo-prepare')->assertSuccessful()->expectsOutputToContain('DRY-RUN');

        $this->assertSame(1, Issue::count());
        $this->assertSame(1, SocialPost::count());
        $this->assertSame(2, MediaArticle::count());
        $this->assertFalse($this->divhumas->fresh()->is_active);
        $this->assertTrue($this->gorontalo->fresh()->is_active);
    }

    public function test_force_purges_sample_data_but_keeps_core_data_and_bookmarks(): void
    {
        $this->artisan('simod:demo-prepare', ['--force' => true])->assertSuccessful();

        $this->assertSame(0, Issue::withTrashed()->count());
        $this->assertSame(0, SocialPost::count());
        $this->assertSame(0, MediaTopic::count());
        $this->assertSame(['Berita disimpan cukup panjang'], MediaArticle::pluck('title')->all(), 'hanya berita ber-bookmark yang tersisa');

        $this->assertSame(1, User::count());
        $this->assertSame(1, MediaFeed::count());
        $this->assertNull(SocialSetting::first()->last_synced_at, 'waktu sinkron karangan dikosongkan');

        $this->assertTrue($this->divhumas->fresh()->is_active);
        $this->assertTrue($this->sulbar->fresh()->is_active);
        $this->assertFalse($this->gorontalo->fresh()->is_active);

        $trace = AuditLog::where('action', 'demo.prepare')->first();
        $this->assertNotNull($trace);
        $this->assertSame(1, $trace->metadata['deleted']['issues']);
    }

    public function test_keep_articles_option_preserves_existing_news(): void
    {
        $this->artisan('simod:demo-prepare', ['--force' => true, '--keep-articles' => true])->assertSuccessful();

        $this->assertSame(2, MediaArticle::count());
        $this->assertSame(0, Issue::count());
    }
}
