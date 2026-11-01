<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Polda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GeminiMediaIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Polda::create([
            'id'       => 10,
            'name'     => 'Divisi Humas Polri',
            'code'     => 'DIVHUMAS',
            'level'    => 'mabes',
            'timezone' => 'Asia/Jakarta',
            'is_active'=> true,
        ]);

        Polda::create([
            'id'       => 1,
            'name'     => 'Polda Sulawesi Barat',
            'code'     => 'SULBAR',
            'level'    => 'polda',
            'timezone' => 'Asia/Makassar',
            'is_active'=> true,
        ]);
    }

    public function test_media_intelligence_page_provides_ai_setting_prop(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/media-intelligence?polda=ALL');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('MediaIntelligence/Index')
            ->has('aiSetting')
            ->has('aiPromptTemplate')
        );
    }

    public function test_save_gemini_settings_persists(): void
    {
        $user = User::factory()->create();
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $response = $this->actingAs($user)->postJson('/media-intelligence/gemini-settings?polda=ALL', [
            'gemini_api_key'  => 'AIzaSyTestKey1234567890abcdef',
            'gemini_model'    => 'gemini-2.5-pro',
            'is_auto_analyze' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('ai_settings', [
            'polda_id'        => $divhumas->id,
            'gemini_model'    => 'gemini-2.5-pro',
            'is_auto_analyze' => 1,
        ]);

        $setting = AiSetting::where('polda_id', $divhumas->id)->first();
        $this->assertEquals('AIzaSyTestKey1234567890abcdef', $setting->gemini_api_key);
    }

    public function test_gemini_test_connection_validation(): void
    {
        $user = User::factory()->create();

        // Empty API key should fail
        $response = $this->actingAs($user)->postJson('/media-intelligence/gemini-test?polda=ALL', [
            'api_key' => '',
            'model'   => 'gemini-2.5-pro',
        ]);

        $response->assertStatus(400);
        $response->assertJson(['success' => false]);
    }

    public function test_analyze_gemini_without_api_key_returns_422(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/media-intelligence/analyze-gemini?polda=ALL');

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'status'  => 'missing_api_key',
        ]);
    }

    public function test_gemini_test_connection_with_mock_http(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => json_encode(['status' => 'ok', 'app' => 'SIMOD Enterprise'])],
                            ],
                        ],
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/media-intelligence/gemini-test?polda=ALL', [
            'api_key' => 'AIzaSyMockValidKey',
            'model'   => 'gemini-2.5-pro',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
