<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Services\AiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/** Mengganti kunci API di Pengaturan Mesin AI harus mencabut penahanan kuota dari kunci lama. */
class AiQuotaBlockClearTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_ai_settings_clears_quota_block(): void
    {
        $polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        Cache::put('ai.quota_blocked.' . $polda->id, true, now()->addMinutes(10));
        $this->assertTrue(app(AiQuota::class)->isBlocked($polda->id));

        $this->actingAsSuperAdmin();
        $this->post('/tenant/switch', ['code' => 'SULBAR']);
        $this->post('/settings/ai', [
            'gemini_model' => 'gemini-3.5-flash-lite',
            'raw_api_keys_text' => "AIzaFakeKeyForTest000000000000000000001\nAIzaFakeKeyForTest000000000000000000002",
            'daily_user_limit' => 20,
        ]);

        $this->assertFalse(app(AiQuota::class)->isBlocked($polda->id));
    }

    public function test_new_auth_key_format_with_dot_is_accepted_and_redacted(): void
    {
        \Illuminate\Support\Facades\Http::fake(['generativelanguage.googleapis.com/*' => \Illuminate\Support\Facades\Http::response(['candidates' => [['content' => ['parts' => [['text' => 'OK']]]]]], 200)]);

        $svc = new \App\Services\GeminiService();
        $key = 'AQ.Ab8RN6FakeKeyForTest0000000000000000_x-y';
        $this->assertTrue($svc->testConnection($key)['success']);
        $this->assertSame(['AIzaSyFake' . str_repeat('a', 29), $key], $svc->parseApiKeys("AIzaSyFake" . str_repeat('a', 29) . "
" . $key));
    }
}
