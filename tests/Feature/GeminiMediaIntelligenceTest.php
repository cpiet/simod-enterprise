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
        $user = User::factory()->create(['role' => 'super_admin_mabes']);

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
        $user = User::factory()->create(['role' => 'super_admin_mabes']);

        $response = $this->actingAs($user)->get('/settings/ai?polda=ALL');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Ai')
            ->has('setting')
            ->has('globalSetting')
            ->has('telemetry')
            ->has('socialSetting')
        );
    }

    public function test_admin_can_update_apify_settings_via_ai_controller(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $response = $this->actingAs($user)->post('/settings/ai/apify?polda=ALL', [
            'apify_api_token'     => 'apify_token_test_123',
            'keywords_tracked'    => 'Polri, Kapolri, Humas Presisi',
            'accounts_tracked'    => '@divisihumaspri, @humaspolri',
            'apply_to_all_poldas' => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('social_settings', [
            'apify_api_token'  => 'apify_token_test_123',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'UPDATE_APIFY_SETTINGS',
        ]);
    }

    public function test_admin_can_update_ai_settings_and_audit_log_persists(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $response = $this->actingAs($user)->post('/settings/ai?polda=ALL', [
            'gemini_api_key'      => "AIzaSyMasterKey1234567890abcdef\nAIzaSyBackupKey9876543210zyxwvu",
            'gemini_model'        => 'gemini-3.5-flash-lite',
            'daily_user_limit'    => 25,
            'temperature'         => 0.20,
            'apply_to_all_poldas' => true,
        ]);

        $response->assertStatus(302);

        $divSetting = AiSetting::where('polda_id', $divhumas->id)->first();
        $this->assertNotNull($divSetting);
        $this->assertEquals('AIzaSyMasterKey1234567890abcdef', $divSetting->gemini_api_key);
        $this->assertCount(2, $divSetting->api_keys_pool);
        $this->assertEquals(25, $divSetting->daily_user_limit);

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'action'  => 'UPDATE_AI_SETTINGS',
        ]);
    }

    public function test_analyze_gemini_without_api_key_returns_422(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);
        config(['services.gemini.api_key' => null]);
        putenv('GEMINI_API_KEY=');
        unset($_ENV['GEMINI_API_KEY'], $_SERVER['GEMINI_API_KEY']);
        AiSetting::query()->update(['gemini_api_key' => null, 'api_keys_pool' => null]);

        $response = $this->actingAs($user)->postJson('/media-intelligence/analyze-gemini?polda=ALL');

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'status'  => 'missing_api_key',
        ]);
    }

    public function test_gemini_test_connection_with_mock_http(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);

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
            'model'   => 'gemini-3.5-flash-lite',
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    public function test_gemini_test_pool_keys_with_mock_http(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);

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
            'raw_api_keys_text' => "AIzaSyKey1111111111\nAIzaSyKey2222222222",
            'model'             => 'gemini-3.5-flash-lite',
            'test_pool'         => true,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success'      => true,
            'total'        => 2,
            'active_count' => 2,
        ]);
    }

    public function test_admin_can_customize_ai_prompt_template_and_it_persists(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $customPrompt = "DOKTRIN KHUSUS KEDINASAN HUMAS POLRI 2026\n[Tempelkan teks berita, rangkuman patroli siber, atau link isu di sini]";

        $response = $this->actingAs($user)->post('/settings/ai?polda=ALL', [
            'gemini_model'           => 'gemini-3.5-flash-lite',
            'custom_prompt_template' => $customPrompt,
            'apply_to_all_poldas'    => false,
        ]);

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $divSetting = AiSetting::where('polda_id', $divhumas->id)->first();
        $this->assertNotNull($divSetting);
        $this->assertEquals($customPrompt, $divSetting->custom_prompt_template);

        // Verify that resolvePromptTemplate returns the custom prompt
        $resolvedPrompt = \App\Http\Controllers\MediaIntelligenceController::resolvePromptTemplate($divhumas);
        $this->assertEquals($customPrompt, $resolvedPrompt);
    }

    public function test_admin_can_reset_custom_ai_prompt_template(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $setting = AiSetting::forPolda($divhumas->id);
        $setting->update(['custom_prompt_template' => 'PROMPT KUSTOM SEMENTARA']);

        $response = $this->actingAs($user)->post('/settings/ai/reset-prompt?polda=ALL');

        $response->assertStatus(302);
        $response->assertSessionHas('success');

        $setting->refresh();
        $this->assertNull($setting->custom_prompt_template);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'RESET_AI_PROMPT_TEMPLATE',
        ]);
    }

    public function test_chat_endpoint_validates_input(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);

        $response = $this->actingAs($user)->postJson('/sadewa/chat', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['message']);
    }

    public function test_chat_endpoint_returns_ai_reply_with_mocked_gemini(): void
    {
        $user = User::factory()->create(['role' => 'super_admin_mabes']);
        $divhumas = Polda::where('code', 'DIVHUMAS')->first();

        $setting = AiSetting::forPolda($divhumas->id);
        $setting->update([
            'gemini_api_key' => 'AIzaSyFakeTestKeyForChatbot1234567890',
            'gemini_model'   => 'gemini-2.5-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => 'Siap Jenderal, berdasarkan pemantauan radar siber terkini, situasi kamtibmas dalam kendali kondusif.']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/sadewa/chat', [
            'message'  => 'Bagaimana situasi isu hari ini?',
            'polda_id' => $divhumas->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'reply'   => 'Siap Jenderal, berdasarkan pemantauan radar siber terkini, situasi kamtibmas dalam kendali kondusif.',
        ]);
    }
}
