<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\User;
use App\Support\NewsSentiment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Sentimen dapat dipertanggungjawabkan: satu metodologi (NewsSentiment) untuk berita & medsos, alasan per item
 * untuk popup, dan data medsos tanpa metrik/akun/tagar/waktu karangan.
 */
class SentimentTransparencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_explain_returns_reason_and_matches_detect(): void
    {
        $r = NewsSentiment::explain('Oknum Polisi Diduga Peras Warga, Propam Turun Tangan');
        $this->assertSame('negatif', $r['sentiment']);
        $this->assertSame('polri_negative', $r['rule']);
        $this->assertTrue($r['police_context']);
        $this->assertContains('oknum', $r['negative']);
        $this->assertStringContainsString('"oknum"', $r['reason']);
        $this->assertSame(NewsSentiment::detect('Oknum Polisi Diduga Peras Warga, Propam Turun Tangan'), $r['sentiment']);

        $p = NewsSentiment::explain('Polisi Tangkap Pengedar Narkoba di Mamuju');
        $this->assertSame('positif', $p['sentiment']);
        $this->assertSame('positive_majority', $p['rule']);

        $n = NewsSentiment::explain('Harga Cabai di Pasar Mamuju Stabil', 'Pedagang menyebut pasokan cukup.');
        $this->assertSame('netral', $n['sentiment']);
        $this->assertSame('none', $n['rule']);
        $this->assertSame('judul + ringkasan', $n['basis']);
    }

    public function test_social_variant_adds_citizen_expressions_and_whole_words_only(): void
    {
        $this->assertSame('negatif', NewsSentiment::detectSocial('Kecewa banget pelayanan SIM lambat sekali'));
        $this->assertSame('positif', NewsSentiment::detectSocial('Mantap, terima kasih pak polisi sudah sigap'));
        // dulu "aman" cocok di dalam "pengamanan" dan "waspada" langsung negatif
        $this->assertSame('netral', NewsSentiment::detectSocial('Rapat pengamanan, warga diminta waspada'));
        // aturan berita tidak berubah untuk teks berita
        $this->assertSame('netral', NewsSentiment::detect('Kecewa banget pelayanan lambat'));
    }

    public function test_syndication_stores_unknown_metrics_as_null_without_injected_hashtag(): void
    {
        $polda = Polda::create(['name' => 'Polda Sulawesi Barat', 'code' => 'SULBAR', 'zone' => 'WITA', 'is_active' => true]);
        $rss = '<?xml version="1.0"?><rss version="2.0"><channel><title>t</title>'
            . '<item><title>Warganet apresiasi Polda Sulawesi Barat sigap bantu korban banjir</title><link>https://www.tiktok.com/@warga/video/1</link>'
            . '<pubDate>Thu, 05 Nov 2026 10:00:00 GMT</pubDate><description>-</description></item>'
            . '<item><title>Unggahan tanpa tanggal soal Polda Sulawesi Barat</title><link>https://x.com/warga/status/2</link><description>-</description></item>'
            . '</channel></rss>';
        Http::fake(['news.google.com/*' => Http::response($rss, 200)]);

        $this->artisan('social:sync-free')->assertSuccessful();

        $posts = SocialPost::where('polda_id', $polda->id)->get();
        $this->assertGreaterThan(0, $posts->count());
        foreach ($posts as $post) {
            $this->assertNull($post->likes_count);
            $this->assertNull($post->views_count);
            $this->assertNull($post->virality_score);
            $this->assertStringNotContainsString('#', $post->content, 'tidak ada tagar kata kunci yang disisipkan');
            $this->assertSame(NewsSentiment::detectSocial($post->content), $post->sentiment);
        }
        $this->assertNull($posts->firstWhere('post_url', 'https://x.com/warga/status/2')?->posted_at, 'tanggal tidak diketahui tetap null, tidak diacak');
    }

    public function test_cleanup_migration_removes_fabricated_values_from_search_posts(): void
    {
        $polda = Polda::create(['name' => 'Polda Sulawesi Barat', 'code' => 'SULBAR', 'zone' => 'WITA', 'is_active' => true]);
        $base = ['polda_id' => $polda->id, 'platform' => 'instagram', 'sentiment' => 'positif', 'posted_at' => now(),
            'likes_count' => 999, 'shares_count' => 99, 'comments_count' => 9, 'views_count' => 9999, 'virality_score' => 88];
        DB::table('social_posts')->insert([
            $base + ['post_id' => 'apify_1', 'author_name' => 'Divisi Humas Polri', 'author_handle' => '@divisihumaspri', 'is_kol' => true,
                'content' => 'Kegiatan divhumas hari ini #PolriPresisi #Kapolri', 'post_url' => 'https://www.instagram.com/p/ABC/',
                'thumbnail_url' => 'https://images.unsplash.com/photo-1?w=600'],
            $base + ['post_id' => 'syndication_1', 'author_name' => 'Kompas', 'author_handle' => '@kompas', 'is_kol' => true,
                'content' => 'Warga kecewa pelayanan lambat #PoldaSulbar', 'post_url' => 'https://news.example/a', 'thumbnail_url' => null],
            $base + ['post_id' => 'ig_asli_1', 'author_name' => 'Akun Asli', 'author_handle' => '@asli', 'is_kol' => false,
                'content' => 'Data dari sumber lain #Tetap', 'post_url' => 'https://www.instagram.com/p/XYZ/', 'thumbnail_url' => null],
        ]);

        (require base_path('database/migrations/2026_11_11_090000_make_social_metrics_nullable_and_clean_fabricated_data.php'))->up();

        $apify = SocialPost::where('post_id', 'apify_1')->first();
        $this->assertNull($apify->likes_count);
        $this->assertNull($apify->virality_score);
        $this->assertNull($apify->posted_at, 'waktu acak dikosongkan');
        $this->assertNull($apify->thumbnail_url, 'gambar stok dihapus');
        $this->assertSame('Kegiatan divhumas hari ini', $apify->content);
        $this->assertSame('', $apify->author_handle, 'akun resmi tebakan dihapus (URL /p/ tidak memuat akun)');
        $this->assertSame('www.instagram.com', $apify->author_name);
        $this->assertFalse($apify->is_kol);

        $syn = SocialPost::where('post_id', 'syndication_1')->first();
        $this->assertSame('Warga kecewa pelayanan lambat', $syn->content);
        $this->assertSame('negatif', $syn->sentiment);
        $this->assertNotNull($syn->posted_at, 'tanggal terbit sindikasi berasal dari sumber, dipertahankan');

        $other = SocialPost::where('post_id', 'ig_asli_1')->first();
        $this->assertSame(999, $other->likes_count, 'data dari sumber lain tidak disentuh');
        $this->assertSame('Data dari sumber lain #Tetap', $other->content);
    }

    public function test_pages_send_reason_per_item_and_lexicon_and_no_invented_kols(): void
    {
        $polda = Polda::create(['name' => 'Divisi Humas Polri', 'code' => 'DIVHUMAS', 'zone' => 'WIB', 'is_active' => true]);
        SocialPost::create(['polda_id' => $polda->id, 'platform' => 'x', 'author_name' => 'Warga', 'author_handle' => '@warga', 'post_id' => 'p1',
            'post_url' => 'https://x.com/warga/status/1', 'content' => 'Terima kasih polisi sigap', 'sentiment' => 'positif', 'posted_at' => now()]);
        $admin = User::factory()->create(['role' => 'super_admin_mabes', 'polda_id' => null]);

        $this->actingAs($admin)->withSession(['active_polda_code' => 'ALL'])->get('/social-intelligence?tab=kol')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('posts.data.0.sentiment_reason.sentiment', 'positif')
                ->has('posts.data.0.sentiment_reason.reason')
                ->has('sentimentLexicon.negative_polri')
                ->where('kolProfiles', [])
            );
    }
}
