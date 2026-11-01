<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\AuditLog;
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

    public function test_admin_can_access_ai_settings_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/settings/ai?polda=ALL');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Ai')
            ->has('setting')
            ->has('globalSetting')
            ->has('telemetry')
        );
    }

    public function test_admin_can_update_ai_settings_and_audit_log_persists(): void
    {
        $user = User::factory()->create();
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $response = $this->actingAs($user)->post('/settings/ai?polda=ALL', [
            'gemini_api_key'      => 'AIzaSyMasterKey1234567890abcdef',
            'gemini_model'        => 'gemini-2.5-pro',
            'is_auto_analyze'     => true,
            'temperature'         => 0.20,
            'apply_to_all_poldas' => true,
        ]);

        $response->assertStatus(302);

        $this->assertDatabaseHas('ai_settings', [
            'polda_id'        => $divhumas->id,
            'gemini_model'    => 'gemini-2.5-pro',
            'is_auto_analyze' => 1,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action'  => 'UPDATE_AI_SETTINGS',
        ]);
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

        $response = $this->actingAs($user)->postJson('/settings/ai/test?polda=ALL', [
            'api_key' => 'AIzaSyMockValidKey',
            'model'   => 'gemini-2.5-pro',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }
}
