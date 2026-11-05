<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Audit Log & Pusat Keamanan: data nyata, tenant aman, filter dinamis, ekspor, pembersihan manual/otomatis
 * dengan jejak yang tidak ikut terhapus.
 */
class AuditLogManagementTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;
    private User $admin;
    private User $regional;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code) => Polda::create(['code' => $code, 'name' => "Polda {$code}", 'region' => $code, 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->sulbar = $mk('SULBAR');
        $this->gorontalo = $mk('GORONTALO');
        $this->admin = User::create(['name' => 'Admin Mabes', 'username' => 'adm_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'super_admin_mabes', 'is_active' => true]);
        $this->regional = User::create(['polda_id' => $this->sulbar->id, 'name' => 'Admin Sulbar', 'username' => 'rg_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);
        RolePermission::create(['polda_id' => $this->sulbar->id, 'role_key' => 'polda_leader', 'role_name' => 'Pimpinan', 'permissions' => ['dashboard_view' => true, 'security_center' => true]]);
    }

    private function log(string $action, ?Polda $polda = null, ?int $daysAgo = null, array $meta = [], ?User $user = null): AuditLog
    {
        return AuditLog::create([
            'polda_id' => $polda?->id, 'user_id' => $user?->id, 'action' => $action, 'metadata' => $meta,
            'ip_address' => '10.0.0.1', 'user_agent' => 'UJI', 'created_at' => now()->subDays($daysAgo ?? 0),
        ]);
    }

    public function test_page_shows_real_numbers_and_only_own_tenant_for_regional_admin(): void
    {
        $this->log('auth.login_failed', $this->sulbar, 0, ['username' => 'penyusup']);
        $this->log('issue.create', $this->sulbar, 0);
        $this->log('auth.login_failed', $this->gorontalo, 0);   // milik Polda lain
        $this->log('issue.create', null, 0);                    // tingkat sistem

        $this->actingAs($this->regional)->get('/audit-logs')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('stats.total', 2)
            ->where('stats.failed24', 1)
            ->where('stats.failed_login24', 1)
            ->where('logs.total', 2)
            ->where('logs.data.0.polda', fn ($p) => $p === 'SULBAR'));
    }

    public function test_national_admin_sees_every_polda(): void
    {
        $this->log('auth.login', $this->sulbar);
        $this->log('auth.login', $this->gorontalo);
        $this->log('auth.login', null);

        $this->actingAs($this->admin)->post('/tenant/switch', ['code' => 'ALL']);
        $this->get('/audit-logs')->assertInertia(fn (Assert $page) => $page->where('stats.total', 3));
    }

    public function test_filters_are_dynamic_and_actually_filter(): void
    {
        $this->log('auth.login_failed', $this->sulbar, 0, ['username' => 'budi'], $this->regional);
        $this->log('auth.login', $this->sulbar, 0, [], $this->regional);
        $this->log('issue.create', $this->sulbar, 0, ['report_code' => 'R-1', 'title' => 'Banjir besar']);
        $this->log('database.backup', $this->sulbar, 40);

        $this->actingAs($this->admin)->post('/tenant/switch', ['code' => 'ALL']);

        $this->get('/audit-logs?hasil=gagal')->assertInertia(fn (Assert $p) => $p->where('logs.total', 1)->where('logs.data.0.label', 'Gagal masuk')->where('logs.data.0.failed', true));
        $this->get('/audit-logs?hasil=berhasil')->assertInertia(fn (Assert $p) => $p->where('logs.total', 3));
        $this->get('/audit-logs?kategori=auth')->assertInertia(fn (Assert $p) => $p->where('logs.total', 2));
        $this->get('/audit-logs?aksi=issue.create')->assertInertia(fn (Assert $p) => $p->where('logs.total', 1)->where('logs.data.0.detail', '[R-1] Banjir besar'));
        $this->get('/audit-logs?q=banjir')->assertInertia(fn (Assert $p) => $p->where('logs.total', 1));
        $this->get('/audit-logs?q=Admin+Sulbar')->assertInertia(fn (Assert $p) => $p->where('logs.total', 2));
        $this->get('/audit-logs?from=' . now()->subDays(1)->format('Y-m-d'))->assertInertia(fn (Assert $p) => $p->where('logs.total', 3));
        $this->get('/audit-logs?user=' . $this->regional->id)->assertInertia(fn (Assert $p) => $p->where('logs.total', 2));

        // Pilihan filter berasal dari isi log, bukan daftar tetap.
        $this->get('/audit-logs')->assertInertia(fn (Assert $p) => $p
            ->where('facets.categories', fn ($c) => collect($c)->pluck('key')->sort()->values()->all() === ['auth', 'isu', 'sistem'])
            ->where('facets.actions', fn ($a) => collect($a)->pluck('code')->contains('database.backup') && ! collect($a)->pluck('code')->contains('menu.create')));
    }

    public function test_sensitive_metadata_is_masked_on_screen_and_in_export(): void
    {
        $this->log('security.update', $this->sulbar, 0, ['password' => 'RAHASIA123', 'api_key' => 'AIzaSECRET', 'note' => 'ok']);
        $this->log('menu.update', $this->sulbar, 0, ['summary' => '=HYPERLINK("http://x")']);

        $this->actingAs($this->admin)->post('/tenant/switch', ['code' => 'ALL']);
        $this->get('/audit-logs?aksi=security.update')->assertInertia(fn (Assert $p) => $p
            ->where('logs.data.0.meta.password', '[disamarkan]')->where('logs.data.0.meta.api_key', '[disamarkan]')->where('logs.data.0.meta.note', 'ok'));

        $csv = $this->get('/audit-logs/export')->assertOk()->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $csv);
        $this->assertStringContainsString('Waktu,"Zona waktu"', $csv);
        $this->assertStringNotContainsString('RAHASIA123', $csv);
        $this->assertStringNotContainsString('AIzaSECRET', $csv);
        $this->assertStringContainsString("'=HYPERLINK", $csv); // sel berawalan = dinetralkan
    }

    public function test_export_respects_filters_and_tenant(): void
    {
        $this->log('auth.login_failed', $this->sulbar);
        $this->log('auth.login', $this->sulbar);
        $this->log('auth.login_failed', $this->gorontalo);

        $csv = $this->actingAs($this->regional)->get('/audit-logs/export?hasil=gagal')->assertOk()->streamedContent();
        $this->assertSame(1, substr_count($csv, 'auth.login_failed'));
        $this->assertStringNotContainsString('GORONTALO', $csv);
    }

    public function test_purge_older_than_keeps_recent_and_protected_logs_and_records_itself(): void
    {
        $this->log('auth.login', $this->sulbar, 200);
        $this->log('issue.create', $this->sulbar, 100);
        $this->log('issue.create', $this->sulbar, 5);
        $this->log('audit.purged', $this->sulbar, 300, ['summary' => 'pembersihan lama']);
        $this->log('auth.login', $this->gorontalo, 200);   // Polda lain: tidak boleh tersentuh

        $this->actingAs($this->regional)->getJson('/audit-logs/purge-preview?mode=older_than&days=90')->assertOk()->assertJson(['count' => 2]);
        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'older_than', 'days' => 90])->assertOk()->assertJson(['deleted' => 2]);

        $this->assertSame(1, AuditLog::where('action', 'issue.create')->count());
        $this->assertSame(1, AuditLog::where('polda_id', $this->gorontalo->id)->count());
        $this->assertSame(2, AuditLog::where('action', 'audit.purged')->count(), 'catatan lama dilindungi + catatan baru');
    }

    public function test_purge_always_leaves_a_trace_and_protected_entries_survive(): void
    {
        $this->log('auth.login', $this->sulbar, 10);
        $this->log('audit.purged', $this->sulbar, 400, ['summary' => 'jejak lama']);

        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'all', 'confirm' => 'hapus'])->assertOk()->assertJson(['deleted' => 1]);

        $this->assertSame(0, AuditLog::where('action', 'auth.login')->count());
        $this->assertSame(2, AuditLog::where('action', 'audit.purged')->count());   // jejak lama + jejak baru
        $trace = AuditLog::where('action', 'audit.purged')->latest('id')->first();
        $this->assertSame($this->regional->id, $trace->user_id);
        $this->assertSame(1, $trace->metadata['deleted']);
        $this->assertSame('all', $trace->metadata['mode']);
    }

    public function test_destructive_modes_require_typing_hapus(): void
    {
        $this->log('auth.login', $this->sulbar, 1);

        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'all'])->assertStatus(422);
        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'all', 'confirm' => 'salah'])->assertStatus(422);
        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'filtered', 'q' => 'x'])->assertStatus(422);
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());

        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'older_than', 'days' => 13])->assertStatus(422);  // hari tidak valid
    }

    public function test_filtered_purge_only_deletes_what_the_filter_shows(): void
    {
        $this->log('auth.login_failed', $this->sulbar);
        $this->log('auth.login_failed', $this->sulbar);
        $this->log('auth.login', $this->sulbar);

        $this->actingAs($this->regional)->getJson('/audit-logs/purge-preview?mode=filtered&hasil=gagal')->assertJson(['count' => 2]);
        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'filtered', 'hasil' => 'gagal', 'confirm' => 'HAPUS'])->assertJson(['deleted' => 2]);

        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());
        $this->assertSame(0, AuditLog::where('action', 'auth.login_failed')->count());
    }

    public function test_security_scope_purge_leaves_non_security_logs(): void
    {
        $this->log('auth.login', $this->sulbar, 3);
        $this->log('roles.matrix_update', $this->sulbar, 3);
        $this->log('issue.create', $this->sulbar, 3);
        $this->log('database.backup', $this->sulbar, 3);

        $this->actingAs($this->regional)->postJson('/audit-logs/purge', ['mode' => 'all', 'scope' => 'security', 'confirm' => 'HAPUS'])->assertJson(['deleted' => 2]);

        $this->assertSame(['database.backup', 'issue.create'], AuditLog::whereNotIn('action', AuditLog::PROTECTED_ACTIONS)->orderBy('action')->pluck('action')->all());
    }

    public function test_user_without_permission_cannot_view_or_purge(): void
    {
        $operator = User::create(['polda_id' => $this->sulbar->id, 'name' => 'Op', 'username' => 'op_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'operator', 'is_active' => true]);
        $this->log('auth.login', $this->sulbar);

        $this->actingAs($operator)->get('/audit-logs')->assertForbidden();
        $this->actingAs($operator)->postJson('/audit-logs/purge', ['mode' => 'older_than', 'days' => 7])->assertForbidden();
        $this->actingAs($operator)->get('/audit-logs/export')->assertForbidden();
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());
    }

    public function test_retention_setting_validates_and_auto_prune_command_respects_it(): void
    {
        $this->actingAs($this->admin)->post('/audit-logs/retention', ['days' => 45])->assertSessionHasErrors('days');
        $this->actingAs($this->admin)->post('/audit-logs/retention', ['days' => 30])->assertRedirect();
        $this->assertSame(30, (int) SecuritySetting::current(null)->audit_retention_days);
        $this->assertSame(1, AuditLog::where('action', 'audit.retention')->count());

        $this->log('auth.login', $this->sulbar, 60);
        $this->log('auth.login', $this->sulbar, 5);
        $this->log('audit.purged', $this->sulbar, 500);

        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(1, AuditLog::where('action', 'auth.login')->count());
        $this->assertSame(1, AuditLog::where('action', 'audit.auto_pruned')->count());
        $this->assertSame(1, AuditLog::where('action', 'audit.purged')->count());

        SecuritySetting::current(null)->update(['audit_retention_days' => 0]);
        $this->log('auth.login', $this->sulbar, 900);
        $this->artisan('audit:prune')->assertSuccessful();
        $this->assertSame(2, AuditLog::where('action', 'auth.login')->count(), 'Retensi 0 = tidak memangkas');
    }

    public function test_security_center_activity_is_scoped_filterable_and_real(): void
    {
        $this->log('auth.login_failed', $this->sulbar, 0, ['username' => 'x']);
        $this->log('auth.login', $this->sulbar, 0, [], $this->regional);
        $this->log('security.update', $this->sulbar, 0);
        $this->log('auth.login_failed', $this->gorontalo, 0);
        $this->log('issue.create', $this->sulbar, 0);   // bukan peristiwa keamanan

        $this->actingAs($this->regional)->get('/security')->assertOk()->assertInertia(fn (Assert $p) => $p
            ->where('securityStats.total', 3)->where('securityStats.failed24', 1)->where('securityStats.login24', 1)->where('securityStats.changes24', 1)
            ->has('securityLogs', 3));

        $this->get('/security?tampil=gagal')->assertInertia(fn (Assert $p) => $p->has('securityLogs', 1)->where('securityLogs.0.label', 'Gagal masuk')->where('securityView.matching', 1));
        $this->get('/security?tampil=perubahan')->assertInertia(fn (Assert $p) => $p->has('securityLogs', 1)->where('securityLogs.0.label', 'Ubah pengaturan keamanan'));
    }

    public function test_server_health_and_readiness_are_measured_not_hard_coded(): void
    {
        $this->actingAs($this->admin)->get('/audit-logs')->assertInertia(fn (Assert $p) => $p
            ->where('serverHealth.database.ok', true)
            ->where('serverHealth.database.driver', 'sqlite')
            ->where('serverHealth.database.latency_ms', fn ($ms) => is_numeric($ms) && $ms < 1000)
            ->where('serverHealth.php_version', PHP_VERSION)
            ->where('readiness', function ($checks) {
                $by = collect($checks)->keyBy('key');

                return $by->has(['https', 'debug', 'env', 'writable', 'extensions', 'scheduler', 'backup'])
                    && $by['scheduler']['status'] === 'warn'    // belum ada aktivitas terjadwal pada basis data uji
                    && $by['backup']['status'] === 'warn'
                    && $by['env']['detail'] === 'Saat ini: testing.';
            }));
    }
}
