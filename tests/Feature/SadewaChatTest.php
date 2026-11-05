<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\MediaArticle;
use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\SadewaConversation;
use App\Models\SocialPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * SADEWA AI multi-sumber: lingkup per hak akses, rujukan sumber, riwayat privat per akun.
 */
class SadewaChatTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sulbar = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->gorontalo = Polda::create(['code' => 'GORONTALO', 'name' => 'Polda Gorontalo', 'region' => 'Gorontalo', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        AiSetting::forPolda($this->sulbar->id)->update(['gemini_api_key' => 'AIzaSyFakeKeySadewaTest1234567890', 'gemini_model' => 'gemini-2.5-flash']);
    }

    private function user(string $role, ?int $poldaId, array $extra = []): User
    {
        return User::create([
            'polda_id' => $poldaId, 'name' => 'Uji ' . $role, 'username' => 'sdw_' . uniqid(),
            'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => $role, 'is_active' => true,
        ] + $extra);
    }

    private function fakeGemini(string $text, ?array &$captured = null): void
    {
        Http::fake(function ($request) use ($text, &$captured) {
            $captured = $request->data();

            return Http::response(['candidates' => [['content' => ['parts' => [['text' => $text]]]]]], 200);
        });
    }

    private function article(int $poldaId, string $title): MediaArticle
    {
        return MediaArticle::create([
            'polda_id' => $poldaId, 'title' => $title, 'source_name' => 'Antara', 'category' => 'Umum',
            'url' => 'https://example.test/' . uniqid(), 'summary' => 'Ringkasan ' . $title, 'pub_date' => now(), 'sentiment' => 'negatif',
        ]);
    }

    public function test_guest_cannot_use_sadewa(): void
    {
        $this->postJson('/sadewa/chat', ['message' => 'halo'])->assertUnauthorized();
        $this->getJson('/sadewa/conversations')->assertUnauthorized();
    }

    public function test_chat_cites_sources_and_saves_history_for_owner(): void
    {
        $user = $this->user('polda_leader', $this->sulbar->id);
        $this->article($this->sulbar->id, 'Banjir Mamuju');
        $this->fakeGemini('Banjir dilaporkan Antara [M1]. Kode tidak valid [M99].');

        $res = $this->actingAs($user)->postJson('/sadewa/chat', ['message' => 'Apa isu hari ini?', 'scope' => 'media'])
            ->assertOk()->assertJsonPath('success', true);

        $this->assertCount(1, $res->json('sources'));
        $this->assertSame('M1', $res->json('sources.0.code'));
        $this->assertSame(1, SadewaConversation::where('user_id', $user->id)->count());

        // lanjutan percakapan menambah pesan pada riwayat yang sama
        $this->actingAs($user)->postJson('/sadewa/chat', [
            'message' => 'Lanjutkan', 'scope' => 'media', 'conversation_id' => $res->json('conversation_id'),
        ])->assertOk();
        $this->assertCount(4, SadewaConversation::first()->messages);
    }

    public function test_history_is_private_per_user(): void
    {
        $a = $this->user('polda_leader', $this->sulbar->id);
        $b = $this->user('polda_leader', $this->sulbar->id);
        $this->fakeGemini('Siap.');

        $id = $this->actingAs($a)->postJson('/sadewa/chat', ['message' => 'Halo'])->json('conversation_id');

        $this->actingAs($b)->getJson("/sadewa/conversations/{$id}")->assertNotFound();
        $this->actingAs($b)->postJson('/sadewa/chat', ['message' => 'x', 'conversation_id' => $id])->assertNotFound();
        $this->actingAs($b)->deleteJson("/sadewa/conversations/{$id}")->assertOk();
        $this->assertDatabaseHas('sadewa_conversations', ['id' => $id]);
        $this->assertCount(0, $this->actingAs($b)->getJson('/sadewa/conversations')->json('data'));
        $this->assertCount(1, $this->actingAs($a)->getJson('/sadewa/conversations')->json('data'));

        $this->actingAs($a)->deleteJson("/sadewa/conversations/{$id}")->assertOk();
        $this->assertDatabaseMissing('sadewa_conversations', ['id' => $id]);
    }

    public function test_regional_user_cannot_read_other_polda_data_via_client_input(): void
    {
        $user = $this->user('polda_leader', $this->sulbar->id);
        $this->article($this->sulbar->id, 'Berita Sulbar Saja');
        $this->article($this->gorontalo->id, 'RAHASIA Gorontalo');
        $this->fakeGemini('Siap.', $captured);

        $this->actingAs($user)->postJson('/sadewa/chat', ['message' => 'ringkas', 'scope' => 'media', 'polda_id' => $this->gorontalo->id, 'polda_code' => 'GORONTALO'])->assertOk();

        $prompt = json_encode($captured);
        $this->assertStringContainsString('Berita Sulbar Saja', $prompt);
        $this->assertStringNotContainsString('RAHASIA Gorontalo', $prompt);
    }

    public function test_scope_without_module_permission_is_rejected_and_not_sent_to_ai(): void
    {
        $user = $this->user('polda_leader', $this->sulbar->id);
        $perms = array_fill_keys(array_column(RolePermission::defaultModules(), 'key'), true);
        $perms['social_intelligence_view'] = false;
        RolePermission::updateOrCreate(
            ['polda_id' => $this->sulbar->id, 'role_key' => 'polda_leader'],
            ['role_name' => 'Pimpinan Polda', 'description' => 'uji', 'permissions' => $perms]
        );
        SocialPost::create([
            'polda_id' => $this->sulbar->id, 'platform' => 'x', 'author_name' => 'A', 'author_handle' => 'akunrahasia',
            'content' => 'POSTINGAN SOSIAL TERLARANG', 'post_url' => 'https://x.com/1', 'sentiment' => 'negatif', 'virality_score' => 90, 'posted_at' => now(),
        ]);
        $this->fakeGemini('Siap.', $captured);

        $this->actingAs($user)->postJson('/sadewa/chat', ['message' => 'medsos?', 'scope' => 'social'])->assertForbidden();

        // lingkup "all" hanya memuat sumber yang diizinkan
        $this->actingAs($user)->postJson('/sadewa/chat', ['message' => 'semua'])->assertOk();
        $this->assertStringNotContainsString('POSTINGAN SOSIAL TERLARANG', json_encode($captured));

        $meta = $this->actingAs($user)->getJson('/sadewa/meta')->assertOk()->json('scopes');
        $this->assertNotContains('social', array_column($meta, 'key'));
    }

    public function test_social_scope_includes_social_posts_for_authorized_user(): void
    {
        $user = $this->user('polda_leader', $this->sulbar->id);
        SocialPost::create([
            'polda_id' => $this->sulbar->id, 'platform' => 'x', 'author_name' => 'A', 'author_handle' => 'warga1',
            'content' => 'Isi postingan viral uji', 'post_url' => 'https://x.com/2', 'sentiment' => 'negatif', 'virality_score' => 80, 'posted_at' => now(),
        ]);
        $this->fakeGemini('Ada tren negatif [S1].', $captured);

        $res = $this->actingAs($user)->postJson('/sadewa/chat', ['message' => 'tren medsos', 'scope' => 'social'])->assertOk();

        $this->assertStringContainsString('Isi postingan viral uji', json_encode($captured));
        $this->assertSame('S1', $res->json('sources.0.code'));
        $this->assertSame('https://x.com/2', $res->json('sources.0.url'));
    }

    public function test_validation_rejects_empty_and_unknown_scope(): void
    {
        $user = $this->user('polda_leader', $this->sulbar->id);
        $this->actingAs($user)->postJson('/sadewa/chat', [])->assertStatus(422)->assertJsonValidationErrors(['message']);
        $this->actingAs($user)->postJson('/sadewa/chat', ['message' => 'x', 'scope' => 'rahasia'])->assertStatus(422);
    }
}
