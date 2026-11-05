<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\User;
use App\Support\ScheduledTasks;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Modul Penjadwal di Audit Log: aktif/tidak aktif berdasarkan detak nyata, status tiap tugas, dan saran cron. */
class SchedulerStatusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->admin = User::create(['name' => 'Admin', 'username' => 'adm_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true]);
        Cache::flush();
    }

    private function page(): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->admin)->get('/audit-logs')->assertOk();
    }

    public function test_scheduler_is_inactive_without_heartbeat_and_suggests_how_to_enable(): void
    {
        $this->page()->assertInertia(fn (Assert $p) => $p
            ->where('scheduler.active', false)
            ->where('scheduler.last_tick', null)
            ->where('scheduler.tasks', fn ($t) => count($t) === 4 && collect($t)->every(fn ($x) => $x['status'] === 'never'))
            ->where('scheduler.suggestions', fn ($s) => collect($s)->pluck('key')->sort()->values()->all() === ['linux', 'windows']
                && str_contains(collect($s)->firstWhere('key', 'linux')['command'], 'schedule:run')
                && str_contains(collect($s)->firstWhere('key', 'linux')['command'], base_path()))
            ->where('readiness', fn ($c) => collect($c)->firstWhere('key', 'scheduler')['status'] === 'warn'));
    }

    public function test_recent_heartbeat_makes_scheduler_active(): void
    {
        Cache::put(ScheduledTasks::HEARTBEAT_KEY, time() - 30, 3600);

        $this->page()->assertInertia(fn (Assert $p) => $p
            ->where('scheduler.active', true)
            ->where('readiness', fn ($c) => collect($c)->firstWhere('key', 'scheduler')['status'] === 'ok'));
    }

    public function test_stale_heartbeat_means_inactive_again(): void
    {
        Cache::put(ScheduledTasks::HEARTBEAT_KEY, time() - 600, 3600);

        $this->page()->assertInertia(fn (Assert $p) => $p
            ->where('scheduler.active', false)
            ->where('scheduler.last_tick_ago', fn ($ago) => str_contains($ago, 'menit')));
    }

    public function test_each_task_reports_ok_late_failed_or_never(): void
    {
        Cache::put(ScheduledTasks::HEARTBEAT_KEY, time(), 3600);
        Cache::put('simod.sched.last.rss_sync', time() - 60, 3600);                    // tiap menit, baru berjalan -> ok
        Cache::put('simod.sched.last.db_backup', time() - 3 * 3600, 3600);             // tiap 10 menit, 3 jam lalu -> terlambat
        Cache::put('simod.sched.last.audit_prune', time() - 3600, 3600);               // harian, 1 jam lalu -> ok
        Cache::put('simod.sched.failed.audit_prune', time() - 3600, 3600);             // ...tetapi proses itu gagal
        // media_prune tidak pernah berjalan -> never

        $this->page()->assertInertia(fn (Assert $p) => $p->where('scheduler.tasks', function ($tasks) {
            $by = collect($tasks)->keyBy('key');

            return $by['rss_sync']['status'] === 'ok'
                && $by['db_backup']['status'] === 'late'
                && $by['audit_prune']['status'] === 'failed'
                && $by['media_prune']['status'] === 'never'
                && $by['rss_sync']['last_run_ago'] !== null
                && $by['media_prune']['last_run_ago'] === null;
        }));
    }

    public function test_disabled_task_is_reported_as_off(): void
    {
        config(['simod.media_retention_days' => 0]);

        $this->page()->assertInertia(fn (Assert $p) => $p->where('scheduler.tasks', fn ($t) => collect($t)->firstWhere('key', 'media_prune')['status'] === 'off'));
    }

    public function test_laravel_schedule_registers_heartbeat_and_every_task_from_the_single_definition(): void
    {
        $this->artisan('schedule:list')->assertSuccessful();
        $events = collect(app(Schedule::class)->events());

        $heartbeat = $events->first(fn ($e) => $e->description === 'simod-heartbeat');
        $this->assertNotNull($heartbeat, 'tugas detak harus terdaftar');
        $this->assertSame('* * * * *', $heartbeat->expression);

        foreach (ScheduledTasks::definitions() as $def) {
            $this->assertTrue($events->contains(fn ($e) => str_contains((string) ($e->command ?? ''), $def['command'])), "{$def['command']} harus terjadwal");
        }

        $this->assertNull(ScheduledTasks::lastTick());
        $heartbeat->run(app());
        $this->assertEqualsWithDelta(time(), ScheduledTasks::lastTick(), 3);
    }
}
