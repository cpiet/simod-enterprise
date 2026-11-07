<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\MonitoredSocialAccount;
use App\Models\Polda;
use App\Models\SocialCommentDigest;
use App\Models\SocialSetting;
use App\Models\User;
use App\Services\ApifyClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Social listening: failover token Apify, Pantau Akun lintas platform (format keluaran Actor nyata, doc 35),
 * dan Ringkasan Komentar. Tidak ada panggilan jaringan sungguhan (Http::fake).
 */
class SocialListeningTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->polda = Polda::create(['name' => 'Divisi Humas Polri', 'code' => 'DIVHUMAS', 'zone' => 'WIB', 'is_active' => true]);
        $this->admin = User::factory()->create(['polda_id' => $this->polda->id, 'role' => 'superadmin']);
        SocialSetting::create(['polda_id' => $this->polda->id, 'apify_api_token' => "token-satu\ntoken-dua\ntoken-tiga"]);
    }

    private function as()
    {
        return $this->actingAs($this->admin)->withSession(['active_polda_code' => 'DIVHUMAS']);
    }

    public function test_failover_skips_exhausted_and_invalid_tokens_and_rests_exhausted_one(): void
    {
        $used = [];
        Http::fake(function (HttpRequest $r) use (&$used) {
            $token = str_replace('Bearer ', '', $r->header('Authorization')[0] ?? '');
            $used[] = $token;

            return match ($token) {
                'token-satu' => Http::response(['error' => ['message' => 'Monthly usage hard limit exceeded']], 402),
                'token-dua' => Http::response(['error' => ['message' => 'invalid token']], 401),
                default => Http::response([['ok' => true]], 201),
            };
        });

        $run = app(ApifyClient::class)->run('apify~instagram-scraper', [], $this->polda->id, 3);
        $this->assertTrue($run['success']);
        $this->assertSame(2, $run['token_index']);
        $this->assertSame(['token-satu', 'token-dua', 'token-tiga'], $used);

        // Token yang saldonya habis diistirahatkan: run berikutnya tidak mencobanya lagi.
        $used = [];
        app(ApifyClient::class)->run('apify~instagram-scraper', [], $this->polda->id, 3);
        $this->assertSame(['token-dua', 'token-tiga'], $used);
    }

    public function test_all_tokens_exhausted_gives_honest_message(): void
    {
        Http::fake(['api.apify.com/*' => Http::response(['error' => ['message' => 'usage limit']], 402)]);
        $run = app(ApifyClient::class)->run('x', [], $this->polda->id, 3);
        $this->assertFalse($run['success']);
        $this->assertStringContainsString('Saldo atau batas pemakaian Apify habis', $run['message']);

        $again = app(ApifyClient::class)->run('x', [], $this->polda->id, 3);
        $this->assertStringContainsString('diistirahatkan', $again['message']);
    }

    public function test_daily_run_cap_is_enforced(): void
    {
        config(['simod.apify_daily_runs' => 1]);
        Http::fake(['api.apify.com/*' => Http::response([], 201)]);
        $this->assertTrue(app(ApifyClient::class)->run('x', [], $this->polda->id, 3)['success']);
        $second = app(ApifyClient::class)->run('x', [], $this->polda->id, 3);
        $this->assertFalse($second['success']);
        $this->assertSame('daily_limit', $second['status']);
        Http::assertSentCount(1);
    }

    /** @return array<string, array{0: string, 1: string, 2: array, 3: array}> */
    public static function platforms(): array
    {
        return [
            'tiktok' => ['tiktok', 'khaby.lame', [
                ['text' => 'Video asli #comedy', 'createTimeISO' => '2026-10-16T16:46:21.000Z', 'webVideoUrl' => 'https://www.tiktok.com/@khaby.lame/video/7697307919943060767',
                    'diggCount' => 347300, 'shareCount' => 34100, 'playCount' => 6200000, 'commentCount' => 8717, 'hashtags' => [['name' => 'comedy']],
                    'videoMeta' => ['coverUrl' => 'https://p19-common-sign.tiktokcdn-us.com/cover.jpg'], 'authorMeta' => ['name' => 'khaby.lame', 'nickName' => 'Khabane lame', 'fans' => 163000000, 'verified' => true]],
                ['text' => 'Akun lain', 'webVideoUrl' => 'https://www.tiktok.com/@lain/video/1', 'authorMeta' => ['name' => 'lain']],
            ], ['likes' => 347300, 'views' => 6200000, 'shares' => 34100, 'followers' => 163000000, 'name' => 'Khabane lame']],
            'youtube' => ['youtube', 'KompasTV', [
                ['title' => 'Penyadapan', 'text' => 'Deskripsi', 'type' => 'video', 'url' => 'https://www.youtube.com/watch?v=e4rZD5j4zxU', 'thumbnailUrl' => 'https://i.ytimg.com/vi/e4rZD5j4zxU/maxresdefault.jpg',
                    'viewCount' => 4046, 'date' => '2026-11-07T10:49:16.000Z', 'likes' => 38, 'commentsCount' => 13, 'channelName' => 'KOMPASTV', 'channelUsername' => 'kompastv', 'numberOfSubscribers' => 20100000],
            ], ['likes' => 38, 'views' => 4046, 'shares' => null, 'followers' => 20100000, 'name' => 'KOMPASTV']],
            'x' => ['x', 'DivHumas_Polri', [
                ['type' => 'tweet', 'url' => 'https://x.com/DivHumas_Polri/status/2118506904091795923', 'fullText' => 'Kapolri meresmikan', 'likeCount' => 103, 'replyCount' => 128,
                    'retweetCount' => 16, 'viewCount' => 6585, 'createdAt' => 'Fri Nov 06 00:53:00 +0000 2026', 'media' => ['https://pbs.twimg.com/media/foto.jpg'], 'author' => ['userName' => 'DivHumas_Polri', 'name' => 'Humas Polri', 'followers' => 2098782, 'isBlueVerified' => true]],
                ['type' => 'tweet', 'isRetweet' => true, 'url' => 'https://x.com/DivHumas_Polri/status/1', 'fullText' => 'RT', 'author' => ['userName' => 'DivHumas_Polri']],
            ], ['likes' => 103, 'views' => 6585, 'shares' => 16, 'followers' => 2098782, 'name' => 'Humas Polri']],
            'facebook' => ['facebook', 'KompasTV', [
                ['url' => 'https://www.facebook.com/reel/2175720106487891/', 'text' => 'Terlilit pinjol', 'time' => '2026-11-07T10:58:58.000Z', 'likes' => 1, 'shares' => 0,
                    'isVideo' => true, 'videoPostViewCount' => 0, 'media' => [['thumbnail' => 'https://scontent-phx1-1.xx.fbcdn.net/v/thumb.jpg']], 'user' => ['name' => 'Kompas TV']],
            ], ['likes' => 1, 'views' => 0, 'shares' => 0, 'followers' => null, 'name' => 'Kompas TV']],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('platforms')]
    public function test_each_platform_keeps_only_own_verified_posts_with_real_metrics(string $platform, string $username, array $items, array $expect): void
    {
        Http::fake(['api.apify.com/*' => Http::response($items, 201)]);

        $this->as()->postJson('/social-intelligence/accounts', ['platform' => $platform, 'username' => $username])->assertOk()->assertJson(['success' => true]);
        $account = MonitoredSocialAccount::firstOrFail();
        $this->assertSame($platform, $account->platform);
        $this->assertCount(1, $account->posts, 'akun lain / retweet tidak disimpan');
        $post = $account->posts[0];
        $this->assertSame($expect['likes'], $post['likes']);
        $this->assertSame($expect['views'], $post['views']);
        $this->assertSame($expect['shares'], $post['shares']);
        $this->assertSame($expect['name'], $account->display_name);
        $this->assertSame($expect['followers'], $account->profile['followers'] ?? null);
        $this->assertNotNull($post['thumb']);
    }

    public function test_private_tiktok_and_non_public_facebook_give_honest_reasons(): void
    {
        Http::fake(['api.apify.com/*' => Http::sequence()
            ->push([['authorMeta' => ['name' => 'divisihumaspolri', 'privateAccount' => true], 'note' => 'Profile is private']], 201)
            ->push([['url' => 'https://www.facebook.com/x', 'error' => 'not_available', 'errorDescription' => 'only shared with a small group']], 201)]);

        $this->as()->postJson('/social-intelligence/accounts', ['platform' => 'tiktok', 'username' => 'divisihumaspolri'])
            ->assertStatus(422)->assertJson(['message' => 'Akun TikTok ini privat; unggahannya tidak dapat diambil.']);
        $this->as()->postJson('/social-intelligence/accounts', ['platform' => 'facebook', 'username' => 'DivHumasPolri'])
            ->assertStatus(422)->assertJsonFragment(['success' => false]);
        $this->assertStringContainsString('Halaman Facebook tidak publik', MonitoredSocialAccount::where('platform', 'facebook')->first()->last_error);
    }

    public function test_username_format_and_pasted_profile_url_are_validated_per_platform(): void
    {
        Http::fake(['api.apify.com/*' => Http::response([], 201)]);
        $this->as()->postJson('/social-intelligence/accounts', ['platform' => 'x', 'username' => 'nama terlalu-panjang-sekali'])->assertStatus(422)->assertJsonValidationErrors('username');
        $this->as()->postJson('/social-intelligence/accounts', ['platform' => 'tiktok', 'username' => 'https://www.tiktok.com/@Khaby.Lame'])->assertStatus(422);
        $this->assertSame('khaby.lame', MonitoredSocialAccount::firstOrFail()->username, 'tautan profil diterima & dinormalkan');
        $this->as()->postJson('/social-intelligence/accounts', ['platform' => 'myspace', 'username' => 'abc'])->assertStatus(422);
    }

    public function test_comment_digest_scores_real_comments_and_rejects_foreign_posts(): void
    {
        $account = MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'DivHumas_Polri',
            'posts' => [['url' => 'https://x.com/DivHumas_Polri/status/2118506904091795923', 'caption' => 'Kapolri meresmikan']]]);
        Http::fake(['api.apify.com/*' => Http::response([
            ['id' => '2118506904091795923', 'fullText' => 'Tweet asli', 'author' => ['userName' => 'DivHumas_Polri']],
            ['id' => '1', 'fullText' => '@DivHumas_Polri Mantap, terima kasih pak polisi sigap', 'author' => ['userName' => 'warga1'], 'likeCount' => 3],
            ['id' => '2', 'fullText' => '@DivHumas_Polri Kecewa, oknum polisi pungli di jalan', 'author' => ['userName' => 'warga2'], 'likeCount' => 9],
            ['id' => '3', 'fullText' => '@DivHumas_Polri Kapan jadwal SIM keliling pungli pungli', 'author' => ['userName' => 'warga3']],
        ], 201)]);

        $res = $this->as()->postJson('/social-intelligence/comments', ['account_id' => $account->id, 'post_url' => 'https://x.com/DivHumas_Polri/status/2118506904091795923'])->assertOk();
        $this->assertSame(3, $res->json('digest.total'), 'tweet asli tidak dihitung sebagai komentar');
        $this->assertSame(1, $res->json('digest.positif'));
        $this->assertGreaterThanOrEqual(1, $res->json('digest.negatif'));
        $this->assertSame('Mantap, terima kasih pak polisi sigap', collect($res->json('digest.comments'))->firstWhere('author', 'warga1')['text'], '@mention di awal dibuang');
        Http::assertSent(fn ($r) => str_contains($r->url(), 'apidojo~tweet-scraper') && $r['searchTerms'] === ['conversation_id:2118506904091795923']);

        // URL di luar snapshot akun ditolak (tidak mengambil sembarang URL berbayar).
        $this->as()->postJson('/social-intelligence/comments', ['account_id' => $account->id, 'post_url' => 'https://x.com/orang/status/9'])->assertStatus(422);
    }

    public function test_ai_summary_uses_quota_and_is_not_repeated_for_same_comments(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['gemini_api_key' => 'AIzaSyFakeKeyComment1234567890123', 'gemini_model' => 'gemini-3.5-flash-lite']);
        $digest = SocialCommentDigest::create(['polda_id' => $this->polda->id, 'platform' => 'instagram', 'post_url' => 'https://www.instagram.com/p/A/',
            'post_caption' => 'Unggahan', 'comments' => [['text' => 'Kecewa', 'sentiment' => 'negatif']], 'total' => 1, 'negatif' => 1, 'fetched_at' => now()->subMinute()]);
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['candidates' => [['content' => ['parts' => [['text' => '1. Ringkasan: warganet kecewa [k1].']]]]]], 200)]);

        $this->as()->postJson("/social-intelligence/comments/{$digest->id}/summarize")->assertOk()->assertJsonPath('digest.ai_summary', '1. Ringkasan: warganet kecewa [k1].');
        $this->assertSame(1, DB::table('ai_usage_logs')->where('feature', 'comment_summary')->count());

        $this->as()->postJson("/social-intelligence/comments/{$digest->id}/summarize")->assertOk()->assertJson(['skipped' => true]);
        Http::assertSentCount(1);
    }
}
