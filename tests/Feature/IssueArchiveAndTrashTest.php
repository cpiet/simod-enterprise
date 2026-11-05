<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueStatusHistory;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Arsip, hapus (Sampah), pulihkan, hapus permanen, dan aksi massal pada Laporan/Isu — termasuk hak akses dan tenant.
 */
class IssueArchiveAndTrashTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;
    private User $verifier;
    private User $operator;
    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $mk = fn (string $code) => Polda::create(['code' => $code, 'name' => "Polda {$code}", 'region' => $code, 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->sulbar = $mk('SULBAR');
        $this->gorontalo = $mk('GORONTALO');
        $user = fn (string $role) => User::create(['polda_id' => $this->sulbar->id, 'name' => ucfirst($role), 'username' => $role . '_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => $role, 'is_active' => true]);
        $this->verifier = $user('verifikator');
        $this->operator = $user('operator');
    }

    private function issue(string $status = 'investigasi', ?Polda $polda = null, array $extra = []): Issue
    {
        $this->seq++;

        return Issue::create(array_merge([
            'polda_id' => ($polda ?? $this->sulbar)->id, 'title' => "Laporan uji {$this->seq}", 'summary' => 'Ringkasan.',
            'category' => 'Kriminalitas', 'priority' => 'medium', 'status' => $status,
        ], $extra));
    }

    public function test_archive_stores_previous_stage_and_restore_returns_to_it(): void
    {
        $issue = $this->issue('klarifikasi');

        $this->actingAs($this->verifier)->post("/issues/{$issue->id}/archive", ['reason' => 'Kasus ditutup'])->assertSessionHas('success');
        $issue->refresh();
        $this->assertSame('arsip', $issue->status);
        $this->assertSame('klarifikasi', $issue->status_before_archive);
        $this->assertNotNull($issue->archived_at);
        $this->assertSame('Kasus ditutup', IssueStatusHistory::where('issue_id', $issue->id)->latest('id')->value('reason'));
        $this->assertSame(1, AuditLog::where('action', 'issue.archive')->count());

        $this->post("/issues/{$issue->id}/restore")->assertSessionHas('success');
        $issue->refresh();
        $this->assertSame('klarifikasi', $issue->status);
        $this->assertNull($issue->archived_at);
        $this->assertSame(1, AuditLog::where('action', 'issue.restore')->count());
    }

    public function test_archived_issues_leave_the_all_tab_and_counts_are_accurate(): void
    {
        $this->issue('investigasi');
        $this->issue('selesai');
        $archived = $this->issue('arsip', null, ['status_before_archive' => 'selesai']);
        $trashed = $this->issue('investigasi');
        $trashed->delete();

        $this->actingAs($this->verifier)->get('/issues')->assertInertia(fn (Assert $p) => $p
            ->where('issues.total', 2)
            ->where('groupCounts.all', 2)->where('groupCounts.archived', 1)->where('groupCounts.trash', 1));

        $this->get('/issues?status=archived')->assertInertia(fn (Assert $p) => $p->where('issues.total', 1)->where('issues.data.0.id', $archived->id)->where('issues.data.0.can_restore', true)->where('issues.data.0.can_archive', false));
        $this->get('/issues?status=trash')->assertInertia(fn (Assert $p) => $p->where('issues.total', 1)->where('issues.data.0.id', $trashed->id)->where('issues.data.0.can_force_delete', true));
    }

    public function test_drafts_and_already_archived_cannot_be_archived(): void
    {
        $draft = $this->issue('draft');
        $archived = $this->issue('arsip');

        $this->actingAs($this->verifier)->post("/issues/{$draft->id}/archive")->assertSessionHas('error');
        $this->post("/issues/{$archived->id}/archive")->assertSessionHas('error');
        $this->assertSame('draft', $draft->fresh()->status);
    }

    public function test_delete_moves_to_trash_hides_everywhere_and_can_be_restored(): void
    {
        $issue = $this->issue('monitoring');
        $task = Task::create(['polda_id' => $this->sulbar->id, 'issue_id' => $issue->id, 'title' => 'Tugas terkait', 'assigned_unit' => 'Sat Reskrim', 'status' => 'pending', 'priority' => 'medium']);

        $this->actingAs($this->verifier)->delete("/issues/{$issue->id}", ['reason' => 'Duplikat'])->assertSessionHas('success');

        $this->assertSoftDeleted('issues', ['id' => $issue->id]);
        $this->assertSame($this->verifier->id, Issue::withTrashed()->find($issue->id)->deleted_by);
        $this->assertSame(0, Issue::count());
        $this->assertNotNull($task->fresh()->issue, 'Tugas tetap dapat membaca laporan di Sampah');
        $this->assertSame(1, AuditLog::where('action', 'issue.delete')->count());

        $this->post("/issues/{$issue->id}/restore")->assertSessionHas('success');
        $this->assertSame(1, Issue::count());
        $this->assertNull(Issue::find($issue->id)->deleted_by);
    }

    public function test_operator_can_delete_only_own_drafts_and_cannot_manage_otherwise(): void
    {
        $own = $this->issue('draft', null, ['created_by' => $this->operator->id]);
        $others = $this->issue('draft', null, ['created_by' => $this->verifier->id]);
        $active = $this->issue('investigasi', null, ['created_by' => $this->operator->id]);

        $this->actingAs($this->operator)->delete("/issues/{$others->id}")->assertForbidden();
        $this->delete("/issues/{$active->id}")->assertForbidden();
        $this->post("/issues/{$active->id}/archive")->assertForbidden();
        $this->post('/issues-bulk', ['action' => 'archive', 'ids' => [$active->id]])->assertForbidden();
        $this->delete("/issues/{$own->id}")->assertSessionHas('success');

        $this->assertSoftDeleted('issues', ['id' => $own->id]);
        $this->assertNotSoftDeleted('issues', ['id' => $others->id]);
        $this->get('/issues?status=trash')->assertInertia(fn (Assert $p) => $p->where('filters.status', 'all')->where('canManage', false)->where('groupCounts.trash', 0));
    }

    public function test_cross_polda_access_is_blocked_for_single_and_bulk_actions(): void
    {
        $foreign = $this->issue('investigasi', $this->gorontalo);
        $own = $this->issue('investigasi');

        $this->actingAs($this->verifier)->post("/issues/{$foreign->id}/archive")->assertForbidden();
        $this->delete("/issues/{$foreign->id}")->assertForbidden();
        $this->post("/issues/{$foreign->id}/restore")->assertForbidden();

        $this->post('/issues-bulk', ['action' => 'archive', 'ids' => [$foreign->id, $own->id]])->assertSessionHas('success');
        $this->assertSame('investigasi', $foreign->fresh()->status);
        $this->assertSame('arsip', $own->fresh()->status);
    }

    public function test_bulk_actions_report_what_was_done_and_skipped(): void
    {
        $a = $this->issue('investigasi');
        $b = $this->issue('klarifikasi');
        $draft = $this->issue('draft');
        $foreign = $this->issue('investigasi', $this->gorontalo);

        $res = $this->actingAs($this->verifier)->post('/issues-bulk', ['action' => 'archive', 'ids' => [$a->id, $b->id, $draft->id, $foreign->id, 99999]]);
        $message = session('success');
        $this->assertStringContainsString('2 laporan diarsipkan', $message);
        $this->assertStringContainsString('di luar wilayah Anda', $message);
        $this->assertStringContainsString('masih draf', $message);
        $this->assertStringContainsString('tidak ditemukan', $message);
        $this->assertSame('arsip', $a->fresh()->status);

        $this->post('/issues-bulk', ['action' => 'restore', 'ids' => [$a->id, $b->id]])->assertSessionHas('success');
        $this->assertSame(['investigasi', 'klarifikasi'], [$a->fresh()->status, $b->fresh()->status]);

        $this->post('/issues-bulk', ['action' => 'priority', 'ids' => [$a->id, $b->id], 'priority' => 'critical'])->assertSessionHas('success');
        $this->assertSame(['critical', 'critical'], [$a->fresh()->priority, $b->fresh()->priority]);

        $this->post('/issues-bulk', ['action' => 'delete', 'ids' => [$a->id, $draft->id]])->assertSessionHas('success');
        $this->assertSoftDeleted('issues', ['id' => $a->id]);
        $this->assertSoftDeleted('issues', ['id' => $draft->id]);
        $this->assertSame(1, AuditLog::where('action', 'issue.bulk')->where('metadata->action', 'delete')->count());
    }

    public function test_bulk_validation_limits_and_unknown_actions(): void
    {
        $this->actingAs($this->verifier)->postJson('/issues-bulk', ['action' => 'explode', 'ids' => [1]])->assertStatus(422);
        $this->postJson('/issues-bulk', ['action' => 'archive', 'ids' => []])->assertStatus(422);
        $this->postJson('/issues-bulk', ['action' => 'archive', 'ids' => range(1, 101)])->assertStatus(422);
        $this->postJson('/issues-bulk', ['action' => 'priority', 'ids' => [1]])->assertStatus(422);
    }

    public function test_permanent_delete_needs_trash_and_confirmation_and_removes_evidence(): void
    {
        $issue = $this->issue('investigasi');
        MediaFile::create(['polda_id' => $this->sulbar->id, 'issue_id' => $issue->id, 'title' => 'Foto', 'file_type' => 'photo', 'file_path' => 'media/tidak-ada.jpg', 'file_url' => '/storage/media/tidak-ada.jpg']);

        // Belum di Sampah: tidak dapat dihapus permanen.
        $this->actingAs($this->verifier)->delete("/issues/{$issue->id}/force", ['confirm' => 'HAPUS'])->assertNotFound();
        $issue->delete();

        $this->delete("/issues/{$issue->id}/force")->assertSessionHas('error');
        $this->delete("/issues/{$issue->id}/force", ['confirm' => 'salah'])->assertSessionHas('error');
        $this->assertNotNull(Issue::withTrashed()->find($issue->id));

        $this->delete("/issues/{$issue->id}/force", ['confirm' => 'hapus'])->assertSessionHas('success');
        $this->assertNull(Issue::withTrashed()->find($issue->id));
        $this->assertSame(0, MediaFile::where('title', 'Foto')->count());
        $this->assertSame(1, AuditLog::where('action', 'issue.delete_permanent')->count());
    }

    public function test_trashed_issue_page_is_only_for_managers(): void
    {
        $issue = $this->issue('monitoring');
        $issue->delete();

        $this->actingAs($this->verifier)->get("/issues/{$issue->id}")->assertOk()->assertInertia(fn (Assert $p) => $p->where('manage.trashed', true)->where('manage.can_restore', true)->where('manage.can_force', true));
        $this->actingAs($this->operator)->get("/issues/{$issue->id}")->assertNotFound();
    }

    public function test_priority_sort_and_page_size(): void
    {
        $this->issue('investigasi', null, ['priority' => 'low']);
        $critical = $this->issue('investigasi', null, ['priority' => 'critical']);
        $this->issue('investigasi', null, ['priority' => 'medium']);
        foreach (range(1, 20) as $i) {
            $this->issue('investigasi');
        }

        $this->actingAs($this->verifier)->get('/issues?sort=prioritas&per_page=30')->assertInertia(fn (Assert $p) => $p
            ->where('issues.data.0.id', $critical->id)->where('issues.per_page', 30)->where('filters.sort', 'prioritas'));
        $this->get('/issues?per_page=7')->assertInertia(fn (Assert $p) => $p->where('issues.per_page', 15));   // nilai tak valid -> bawaan
    }
}
