<?php

namespace Tests\Feature;

use App\Models\AiSetting;
use App\Models\Polda;
use App\Models\User;
use App\Services\AiQuota;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Indikator pemakaian AI: netral untuk pengguna, tidak membuka batas total, admin dapat mencabut penahanan. */
class AiQuotaIndicatorTest extends TestCase
{
    use RefreshDatabase;

    private function setup2(int $limit = 10): array
    {
        $polda = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        AiSetting::updateOrCreate(['polda_id' => $polda->id], ['daily_user_limit' => $limit]);
        $user = User::create(['polda_id' => $polda->id, 'name' => 'Op', 'username' => 'op_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'operator', 'is_active' => true]);

        return [$polda, $user];
    }

    private function use(User $u, ?int $poldaId, int $n): void
    {
        for ($i = 0; $i < $n; $i++) {
            app(AiQuota::class)->log($u, $poldaId, 'chat', 'ok');
        }
    }

    public function test_state_hides_exact_remaining_until_low(): void
    {
        [$polda, $user] = $this->setup2(10);
        $q = app(AiQuota::class);

        $this->assertSame(['state' => 'available', 'remaining' => null], $q->publicStatus($user, $polda->id));

        $this->use($user, $polda->id, 7);
        $this->assertSame(['state' => 'low', 'remaining' => 3], $q->publicStatus($user, $polda->id));

        $this->use($user, $polda->id, 3);
        $this->assertSame('exhausted', $q->publicStatus($user, $polda->id)['state']);
    }

    public function test_blocked_state_and_shared_prop_are_neutral(): void
    {
        [$polda, $user] = $this->setup2(10);
        Cache::put('ai.quota_blocked.' . $polda->id, true, now()->addMinutes(10));

        $this->actingAs($user)->get('/profile')->assertInertia(fn (Assert $p) => $p
            ->where('aiQuota.state', 'blocked')->where('aiQuota.remaining', null)->missing('aiQuota.limit'));
    }

    public function test_admin_can_clear_block_and_it_is_audited(): void
    {
        [$polda] = $this->setup2();
        Cache::put('ai.quota_blocked.' . $polda->id, true, now()->addMinutes(10));

        $this->actingAsSuperAdmin();
        $this->post('/settings/ai/clear-block')->assertRedirect();

        $this->assertFalse(app(AiQuota::class)->isBlocked($polda->id));
        $this->assertDatabaseHas('audit_logs', ['action' => 'ai.block_cleared']);
    }

    public function test_operator_cannot_clear_block(): void
    {
        [, $user] = $this->setup2();
        $this->actingAs($user)->post('/settings/ai/clear-block')->assertStatus(403);
    }
}
