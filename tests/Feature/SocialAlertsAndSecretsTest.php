<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\MediaTopic;
use App\Models\MonitoredSocialAccount;
use App\Models\Polda;
use App\Models\SocialAlert;
use App\Models\SocialCommentDigest;
use App\Models\SocialSetting;
use App\Models\User;
use App\Services\SocialAlertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Peringatan otomatis social listening, pemantauan berkala dengan batas biaya, dan rahasia AI/Apify tidak ke browser.
 */
class SocialAlertsAndSecretsTest extends TestCase
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
        SocialSetting::create(['polda_id' => $this->polda->id, 'apify_api_token' => "apify_api_rahasiaSATU1234\napify_api_rahasiaDUA56789"]);
    }

    private function as()
    {
        return $this->actingAs($this->admin)->withSession(['active_polda_code' => 'DIVHUMAS']);
    }

    private function tweet(string $id, string $text, int $likes, $hoursAgo = 2): array
    {
        return ['type' => 'tweet', 'url' => "https://x.com/DivHumas_Polri/status/{$id}", 'fullText' => $text, 'likeCount' => $likes, 'replyCount' => 0,
            'createdAt' => now()->subHours($hoursAgo)->toRfc2822String(), 'author' => ['userName' => 'DivHumas_Polri', 'name' => 'Humas Polri']];
    }

    public function test_new_posts_create_alerts_once_and_old_posts_do_not(): void
    {
        MediaTopic::create(['polda_id' => $this->polda->id, 'title' => 'Penyadapan', 'keywords' => 'penyadapan, sadap', 'status' => 'active', 'priority' => 'urgent']);
        $account = MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'DivHumas_Polri', 'posts' => []]);
        Http::fake(['api.apify.com/*' => Http::response([
            $this->tweet('1', 'Polisi amankan demo di Jakarta', 100),
            $this->tweet('2', 'Klarifikasi isu penyadapan presiden', 90),
            $this->tweet('3', 'Kegiatan rutin', 95),
            $this->tweet('4', 'Kegiatan rutin lain', 105),
            $this->tweet('5', 'Video viral', 2000),
            $this->tweet('6', 'Polisi tangkap begal (arsip lama)', 80, 24 * 10),
        ], 201)]);

        $res = app(\App\Services\SocialAccountMonitoringService::class)->sync($account);
        $this->assertTrue($res['success']);
        $types = SocialAlert::pluck('type', 'post_url');
        $this->assertSame('kamtibmas', $types['https://x.com/DivHumas_Polri/status/1']);
        $this->assertTrue(SocialAlert::where('type', 'topik')->where('post_url', 'https://x.com/DivHumas_Polri/status/2')->exists());
        $this->assertTrue(SocialAlert::where('type', 'lonjakan')->where('post_url', 'https://x.com/DivHumas_Polri/status/5')->exists());
        $this->assertFalse(SocialAlert::where('post_url', 'https://x.com/DivHumas_Polri/status/6')->exists(), 'unggahan lama tidak memicu peringatan');

        $count = SocialAlert::count();
        app(\App\Services\SocialAccountMonitoringService::class)->sync($account->fresh());
        $this->assertSame($count, SocialAlert::count(), 'sinkron ulang tidak menggandakan peringatan');
    }

    public function test_negative_comment_share_creates_alert_only_above_threshold(): void
    {
        $base = ['polda_id' => $this->polda->id, 'platform' => 'x', 'fetched_at' => now()];
        $low = SocialCommentDigest::create($base + ['post_url' => 'https://x.com/a/status/1', 'total' => 10, 'negatif' => 3]);
        $high = SocialCommentDigest::create($base + ['post_url' => 'https://x.com/a/status/2', 'total' => 10, 'negatif' => 5]);
        $few = SocialCommentDigest::create($base + ['post_url' => 'https://x.com/a/status/3', 'total' => 4, 'negatif' => 4]);

        $svc = app(SocialAlertService::class);
        $this->assertSame(0, $svc->fromDigest($low));
        $this->assertSame(1, $svc->fromDigest($high));
        $this->assertSame(0, $svc->fromDigest($few), 'terlalu sedikit komentar');
    }

    public function test_alert_endpoint_counts_unread_per_user_and_mark_seen(): void
    {
        SocialAlert::create(['polda_id' => $this->polda->id, 'type' => 'kamtibmas', 'severity' => 'tinggi', 'title' => 'Uji', 'post_url' => 'https://x.com/a/status/9']);
        $other = Polda::create(['name' => 'Polda Lain', 'code' => 'LAIN', 'zone' => 'WIB', 'is_active' => true]);
        SocialAlert::create(['polda_id' => $other->id, 'type' => 'kamtibmas', 'severity' => 'tinggi', 'title' => 'Tenant lain', 'post_url' => 'https://x.com/b/status/9']);

        $this->as()->getJson('/social-intelligence/alerts')->assertOk()->assertJson(['unread' => 1])->assertJsonCount(1, 'items');
        $this->as()->postJson('/social-intelligence/alerts/seen')->assertOk();
        $this->as()->getJson('/social-intelligence/alerts')->assertOk()->assertJson(['unread' => 0]);
    }

    public function test_auto_monitor_refreshes_due_accounts_within_daily_auto_cap(): void
    {
        config(['simod.apify_auto_runs' => 1]);
        $due1 = MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'DivHumas_Polri', 'auto_monitor' => true, 'last_synced_at' => now()->subHours(7)]);
        MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'akun_dua', 'auto_monitor' => true, 'last_synced_at' => now()->subHours(8)]);
        MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'baru_saja', 'auto_monitor' => true, 'last_synced_at' => now()->subHour()]);
        MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'manual', 'auto_monitor' => false]);
        Http::fake(function ($r) {
            $handle = $r['twitterHandles'][0] ?? 'x';

            return Http::response([array_merge($this->tweet('7', 'Kegiatan', 5), ['url' => "https://x.com/{$handle}/status/7", 'author' => ['userName' => $handle]])], 201);
        });

        $this->artisan('social:accounts-refresh')->assertSuccessful();
        Http::assertSentCount(1); // batas 1 run otomatis; akun yang belum jatuh tempo / manual tidak disentuh
        $this->assertNotNull(MonitoredSocialAccount::where('username', 'akun_dua')->first()->posts, 'akun paling lama jatuh tempo diproses lebih dulu');
        $this->assertNull($due1->fresh()->posts);
    }

    public function test_toggle_auto_monitor_is_tenant_scoped(): void
    {
        $acc = MonitoredSocialAccount::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'username' => 'DivHumas_Polri']);
        $other = Polda::create(['name' => 'Polda Lain', 'code' => 'LAIN', 'zone' => 'WIB', 'is_active' => true]);
        $foreign = MonitoredSocialAccount::create(['polda_id' => $other->id, 'platform' => 'x', 'username' => 'asing']);

        $this->as()->postJson("/social-intelligence/accounts/{$acc->id}/auto", ['enabled' => true])->assertOk()->assertJson(['auto_monitor' => true]);
        $this->assertTrue($acc->fresh()->auto_monitor);
        $leader = User::factory()->create(['polda_id' => $this->polda->id, 'role' => 'polda_leader']);
        $this->actingAs($leader)->postJson("/social-intelligence/accounts/{$foreign->id}/auto", ['enabled' => true])->assertNotFound();
    }

    public function test_ai_settings_page_never_sends_raw_keys_or_tokens(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['gemini_api_key' => 'AIzaSyRahasiaKunciGemini123456789012', 'api_keys_pool' => ['AIzaSyRahasiaKunciGemini123456789012']]);
        $html = $this->as()->get('/settings/ai')->assertOk()->getContent();

        $this->assertStringNotContainsString('AIzaSyRahasiaKunciGemini123456789012', $html);
        $this->assertStringNotContainsString('apify_api_rahasiaSATU1234', $html);
        $this->assertStringNotContainsString('raw_api_keys_text', $html);
    }

    public function test_apify_tokens_are_kept_when_box_is_empty_and_can_be_appended_or_cleared(): void
    {
        $post = fn (array $extra) => $this->as()->post('/settings/ai/apify', array_merge(['keywords_tracked' => 'Polri', 'accounts_tracked' => ''], $extra))->assertRedirect();
        $tokens = fn () => SocialSetting::where('polda_id', $this->polda->id)->value('apify_api_token');

        $post(['apify_api_token' => '']);
        $this->assertSame("apify_api_rahasiaSATU1234\napify_api_rahasiaDUA56789", $tokens(), 'kotak kosong tidak menghapus token');

        $post(['apify_api_token' => 'apify_api_baruTIGA00000', 'tokens_mode' => 'append']);
        $this->assertSame("apify_api_rahasiaSATU1234\napify_api_rahasiaDUA56789\napify_api_baruTIGA00000", $tokens());

        $post(['clear_tokens' => true]);
        $this->assertSame('', (string) $tokens());
    }

    public function test_gemini_keys_append_mode_keeps_existing_keys(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['gemini_api_key' => 'AIzaSyLAMA000000000000000000000000000', 'api_keys_pool' => ['AIzaSyLAMA000000000000000000000000000']]);
        $this->as()->post('/settings/ai', ['gemini_model' => 'gemini-3.5-flash-lite', 'gemini_api_key' => 'AIzaSyBARU000000000000000000000000000', 'keys_mode' => 'append'])->assertRedirect();

        $this->assertSame(['AIzaSyLAMA000000000000000000000000000', 'AIzaSyBARU000000000000000000000000000'], AiSetting::forPolda($this->polda->id)->fresh()->api_keys_pool);
    }
}
