<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\RssSyncSetting;
use App\Models\User;
use App\Services\RssSyncService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Jadwal sinkron RSS otomatis: hanya pengaturan global yang ditegakkan, jam dinas dalam WIB,
 * jadwal berikutnya terlihat di halaman, dan hanya Super Admin yang dapat mengubah jadwal nasional.
 */
class RssScheduleTest extends TestCase
{
    use RefreshDatabase;

    private Polda $polda;

    protected function setUp(): void
    {
        parent::setUp();
        $this->polda = Polda::create(['name' => 'Polda Sulawesi Barat', 'code' => 'SULBAR', 'zone' => 'WITA', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'polda_id' => $this->polda->id, 'sync_mode' => 'interval', 'interval_minutes' => 30, 'scheduled_times' => ['06:00'],
            'max_articles_per_feed' => 25, 'max_retained_articles_per_polda' => 1000, 'auto_prune_days' => 30, 'timeout_seconds' => 8,
            'is_active' => true,
        ], $override);
    }

    public function test_scheduled_times_are_read_in_wib_not_server_utc(): void
    {
        $setting = RssSyncSetting::forPolda(null);
        $setting->update(['sync_mode' => 'scheduled_times', 'scheduled_times' => ['06:00'], 'is_active' => true, 'last_sync_at' => null]);
        $service = app(RssSyncService::class);

        $this->travelTo(Carbon::parse('2026-11-07 06:00:30', 'UTC')); // 13.00 WIB
        $this->assertFalse($service->shouldRunNow($setting->fresh()));

        $this->travelTo(Carbon::parse('2026-11-06 23:00:30', 'UTC')); // 06.00 WIB
        $this->assertTrue($service->shouldRunNow($setting->fresh()));
    }

    public function test_next_run_follows_interval_and_is_null_when_manual(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 10:00:00', 'UTC'));
        $setting = RssSyncSetting::forPolda(null);
        $setting->update(['sync_mode' => 'interval', 'interval_minutes' => 30, 'is_active' => true, 'last_sync_at' => now()->subMinutes(10)]);
        $this->assertSame('2026-11-07 10:20:00', app(RssSyncService::class)->nextRunAt($setting->fresh())->toDateTimeString());

        $setting->update(['sync_mode' => 'manual']);
        $this->assertNull(app(RssSyncService::class)->nextRunAt($setting->fresh()));
    }

    public function test_only_super_admin_changes_national_schedule(): void
    {
        RssSyncSetting::forPolda(null)->update(['interval_minutes' => 720]);
        $leader = User::factory()->create(['role' => 'polda_leader', 'polda_id' => $this->polda->id]);
        \App\Models\RolePermission::create(['polda_id' => $this->polda->id, 'role_key' => 'polda_leader', 'role_name' => 'Pimpinan Polda', 'permissions' => ['dashboard_view' => true, 'maintenance_ops' => true]]);

        $this->actingAs($leader)->post('/maintenance/rss-sync', $this->payload(['apply_to_all_poldas' => true]))
            ->assertRedirect()->assertSessionHas('success', fn ($m) => str_contains($m, 'hanya Super Admin'));
        $this->assertSame(720, RssSyncSetting::forPolda(null)->fresh()->interval_minutes, 'jadwal nasional tidak berubah');

        $admin = User::factory()->create(['role' => 'super_admin_mabes', 'polda_id' => null]);
        $this->actingAs($admin)->post('/maintenance/rss-sync', $this->payload())->assertRedirect();
        $this->assertSame(30, RssSyncSetting::forPolda(null)->fresh()->interval_minutes);
    }

    public function test_page_shows_next_run_scheduler_status_and_local_time(): void
    {
        $this->travelTo(Carbon::parse('2026-11-07 02:59:00', 'UTC'));
        RssSyncSetting::forPolda(null)->update(['sync_mode' => 'interval', 'interval_minutes' => 720, 'is_active' => true, 'last_sync_at' => now()]);
        $admin = User::factory()->create(['role' => 'super_admin_mabes', 'polda_id' => null]);

        $this->actingAs($admin)->withSession(['active_polda_code' => 'SULBAR'])->get('/maintenance/rss-sync')->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('schedule.next_run_at', '07 Nov 2026, 22:59 WITA') // 14.59 UTC
                ->where('globalSetting.last_sync_at', '07 Nov 2026, 10:59 WITA') // 02.59 UTC, bukan "02:59 WITA"
                ->where('schedule.scheduler_active', false)
                ->where('schedule.can_edit', true)
            );
    }
}
