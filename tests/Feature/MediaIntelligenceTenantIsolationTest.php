<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Aksi pada berita & Topik Atensi harus terkunci ke tenant pengguna (anti-IDOR): personel Polda A tidak boleh
 * mengubah, menghapus, menganalisis, atau men-crawl data milik Polda B dengan menebak ID.
 */
class MediaIntelligenceTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;
    private User $leader;
    private MediaArticle $ownArticle;
    private MediaArticle $foreignArticle;
    private MediaTopic $ownTopic;
    private MediaTopic $foreignTopic;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code) => Polda::create(['code' => $code, 'name' => "Polda {$code}", 'region' => $code, 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->sulbar = $mk('SULBAR');
        $this->gorontalo = $mk('GORONTALO');
        $this->leader = User::create(['polda_id' => $this->sulbar->id, 'name' => 'Kabid', 'username' => 'kb_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);

        $article = fn (Polda $p, string $slug) => MediaArticle::create(['polda_id' => $p->id, 'title' => "Berita {$slug} cukup panjang", 'source_name' => 'P', 'category' => 'Umum', 'url' => "https://t.test/{$slug}", 'summary' => 's', 'pub_date' => now(), 'sentiment' => 'netral']);
        $topic = fn (Polda $p, string $t) => MediaTopic::create(['polda_id' => $p->id, 'title' => $t, 'keywords' => 'kata', 'priority' => 'high', 'status' => 'active']);
        $this->ownArticle = $article($this->sulbar, 'sendiri');
        $this->foreignArticle = $article($this->gorontalo, 'asing');
        $this->ownTopic = $topic($this->sulbar, 'Topik Sendiri');
        $this->foreignTopic = $topic($this->gorontalo, 'Topik Asing');
    }

    public function test_bookmark_only_works_on_own_polda_articles(): void
    {
        $this->actingAs($this->leader)->postJson("/media-intelligence/articles/{$this->ownArticle->id}/bookmark")->assertOk();
        $this->assertTrue($this->ownArticle->fresh()->is_bookmarked);

        $this->actingAs($this->leader)->postJson("/media-intelligence/articles/{$this->foreignArticle->id}/bookmark")->assertForbidden();
        $this->assertFalse($this->foreignArticle->fresh()->is_bookmarked);
    }

    public function test_bookmark_import_ignores_foreign_article_ids(): void
    {
        $res = $this->actingAs($this->leader)->postJson('/media-intelligence/articles/bookmarks/import', ['ids' => [$this->ownArticle->id, $this->foreignArticle->id]]);

        $res->assertOk()->assertJson(['imported' => 1]);
        $this->assertTrue($this->ownArticle->fresh()->is_bookmarked);
        $this->assertFalse($this->foreignArticle->fresh()->is_bookmarked);
    }

    public function test_foreign_topic_cannot_be_edited_deleted_analyzed_or_crawled(): void
    {
        $id = $this->foreignTopic->id;

        $this->actingAs($this->leader)->post("/media-intelligence/topics/{$id}/update", ['title' => 'Dibajak', 'keywords' => 'x'])->assertForbidden();
        $this->actingAs($this->leader)->delete("/media-intelligence/topics/{$id}")->assertForbidden();
        $this->actingAs($this->leader)->postJson("/media-intelligence/topics/{$id}/analyze-ai")->assertForbidden();
        $this->actingAs($this->leader)->postJson("/media-intelligence/topics/{$id}/crawl-live")->assertForbidden();

        $this->assertSame('Topik Asing', $this->foreignTopic->fresh()->title);
        $this->assertNotNull(MediaTopic::find($id));
    }

    public function test_own_topic_can_still_be_edited_and_deleted(): void
    {
        $this->actingAs($this->leader)->post("/media-intelligence/topics/{$this->ownTopic->id}/update", ['title' => 'Judul Baru', 'keywords' => 'kata baru'])->assertRedirect();
        $this->assertSame('Judul Baru', $this->ownTopic->fresh()->title);

        $this->actingAs($this->leader)->delete("/media-intelligence/topics/{$this->ownTopic->id}")->assertRedirect();
        $this->assertNull(MediaTopic::find($this->ownTopic->id));
    }

    public function test_tagging_requires_article_and_topic_from_own_polda(): void
    {
        $url = fn (MediaArticle $a) => "/media-intelligence/articles/{$a->id}/toggle-topic";

        $this->actingAs($this->leader)->postJson($url($this->foreignArticle), ['topic_id' => $this->ownTopic->id])->assertForbidden();
        $this->actingAs($this->leader)->postJson($url($this->ownArticle), ['topic_id' => $this->foreignTopic->id])->assertForbidden();
        $this->assertSame(0, \DB::table('media_article_topic')->count());

        $this->actingAs($this->leader)->postJson($url($this->ownArticle), ['topic_id' => $this->ownTopic->id])->assertSuccessful();
        $this->assertSame(1, \DB::table('media_article_topic')->count());
    }

    public function test_super_admin_may_still_manage_any_polda(): void
    {
        $admin = User::create(['name' => 'Admin', 'username' => 'adm_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true]);

        $this->actingAs($admin)->postJson("/media-intelligence/articles/{$this->foreignArticle->id}/bookmark")->assertOk();
        $this->assertTrue($this->foreignArticle->fresh()->is_bookmarked);
    }
}
