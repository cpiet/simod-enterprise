<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Services\GeminiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Kuota 429 pada satu model (mis. model Pro di paket gratis) tidak boleh menggagalkan analisis
 * bila model lain pada kunci yang sama masih tersedia; galat akhir harus ringkas dan jelas.
 */
class GeminiQuotaFallbackTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
    }

    private function articles(): array
    {
        return [['title' => 'Berita uji', 'source_name' => 'ANTARA', 'sentiment' => 'netral', 'summary' => 'Ringkasan', 'category' => 'Umum', 'pub_date' => now()->toDateTimeString(), 'link' => 'https://example.test/a']];
    }

    public function test_media_analysis_falls_back_to_next_model_when_pro_model_is_quota_limited(): void
    {
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'gemini-3.1-pro-preview')) {
                return Http::response(['error' => ['message' => 'You exceeded your current quota (limit: 0)']], 429);
            }

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => json_encode(['executive_brief' => 'Ringkasan uji', 'top_concern' => 'Uji'])]]]]]], 200);
        });

        $result = (new GeminiService())->analyzeMediaArticles($this->polda, $this->articles(), 'Prompt dasar', 'AIzaSyFakeKeyForQuotaFallback12345', 'gemini-3.1-pro-preview');

        $this->assertTrue($result['success'], $result['message'] ?? '');
        $this->assertNotSame('gemini-3.1-pro-preview', $result['model_used']);
    }

    public function test_media_analysis_returns_short_friendly_message_when_all_models_are_quota_limited(): void
    {
        Http::fake(['generativelanguage.googleapis.com/*' => Http::response(['error' => ['message' => "You exceeded your current quota, please check your plan.\n* Quota exceeded for metric: generate_content_free_tier_requests, limit: 0"]], 429)]);

        $result = (new GeminiService())->analyzeMediaArticles($this->polda, $this->articles(), 'Prompt dasar', 'AIzaSyFakeKeyForQuotaFallback12345');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Kuota layanan analisis AI', $result['message']);
        $this->assertLessThan(400, strlen($result['message']));
        $this->assertStringNotContainsString('generativelanguage', $result['message']);
    }
}
