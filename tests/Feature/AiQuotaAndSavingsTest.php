<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\MediaArticle;
use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Batas pemakaian AI per pengguna per hari, penahanan saat kuota Google habis,
 * dan penghematan token (data tidak berubah tidak dianalisis ulang).
 */
class AiQuotaAndSavingsTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;
    private User $admin;
    private int $geminiCalls = 0;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->admin = $this->makeUser('super_admin_mabes');
        AiSetting::forPolda($this->polda->id)->update(['gemini_api_key' => 'AIzaSyFakeKeyQuotaTest1234567890', 'gemini_model' => 'gemini-3.5-flash-lite']);
        MediaArticle::create(['polda_id' => $this->polda->id, 'title' => 'Berita A', 'source_name' => 'ANTARA', 'category' => 'Umum', 'url' => 'https://e.test/a', 'summary' => 's', 'pub_date' => now(), 'sentiment' => 'netral']);
        SocialPost::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'author_name' => 'A', 'author_handle' => 'akun1', 'content' => 'Isi satu', 'post_url' => 'https://x.com/1', 'sentiment' => 'netral', 'posted_at' => now()]);
    }

    private function makeUser(string $role): User
    {
        return User::create([
            'polda_id' => $this->polda->id, 'name' => 'U ' . $role . uniqid(), 'username' => 'q_' . uniqid(), 'email' => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => $role, 'is_active' => true,
        ]);
    }

    private function fakeGemini(int $status = 200): void
    {
        $this->geminiCalls = 0;
        Http::fake(function () use ($status) {
            $this->geminiCalls++;
            if ($status !== 200) {
                return Http::response(['error' => ['message' => 'You exceeded your current quota (limit: 0)']], $status);
            }
            $json = json_encode(['executive_brief' => 'Ringkasan uji', 'top_concern' => 'Uji', 'sentiment_analysis' => ['positif' => 30, 'netral' => 50, 'negatif' => 20, 'stability_index' => 80]]);

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => $json]]]]]], 200);
        });
    }

    public function test_media_analysis_is_skipped_when_articles_did_not_change(): void
    {
        $this->fakeGemini();

        $first = $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini')->assertOk();
        $this->assertNotTrue($first->json('skipped'));
        $callsAfterFirst = $this->geminiCalls;
        $this->assertGreaterThan(0, $callsAfterFirst);

        $second = $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini')->assertOk();
        $this->assertTrue($second->json('skipped'));
        $this->assertSame($callsAfterFirst, $this->geminiCalls, 'Tidak boleh ada panggilan Gemini baru.');
        $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'media', 'status' => 'skipped']);

        // berita baru -> dianalisis lagi
        MediaArticle::create(['polda_id' => $this->polda->id, 'title' => 'Berita B', 'source_name' => 'ANTARA', 'category' => 'Umum', 'url' => 'https://e.test/b', 'summary' => 's', 'pub_date' => now()->addMinute(), 'sentiment' => 'netral']);
        $third = $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini')->assertOk();
        $this->assertNotTrue($third->json('skipped'));
        $this->assertGreaterThan($callsAfterFirst, $this->geminiCalls);
    }

    public function test_force_reanalysis_bypasses_skip(): void
    {
        $this->fakeGemini();
        $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini')->assertOk();
        $calls = $this->geminiCalls;

        $res = $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini?force=1')->assertOk();
        $this->assertNotTrue($res->json('skipped'));
        $this->assertGreaterThan($calls, $this->geminiCalls);
    }

    public function test_social_analysis_is_skipped_when_posts_did_not_change(): void
    {
        $this->fakeGemini();
        $this->actingAs($this->admin)->postJson('/social-intelligence/analyze-ai', [])->assertOk();
        $calls = $this->geminiCalls;

        $res = $this->actingAs($this->admin)->postJson('/social-intelligence/analyze-ai', [])->assertOk();
        $this->assertTrue($res->json('skipped'));
        $this->assertSame($calls, $this->geminiCalls);
    }

    public function test_topic_analysis_is_skipped_when_no_new_articles(): void
    {
        $this->fakeGemini();
        $topic = MediaTopic::create([
            'polda_id' => $this->polda->id, 'title' => 'Topik Uji', 'keywords' => 'Berita', 'priority' => 'high',
            'ai_analysis' => ['executive_summary' => 'Sudah ada'], 'ai_analyzed_at' => now()->addMinute(), 'ai_articles_count' => 1,
        ]);

        $res = $this->actingAs($this->admin)->postJson("/media-intelligence/topics/{$topic->id}/analyze-ai");

        $res->assertOk();
        $this->assertTrue($res->json('skipped'));
        $this->assertSame(0, $this->geminiCalls);
    }

    public function test_per_user_daily_limit_blocks_chat_and_shows_remaining(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['daily_user_limit' => 2]);
        $leader = $this->makeUser('polda_leader');
        $other = $this->makeUser('polda_leader');
        $this->fakeGemini();

        $r1 = $this->actingAs($leader)->postJson('/sadewa/chat', ['message' => 'satu'])->assertOk();
        $this->assertSame(1, $r1->json('usage.remaining'));
        $this->actingAs($leader)->postJson('/sadewa/chat', ['message' => 'dua'])->assertOk();

        $blocked = $this->actingAs($leader)->postJson('/sadewa/chat', ['message' => 'tiga'])->assertStatus(429);
        $this->assertStringContainsString('Batas pemakaian AI harian', $blocked->json('message'));
        $this->assertSame(0, $blocked->json('usage.remaining'));

        // pengguna lain tidak terdampak
        $this->actingAs($other)->postJson('/sadewa/chat', ['message' => 'halo'])->assertOk();
    }

    public function test_zero_limit_means_unlimited(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['daily_user_limit' => 0]);
        $leader = $this->makeUser('polda_leader');
        $this->fakeGemini();

        for ($i = 0; $i < 3; $i++) {
            $this->actingAs($leader)->postJson('/sadewa/chat', ['message' => "tanya {$i}"])->assertOk();
        }
    }

    public function test_quota_exhaustion_holds_further_calls_with_clear_message(): void
    {
        $leader = $this->makeUser('polda_leader');
        $this->fakeGemini(429);

        $first = $this->actingAs($leader)->postJson('/sadewa/chat', ['message' => 'satu'])->assertStatus(422);
        $this->assertStringContainsString('Kuota layanan analisis AI', $first->json('message'));
        $callsAfterFirst = $this->geminiCalls;
        $this->assertGreaterThan(0, $callsAfterFirst);

        $second = $this->actingAs($leader)->postJson('/sadewa/chat', ['message' => 'dua'])->assertStatus(429);
        $this->assertStringContainsString('Jatah AI dari Google sedang habis', $second->json('message'));
        $this->assertSame($callsAfterFirst, $this->geminiCalls, 'Saat ditahan tidak boleh memanggil Google lagi.');
        $this->assertDatabaseHas('ai_usage_logs', ['feature' => 'chat', 'status' => 'quota']);
    }

    public function test_ai_settings_page_exposes_usage_and_no_longer_has_automation_fields(): void
    {
        $this->fakeGemini();
        $this->actingAs($this->admin)->postJson('/sadewa/chat', ['message' => 'halo'])->assertOk();

        $this->actingAs($this->admin)->get('/settings/ai')->assertOk()->assertInertia(fn ($p) => $p
            ->where('usage.ok', 1)
            ->has('setting.daily_user_limit')
            ->missing('setting.sync_mode')
            ->missing('setting.scheduled_times'));
    }

    public function test_daily_limit_is_saved_from_ai_settings(): void
    {
        $this->actingAs($this->admin)->post('/settings/ai', ['gemini_model' => 'gemini-3.5-flash-lite', 'daily_user_limit' => 15])->assertRedirect();
        $this->assertSame(15, (int) AiSetting::forPolda($this->polda->id)->fresh()->daily_user_limit);
    }
}
