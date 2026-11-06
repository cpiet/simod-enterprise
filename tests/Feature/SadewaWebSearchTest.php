<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Issue;
use App\Models\Polda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SADEWA mencari di internet bila data SIMOD tidak memuat jawaban: hanya pertanyaan pengguna yang dikirim,
 * jawaban diberi label & sumber, dihitung kuota, dan dapat dimatikan admin.
 */
class SadewaWebSearchTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;
    private User $leader;
    /** @var array<int, array> */
    private array $payloads = [];

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->leader = User::create([
            'polda_id' => $this->polda->id, 'name' => 'Pimpinan', 'username' => 'pimpinan_web', 'email' => 'pimpinan_web@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true,
        ]);
        AiSetting::forPolda($this->polda->id)->update(['gemini_api_key' => 'AIzaSyFakeKeyWebSearch1234567890', 'gemini_model' => 'gemini-3.5-flash-lite']);
        Issue::create([
            'polda_id' => $this->polda->id, 'title' => 'Laporan rahasia pelapor Budi Santoso', 'summary' => 'Isi laporan internal yang tidak boleh keluar',
            'category' => 'Kamtibmas', 'priority' => 'high', 'status' => 'monitoring', 'reported_by' => $this->leader->id,
        ]);
    }

    /** Panggilan pertama → penanda; panggilan kedua (dengan alat google_search) → jawaban bersumber. */
    private function fakeGemini(): void
    {
        $this->payloads = [];
        $text = 'Pertandingan berakhir 2-1 untuk tim tuan rumah.';
        Http::fake(function (HttpRequest $request) use ($text) {
            $this->payloads[] = $request->data();
            if (empty($request->data()['tools'])) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[[CARI_WEB]]']]]]]], 200);
            }

            return Http::response(['candidates' => [[
                'content' => ['parts' => [['text' => $text]]],
                'groundingMetadata' => [
                    'groundingChunks' => [
                        ['web' => ['uri' => 'https://vertexaisearch.cloud.google.com/grounding-api-redirect/abc', 'title' => 'bola.example']],
                        ['web' => ['uri' => 'javascript:alert(1)', 'title' => 'jahat']],
                    ],
                    'groundingSupports' => [
                        ['segment' => ['startIndex' => 0, 'endIndex' => strlen($text)], 'groundingChunkIndices' => [0, 1]],
                    ],
                ],
            ]]], 200);
        });
    }

    public function test_falls_back_to_web_with_label_sources_and_without_internal_data(): void
    {
        $this->fakeGemini();

        $res = $this->actingAs($this->leader)->postJson('/sadewa/chat', ['message' => 'Berapa skor pertandingan semalam?'])->assertOk();

        $this->assertTrue($res->json('from_web'));
        $this->assertSame('Pertandingan berakhir 2-1 untuk tim tuan rumah. [W1]', $res->json('reply'));
        $this->assertCount(1, $res->json('sources'), 'tautan non-http dibuang');
        $this->assertSame('web', $res->json('sources.0.type'));
        $this->assertStringStartsWith('https://', $res->json('sources.0.url'));

        $this->assertCount(2, $this->payloads);
        $first = json_encode($this->payloads[0]);
        $web = json_encode($this->payloads[1], JSON_UNESCAPED_UNICODE);
        $this->assertStringContainsString('Budi Santoso', $first, 'panggilan pertama memakai data SIMOD');
        $this->assertStringNotContainsString('Budi Santoso', $web, 'data internal tidak boleh ikut ke pencarian internet');
        $this->assertStringNotContainsString('Isi laporan internal', $web);
        $this->assertStringContainsString('Berapa skor pertandingan semalam?', $web);

        $this->assertSame(1, DB::table('ai_usage_logs')->where('feature', 'chat_web')->count(), 'pencarian internet dihitung kuota');
        $this->assertTrue(\App\Models\SadewaConversation::where('user_id', $this->leader->id)->first()->messages[1]['from_web'] ?? false);
    }

    public function test_admin_can_disable_web_search(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['web_search_enabled' => false]);
        $this->fakeGemini();

        $res = $this->actingAs($this->leader)->postJson('/sadewa/chat', ['message' => 'Siapa juara MotoGP Qatar?'])->assertOk();

        $this->assertFalse($res->json('from_web'));
        $this->assertStringContainsString('dinonaktifkan administrator', $res->json('reply'));
        $this->assertStringNotContainsString('[[CARI_WEB]]', $res->json('reply'));
        $this->assertCount(1, $this->payloads, 'tidak ada panggilan pencarian');
    }

    public function test_web_search_respects_daily_limit(): void
    {
        AiSetting::forPolda($this->polda->id)->update(['daily_user_limit' => 1]);
        $this->fakeGemini();

        $res = $this->actingAs($this->leader)->postJson('/sadewa/chat', ['message' => 'Kabar terbaru gempa?'])->assertOk();

        $this->assertFalse($res->json('from_web'));
        $this->assertStringContainsString('Pencarian internet tidak dijalankan', $res->json('reply'));
        $this->assertCount(1, $this->payloads);
    }

    public function test_search_quota_rejection_pauses_web_search_without_blocking_other_ai(): void
    {
        $this->payloads = [];
        Http::fake(function (HttpRequest $request) {
            $this->payloads[] = $request->data();
            if (empty($request->data()['tools'])) {
                return Http::response(['candidates' => [['content' => ['parts' => [['text' => '[[CARI_WEB]]']]]]]], 200);
            }

            return Http::response(['error' => ['message' => 'You exceeded your current quota']], 429);
        });

        $res = $this->actingAs($this->leader)->postJson('/sadewa/chat', ['message' => 'Siapa juara MotoGP Qatar?'])->assertOk();
        $this->assertFalse($res->json('from_web'));
        $this->assertStringContainsString('Google menolak permintaan pencarian', $res->json('reply'));

        // Fitur AI lain tidak ditahan (sebelumnya finish() memasang penahanan 10 menit untuk seluruh Polda).
        $this->assertFalse(app(\App\Services\AiQuota::class)->isBlocked($this->polda->id));
        $this->assertNotNull(Cache::get(\App\Http\Controllers\SadewaController::WEB_SEARCH_ISSUE_KEY));

        // Pertanyaan berikutnya tidak mencoba pencarian lagi selama masa istirahat.
        $calls = count($this->payloads);
        $next = $this->actingAs($this->leader)->postJson('/sadewa/chat', ['message' => 'Kabar terbaru gempa?'])->assertOk();
        $this->assertStringContainsString('sementara tidak tersedia', $next->json('reply'));
        $this->assertCount($calls + 1, $this->payloads, 'hanya panggilan data SIMOD, tanpa pencarian');
    }

    public function test_answer_from_simod_data_does_not_trigger_web(): void
    {
        Http::fake(fn () => Http::response(['candidates' => [['content' => ['parts' => [['text' => 'Ada satu isu aktif [I1].']]]]]], 200));

        $res = $this->actingAs($this->leader)->postJson('/sadewa/chat', ['message' => 'Isu apa yang aktif?'])->assertOk();

        $this->assertFalse($res->json('from_web'));
        $this->assertSame('issue', $res->json('sources.0.type'));
        Http::assertSentCount(1);
    }

    public function test_setting_is_saved_from_ai_settings_page(): void
    {
        $admin = User::create([
            'polda_id' => $this->polda->id, 'name' => 'Admin', 'username' => 'admin_web', 'email' => 'admin_web@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true,
        ]);

        $this->actingAs($admin)->post('/settings/ai', ['gemini_model' => 'gemini-3.5-flash-lite', 'web_search_enabled' => false])->assertRedirect();
        $this->assertFalse(AiSetting::webSearchEnabled($this->polda->id));

        $this->actingAs($admin)->post('/settings/ai', ['gemini_model' => 'gemini-3.5-flash-lite', 'web_search_enabled' => true])->assertRedirect();
        $this->assertTrue(AiSetting::webSearchEnabled($this->polda->id));
    }
}
