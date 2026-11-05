<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\SocialPost;
use App\Models\User;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pengaturan Mesin AI (/settings/ai) harus benar-benar berefek: model pilihan dipakai pertama,
 * suhu terkirim ke Gemini, daftar cadangan tidak memuat Pro otomatis, dan 503 dicoba ulang.
 */
class AiModelSelectionTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;
    private User $admin;
    private array $urls = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->admin = User::create([
            'polda_id' => $this->polda->id, 'name' => 'Admin', 'username' => 'ai_' . uniqid(), 'email' => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true,
        ]);
        MediaArticle::create(['polda_id' => $this->polda->id, 'title' => 'Berita', 'source_name' => 'ANTARA', 'category' => 'Umum', 'url' => 'https://e.test/1', 'summary' => 's', 'pub_date' => now(), 'sentiment' => 'netral']);
        SocialPost::create(['polda_id' => $this->polda->id, 'platform' => 'x', 'author_name' => 'A', 'author_handle' => 'a', 'content' => 'c', 'post_url' => 'https://x.com/1', 'sentiment' => 'netral', 'posted_at' => now()]);
    }

    private function saveSettings(string $model, array $extra = []): void
    {
        $this->actingAs($this->admin)->post('/settings/ai', array_merge([
            'gemini_api_key' => 'AIzaSyFakeKeyForModelSelect1234567',
            'gemini_model'   => $model,
        ], $extra))->assertRedirect();
        $this->assertSame($model, AiSetting::forPolda($this->polda->id)->fresh()->gemini_model);
    }

    private function okResponse()
    {
        $json = json_encode(['executive_brief' => 'Ringkasan uji', 'top_concern' => 'Uji', 'sentiment_analysis' => ['positif' => 30, 'netral' => 50, 'negatif' => 20, 'stability_index' => 80]]);

        return Http::response(['candidates' => [['content' => ['parts' => [['text' => $json]]]]]], 200);
    }

    private function fakeAi(): void
    {
        Http::fake(function ($request) {
            $this->urls[] = $request->url();

            return $this->okResponse();
        });
    }

    private function modelOf(string $url): string
    {
        preg_match('#/models/([^:]+):generateContent#', $url, $m);

        return $m[1] ?? '(tidak ada panggilan)';
    }

    public function test_selected_model_is_first_used_for_media_analysis(): void
    {
        $this->saveSettings('gemini-2.5-flash');
        $this->fakeAi();
        $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini');
        $this->assertSame('gemini-2.5-flash', $this->modelOf($this->urls[0] ?? ''));
    }

    public function test_selected_model_is_first_used_for_social_analysis(): void
    {
        $this->saveSettings('gemini-3.8-flash');
        $this->fakeAi();
        $this->actingAs($this->admin)->postJson('/social-intelligence/analyze-ai', []);
        $this->assertSame('gemini-3.8-flash', $this->modelOf($this->urls[0] ?? ''));
    }

    public function test_selected_model_is_first_used_for_sadewa_chat(): void
    {
        $this->saveSettings('gemini-2.5-pro');
        $this->fakeAi();
        $this->actingAs($this->admin)->postJson('/sadewa/chat', ['message' => 'halo']);
        $this->assertSame('gemini-2.5-pro', $this->modelOf($this->urls[0] ?? ''));
    }

    public function test_temperature_setting_is_sent_to_gemini_with_json_cap(): void
    {
        $this->saveSettings('gemini-3.5-flash-lite', ['temperature' => 0.9]);
        $sent = [];
        Http::fake(function ($request) use (&$sent) {
            $sent[] = $request->data()['generationConfig']['temperature'] ?? null;

            return $this->okResponse();
        });

        $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini');
        $this->actingAs($this->admin)->postJson('/sadewa/chat', ['message' => 'halo']);

        $this->assertEquals(0.4, $sent[0], 'Analisis berformat JSON dibatasi maks. 0.4.');
        $this->assertEquals(0.9, $sent[1], 'Chat memakai nilai slider apa adanya.');
    }

    public function test_pro_models_are_not_added_to_fallback_chain_automatically(): void
    {
        $service = new GeminiService();
        $this->assertSame(['gemini-3.5-flash-lite', 'gemini-3.1-flash-lite', 'gemini-3-flash-preview', 'gemini-3.8-flash'], $service->modelChain('gemini-3.5-flash-lite'));
        $this->assertSame('gemini-2.5-pro', $service->modelChain('gemini-2.5-pro')[0]);
        $this->assertNotContains('gemini-1.5-pro', $service->modelChain('gemini-3.8-flash'));
    }

    public function test_busy_server_503_is_retried_on_same_model(): void
    {
        $this->saveSettings('gemini-3.5-flash-lite');
        $calls = [];
        Http::fake(function ($request) use (&$calls) {
            $calls[] = $request->url();
            if (count($calls) === 1) {
                return Http::response(['error' => ['message' => 'The model is overloaded']], 503);
            }

            return $this->okResponse();
        });

        $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini')->assertOk();

        $this->assertCount(2, $calls);
        $this->assertSame($this->modelOf($calls[0]), $this->modelOf($calls[1]), 'Percobaan ulang harus ke model yang sama.');
    }
}
