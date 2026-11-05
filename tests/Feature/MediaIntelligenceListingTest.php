<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\RssSyncLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Daftar berita Radar Media Online di sisi server: cakupan tenant (termasuk mode Nasional), pencarian, periode,
 * penggabungan berita ganda, paginasi, paritas facet dengan hasil daftar, topik atensi, dan kesegaran data.
 */
class MediaIntelligenceListingTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;
    private Polda $divhumas;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code, string $name) => Polda::create(['code' => $code, 'name' => $name, 'region' => $name, 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->sulbar = $mk('SULBAR', 'Polda Sulbar');
        $this->gorontalo = $mk('GORONTALO', 'Polda Gorontalo');
        $this->divhumas = $mk('DIVHUMAS', 'Divhumas Polri');
    }

    private function user(string $role, ?Polda $polda = null, array $extra = []): User
    {
        return User::create(array_merge([
            'polda_id' => $polda?->id, 'name' => ucfirst($role), 'username' => $role . '_' . uniqid(), 'email' => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => $role, 'is_active' => true,
        ], $extra));
    }

    private function article(Polda $polda, string $title, array $extra = []): MediaArticle
    {
        $this->seq++;

        return MediaArticle::create(array_merge([
            'polda_id' => $polda->id, 'title' => $title, 'source_name' => 'PORTAL A', 'category' => 'Harkamtibmas',
            'url' => 'https://contoh.test/berita-' . $this->seq, 'summary' => 'Ringkasan ' . $this->seq,
            'pub_date' => now()->subMinutes($this->seq), 'sentiment' => 'netral',
        ], $extra));
    }

    private function national(): static
    {
        $admin = $this->user('super_admin_mabes');
        $this->actingAs($admin)->post('/tenant/switch', ['code' => 'ALL']);

        return $this;
    }

    public function test_regional_user_only_sees_own_polda_and_cannot_widen_scope(): void
    {
        $this->article($this->sulbar, 'Berita Sulbar satu');
        $this->article($this->sulbar, 'Berita Sulbar dua');
        $this->article($this->gorontalo, 'Berita Gorontalo satu');
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence?wilayah=' . $this->gorontalo->id . '&polda=ALL')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isNational', false)
                ->where('articlesMeta.total', 2)
                ->where('filters.wilayah', 0)
                ->has('sampleArticles', 2));
    }

    public function test_national_scope_aggregates_all_poldas_and_can_filter_by_wilayah(): void
    {
        config(['simod.national_aggregate' => true]);
        $this->article($this->sulbar, 'Berita Sulbar');
        $this->article($this->gorontalo, 'Berita Gorontalo');
        $this->article($this->divhumas, 'Berita Divhumas');

        $this->national()->get('/media-intelligence')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('isNational', true)
                ->where('articlesMeta.total', 3)
                ->has('facets.wilayah', 3)
                ->where('sampleArticles.0.polda_code', fn ($code) => in_array($code, ['SULBAR', 'GORONTALO', 'DIVHUMAS'], true)));

        $this->get('/media-intelligence?wilayah=' . $this->gorontalo->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('articlesMeta.total', 1)
                ->where('sampleArticles.0.title', 'Berita Gorontalo'));
    }

    public function test_search_runs_on_server_with_phrase_and_exclusion(): void
    {
        $this->article($this->sulbar, 'Kapolri tegaskan reformasi Polri berjalan');
        $this->article($this->sulbar, 'Kapolri bantah kabar hoaks soal mutasi');
        $this->article($this->sulbar, 'Harga cabai naik di Mamuju');
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence?q=' . urlencode('kapolri -hoaks'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('articlesMeta.total', 1)
                ->where('searchTerms', ['kapolri'])
                ->where('sampleArticles.0.title', 'Kapolri tegaskan reformasi Polri berjalan'));

        $this->get('/media-intelligence?q=' . urlencode('"reformasi polri"'))
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 1));

        // Karakter khusus LIKE tidak boleh bocor menjadi wildcard.
        $this->get('/media-intelligence?q=' . urlencode('%%'))
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 0));
    }

    public function test_period_filter_and_pagination(): void
    {
        foreach (range(1, 20) as $i) {
            $this->article($this->sulbar, "Berita baru nomor {$i} hari ini", ['pub_date' => now()->subMinutes($i)]);
        }
        $this->article($this->sulbar, 'Berita lama sekali bulan lalu', ['pub_date' => now()->subDays(45)]);
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence?period=7d&per_page=9&page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->where('articlesMeta.total', 20)
                ->where('articlesMeta.page', 2)
                ->where('articlesMeta.last_page', 3)
                ->where('articlesMeta.from', 10)
                ->where('articlesMeta.to', 18)
                ->has('sampleArticles', 9));

        $this->get('/media-intelligence?period=all')
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 21));

        // Halaman di luar jangkauan dipangkas ke halaman terakhir, bukan galat.
        $this->get('/media-intelligence?page=999&per_page=9')
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.page', 3));

        $this->get('/media-intelligence?period=custom&from=' . now()->subDays(60)->format('Y-m-d') . '&to=' . now()->subDays(30)->format('Y-m-d'))
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 1));
    }

    public function test_duplicate_stories_are_collapsed_into_one_card_with_other_portals(): void
    {
        $this->article($this->sulbar, 'Kapolda Sulbar resmikan gedung baru Polres Mamuju - Detikcom', ['source_name' => 'DETIKCOM']);
        $this->article($this->sulbar, 'Kapolda Sulbar resmikan gedung baru Polres Mamuju | CNN Indonesia', ['source_name' => 'CNN INDONESIA']);
        $this->article($this->sulbar, 'Kapolda Sulbar resmikan gedung baru Polres Mamuju', ['source_name' => 'ANTARA']);
        $this->article($this->sulbar, 'Berita yang sama sekali berbeda isinya hari ini', ['source_name' => 'ANTARA']);
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence')
            ->assertInertia(fn (Assert $page) => $page
                ->where('articlesMeta.total', 2)
                ->where('sampleArticles', fn ($rows) => collect($rows)->contains(fn ($r) => $r['dupe_count'] === 2 && count($r['also_in']) === 2)));

        $this->get('/media-intelligence?group=0')
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 4));
    }

    public function test_facet_counts_equal_the_number_of_cards_when_selected(): void
    {
        $this->article($this->sulbar, 'Polisi ungkap kasus pencurian motor di Mamuju', ['source_name' => 'PORTAL A', 'sentiment' => 'positif', 'category' => 'Hukum & Kriminal']);
        $this->article($this->sulbar, 'Bentrok antarwarga pecah di Polewali', ['source_name' => 'PORTAL B', 'sentiment' => 'negatif']);
        $this->article($this->sulbar, 'Tawuran pelajar terjadi di Majene', ['source_name' => 'PORTAL B', 'sentiment' => 'negatif']);
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence?sentiment=negatif')
            ->assertInertia(function (Assert $page) {
                $page->where('articlesMeta.total', 2)
                    ->where('facets.sentiments.negatif', 2)
                    // Portal dihitung dengan filter lain aktif: hanya PORTAL B yang punya berita negatif.
                    ->where('facets.portals', fn ($p) => collect($p)->firstWhere('name', 'PORTAL B')['count'] === 2
                        && (collect($p)->firstWhere('name', 'PORTAL A')['count'] ?? 0) === 0);
            });

        $this->get('/media-intelligence?portal=PORTAL%20B')
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 2)->where('facets.all', 3));
    }

    public function test_portal_facet_lists_registered_feeds_even_with_zero_articles_and_hides_other_sources(): void
    {
        \App\Models\MediaFeed::create(['polda_id' => $this->sulbar->id, 'name' => 'Portal Terdaftar', 'url' => 'https://a.test/rss', 'category' => 'Regional', 'is_active' => true]);
        \App\Models\MediaFeed::create(['polda_id' => $this->gorontalo->id, 'name' => 'Portal Polda Lain', 'url' => 'https://b.test/rss', 'category' => 'Regional', 'is_active' => true]);
        $this->article($this->sulbar, 'Berita dari hasil crawl internet', ['source_name' => 'TRIBRATANEWS POLDA LAIN', 'feed_id' => null]);
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence')
            ->assertInertia(fn (Assert $page) => $page
                ->where('facets.portals', [['name' => 'PORTAL TERDAFTAR', 'count' => 0]])
                ->where('facets.internet', 1)
                ->where('stats.portals_active', 1));
    }

    public function test_topic_filter_only_accepts_topics_of_the_active_tenant(): void
    {
        $own = MediaTopic::create(['polda_id' => $this->sulbar->id, 'title' => 'Atensi Tambang', 'keywords' => 'tambang, galian', 'priority' => 'high', 'status' => 'active']);
        $foreign = MediaTopic::create(['polda_id' => $this->gorontalo->id, 'title' => 'Atensi Lain', 'keywords' => 'cabai', 'priority' => 'high', 'status' => 'active']);
        $this->article($this->sulbar, 'Tambang ilegal digerebek di Mamuju');
        $this->article($this->sulbar, 'Harga cabai stabil');
        $leader = $this->user('polda_leader', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence?topic=' . $own->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('articlesMeta.total', 1)
                ->where('topics.0.articles_count', 1));

        $this->get('/media-intelligence?topic=' . $foreign->id)
            ->assertInertia(fn (Assert $page) => $page->where('articlesMeta.total', 0));
    }

    public function test_freshness_comes_from_last_successful_sync_and_situation_is_for_leadership_only(): void
    {
        $this->article($this->sulbar, 'Bentrok antarwarga pecah di Polewali', ['sentiment' => 'negatif']);
        RssSyncLog::create(['polda_id' => $this->sulbar->id, 'sync_type' => 'artisan_cli', 'status' => 'success']);
        RssSyncLog::create(['polda_id' => $this->sulbar->id, 'sync_type' => 'artisan_cli', 'status' => 'skipped_locked', 'created_at' => now()->addHour()]);

        $leader = $this->user('polda_leader', $this->sulbar);
        $operator = $this->user('operator', $this->sulbar);

        $this->actingAs($leader)->get('/media-intelligence')
            ->assertInertia(fn (Assert $page) => $page
                ->whereNot('freshness.updated_at', null)
                ->where('situation.top_negative.0.title', 'Bentrok antarwarga pecah di Polewali')
                ->where('stats.negative24', 1));

        $this->actingAs($operator)->get('/media-intelligence')
            ->assertInertia(fn (Assert $page) => $page->where('situation', null));
    }

    public function test_operator_gets_area_shortcut_from_own_polres_only(): void
    {
        $polres = Polres::create(['polda_id' => $this->sulbar->id, 'code' => 'MAMUJU', 'name' => 'Polresta Mamuju', 'is_active' => true]);
        $operator = $this->user('operator', $this->sulbar, ['polres_id' => $polres->id]);
        $outsider = $this->user('operator', $this->gorontalo, ['polres_id' => $polres->id]); // Polres milik Polda lain

        $this->actingAs($operator)->get('/media-intelligence')
            ->assertInertia(fn (Assert $page) => $page->where('myArea', ['label' => 'Polresta Mamuju', 'keyword' => 'Mamuju']));

        $this->actingAs($outsider)->get('/media-intelligence')
            ->assertInertia(fn (Assert $page) => $page->where('myArea', null));
    }

    public function test_every_ingest_path_sets_title_hash_and_rss_sentiment_follows_police_context(): void
    {
        $a = $this->article($this->sulbar, 'Kapolda Sulbar resmikan gedung baru Polres Mamuju - Detikcom');
        $this->assertNotNull($a->fresh()->title_hash);

        $a->update(['title' => 'Judul sudah diganti total menjadi berbeda sekali']);
        $this->assertSame(\App\Support\NewsSearch::titleHash('Judul sudah diganti total menjadi berbeda sekali'), $a->fresh()->title_hash);

        $this->assertSame('positif', \App\Support\NewsSentiment::detect('Polisi tangkap pengedar sabu di Mamuju'));
    }
}
