<?php

namespace Tests\Feature;

use App\Models\MonitoredInstagramAccount;
use App\Models\Polda;
use App\Models\SocialSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InstagramAccountMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_saves_only_verified_account_posts_and_preserves_missing_metrics(): void
    {
        $polda = Polda::create(['name' => 'Sulbar', 'code' => 'SULBAR', 'is_active' => true]);
        $user = User::factory()->create(['polda_id' => $polda->id, 'role' => 'superadmin']);
        SocialSetting::create(['polda_id' => $polda->id, 'apify_api_token' => 'test-token']);
        Http::fake(['api.apify.com/*' => Http::response([
            ['ownerUsername' => 'mohmahfudmd', 'ownerFullName' => 'Mahfud MD', 'url' => 'https://www.instagram.com/p/abc123/', 'timestamp' => '2026-10-30T03:58:32Z', 'caption' => 'Posting asli', 'likesCount' => -1, 'commentsCount' => 9],
            ['ownerUsername' => 'oranglain', 'url' => 'https://www.instagram.com/p/xyz123/', 'caption' => 'Orang lain'],
            ['ownerUsername' => 'mohmahfudmd', 'url' => 'https://example.com/p/fake/', 'caption' => 'Tautan palsu'],
        ], 201)]);

        $this->actingAs($user)->withSession(['active_polda_code' => 'SULBAR'])
            ->postJson('/social-intelligence/instagram-accounts', ['username' => '@mohmahfudmd'])
            ->assertOk()->assertJson(['success' => true]);
        $account = MonitoredInstagramAccount::firstOrFail();
        $this->assertCount(1, $account->posts);
        $this->assertNull($account->posts[0]['likes']);
        $this->assertSame(9, $account->posts[0]['comments']);
        Http::assertSent(fn ($request) => str_contains($request->url(), 'maxTotalChargeUsd=0.05') && $request['resultsLimit'] === 12);
    }

    public function test_failed_sync_preserves_previous_data_and_cross_tenant_access_is_denied(): void
    {
        $polda = Polda::create(['name' => 'Sulbar', 'code' => 'SULBAR', 'is_active' => true]);
        $other = Polda::create(['name' => 'Jateng', 'code' => 'JATENG', 'is_active' => true]);
        $user = User::factory()->create(['polda_id' => $polda->id, 'role' => 'polda_leader']);
        SocialSetting::create(['polda_id' => $polda->id, 'apify_api_token' => 'test-token']);
        $account = MonitoredInstagramAccount::create(['polda_id' => $polda->id, 'username' => 'test', 'posts' => [['url' => 'https://www.instagram.com/p/old/']], 'sync_status' => 'success']);
        $foreign = MonitoredInstagramAccount::create(['polda_id' => $other->id, 'username' => 'foreign']);
        Http::fake(['api.apify.com/*' => Http::response([], 201)]);

        $this->actingAs($user)->postJson('/social-intelligence/instagram-accounts/'.$foreign->id.'/sync')->assertNotFound();
        $this->actingAs($user)->postJson('/social-intelligence/instagram-accounts/'.$account->id.'/sync')->assertStatus(422);
        $this->assertSame('empty', $account->fresh()->sync_status);
        $this->assertCount(1, $account->fresh()->posts);
    }
}
