<?php

namespace Tests\Feature;

use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Saat server Gemini sibuk (503), pengguna harus mendapat pesan jelas (bukan galat Inggris mentah),
 * penyebabnya tercatat di log, dan kunci API tidak pernah dikirim lewat URL.
 */
class GeminiBusyServerTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->admin = User::create([
            'polda_id' => $polda->id, 'name' => 'Admin', 'username' => 'busy_' . uniqid(), 'email' => uniqid() . '@polri.go.id',
            'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true,
        ]);
        MediaArticle::create(['polda_id' => $polda->id, 'title' => 'Berita', 'source_name' => 'ANTARA', 'category' => 'Umum', 'url' => 'https://e.test/1', 'summary' => 's', 'pub_date' => now(), 'sentiment' => 'netral']);
        $this->actingAs($this->admin)->post('/settings/ai', [
            'gemini_api_key' => 'AIzaSyFakeKeyForBusyServer1234567',
            'gemini_model'   => 'gemini-3.5-flash-lite',
        ])->assertRedirect();
    }

    public function test_busy_server_shows_friendly_message_and_is_logged(): void
    {
        Log::spy();
        Http::fake(fn () => Http::response(['error' => ['message' => 'This model is currently experiencing high demand.']], 503));

        $res = $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini');

        $message = (string) $res->json('message');
        $this->assertStringContainsString('sedang sibuk', $message);
        $this->assertStringContainsString('bukan masalah API Key', $message);
        $this->assertStringNotContainsString('high demand', $message);

        Log::shouldHaveReceived('warning')->withArgs(fn ($msg) => str_contains((string) $msg, 'HTTP 503'))->atLeast()->once();
    }

    public function test_api_key_is_sent_in_header_never_in_url(): void
    {
        $seen = [];
        Http::fake(function (Request $request) use (&$seen) {
            $seen[] = $request;

            return Http::response(['error' => ['message' => 'sibuk']], 503);
        });

        $this->actingAs($this->admin)->postJson('/media-intelligence/analyze-gemini');

        $this->assertNotEmpty($seen);
        foreach ($seen as $request) {
            $this->assertStringNotContainsString('key=', $request->url());
            $this->assertStringNotContainsString('AIza', $request->url());
            $this->assertSame('AIzaSyFakeKeyForBusyServer1234567', $request->header('x-goog-api-key')[0] ?? null);
        }
    }
}
