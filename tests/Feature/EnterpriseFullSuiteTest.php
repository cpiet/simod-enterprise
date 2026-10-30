<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnterpriseFullSuiteTest extends TestCase
{
    use RefreshDatabase;

    protected Polda $polda;
    protected ?Polres $polres;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::firstOrCreate(
            ['code' => 'SULBAR'],
            ['name' => 'Polda Sulawesi Barat', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]
        );

        $this->polres = Polres::firstOrCreate(
            ['polda_id' => $this->polda->id, 'code' => 'MAMUJU'],
            ['name' => 'Polresta Mamuju', 'short_name' => 'Mamuju', 'is_active' => true]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'test_officer'],
            [
                'polda_id'     => $this->polda->id,
                'polres_id'    => $this->polres->id,
                'name'         => 'Bripka Test Pengujian',
                'email'        => 'test.officer@polri.go.id',
                'password'     => bcrypt('secret123'),
                'role'         => 'operator',
                'badge_number' => '88990011',
                'is_active'    => true,
            ]
        );
    }

    public function test_command_center_and_media_intelligence_routes_return_ok(): void
    {
        $this->actingAs($this->user);

        $r1 = $this->get('/?polda=SULBAR');
        $r1->assertStatus(200);

        $r2 = $this->get('/media-intelligence?polda=SULBAR');
        $r2->assertStatus(200);
    }

    public function test_issue_lifecycle_create_verify_clarify_task_resolve(): void
    {
        $this->actingAs($this->user);

        $uniqueTitle = 'Uji Otomatisasi Penanganan Isu Kamtibmas Jalur Arteri Mamuju ' . uniqid();

        // 1. Create Issue
        $createRes = $this->post('/issues?polda=SULBAR', [
            'polda_id'      => $this->polda->id,
            'polres_id'     => $this->polres->id,
            'title'         => $uniqueTitle,
            'summary'       => 'Pemeriksaan rutin kepolisian dan antisipasi potensi kerumunan balap liar.',
            'location_text' => 'Jl. Arteri Mamuju',
            'occurred_at'   => now()->toDateTimeString(),
            'category'      => 'Harkamtibmas',
            'priority'      => 'high',
            'sentiment'     => 'netral',
        ]);
        $createRes->assertRedirect();

        $issue = Issue::where('title', $uniqueTitle)->first();
        $this->assertNotNull($issue);
        $this->assertEquals('monitoring', $issue->status);
        $this->assertNotEmpty($issue->report_code);

        // 2. Verify Issue
        $verifyRes = $this->post("/issues/{$issue->id}/verify", [
            'target_status' => 'investigasi',
            'reason'        => 'Fakta di lapangan telah dikonfirmasi oleh piket patroli.',
        ]);
        $verifyRes->assertRedirect();
        $issue->refresh();
        $this->assertEquals('investigasi', $issue->status);

        // 3. Update Clarification (Fact Check)
        $clarifyRes = $this->post("/issues/{$issue->id}/clarify", [
            'is_hoax'                => false,
            'fact_check_result'      => 'Fakta terkonfirmasi aman terkendali.',
            'official_clarification' => 'Patroli presisi telah mengamankan lokasi tanpa insiden.',
        ]);
        $clarifyRes->assertRedirect();
        $issue->refresh();
        $this->assertEquals('klarifikasi', $issue->status);

        // 4. Create Task from Issue
        $taskRes = $this->post("/issues/{$issue->id}/task", [
            'title'         => 'Tingkatkan Patroli Blue Light di Simpang Arteri',
            'instructions'  => 'Laksanakan patroli stasioner dan dialogis bersama warga setempat.',
            'assigned_unit' => 'Satlantas Polresta Mamuju',
            'priority'      => 'high',
        ]);
        $taskRes->assertRedirect();
        $this->assertDatabaseHas('tasks', ['issue_id' => $issue->id, 'title' => 'Tingkatkan Patroli Blue Light di Simpang Arteri']);

        // 5. Create Publication Release from Issue
        $pubRes = $this->post("/issues/{$issue->id}/release", [
            'channel'       => 'instagram',
            'title'         => 'Siaran Pers Kondusifitas Jalur Arteri Mamuju',
            'content_draft' => 'Polresta Mamuju mengimbau masyarakat untuk menjaga ketertiban bersama.',
        ]);
        $pubRes->assertRedirect();
        $this->assertDatabaseHas('publications', ['issue_id' => $issue->id, 'channel' => 'instagram']);

        // 6. Resolve Issue
        $resolveRes = $this->post("/issues/{$issue->id}/resolve", [
            'resolved_note' => 'Penanganan tuntas, situasi kondusif dan opini publik 100% positif.',
        ]);
        $resolveRes->assertRedirect();
        $issue->refresh();
        $this->assertEquals('selesai', $issue->status);
    }

    public function test_tasks_dispatch_and_progress_updates(): void
    {
        $this->actingAs($this->user);

        $task = Task::create([
            'polda_id'      => $this->polda->id,
            'polres_id'     => $this->polres->id,
            'title'         => 'Penugasan Pemantauan Pilkada Wilayah Pesisir',
            'instructions'  => 'Koordinasi dengan Bawaslu dan KPUD setempat.',
            'assigned_unit' => 'Subbag Renmin Bidhumas',
            'priority'      => 'urgent',
            'status'        => 'in_progress',
        ]);

        $updateRes = $this->post("/tasks/{$task->id}/update", [
            'note'           => 'Koordinasi tahap awal telah dilaksanakan dengan lancar.',
            'mark_completed' => true,
        ]);
        $updateRes->assertRedirect();

        $task->refresh();
        $this->assertEquals('completed', $task->status);
        $this->assertDatabaseHas('task_updates', ['task_id' => $task->id, 'note' => 'Koordinasi tahap awal telah dilaksanakan dengan lancar.']);
    }

    public function test_gallery_media_upload_and_listing(): void
    {
        $this->actingAs($this->user);

        $res = $this->post('/gallery', [
            'polda_id'   => $this->polda->id,
            'polres_id'  => $this->polres->id,
            'title'      => 'Dokumentasi Apel Pengamanan Operasi Mantap Praja',
            'caption'    => 'Apel pengecekan kesiapan sarana prasarana.',
            'file_type'  => 'photo',
            'file_url'   => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=800',
            'is_private' => true,
        ]);
        $res->assertRedirect();

        $this->assertDatabaseHas('media_files', [
            'title'     => 'Dokumentasi Apel Pengamanan Operasi Mantap Praja',
            'file_type' => 'photo',
        ]);
    }

    public function test_personalia_registration_and_rbac(): void
    {
        $this->actingAs($this->user);

        $uid = uniqid();
        $username = 'andi_' . $uid;
        $email = "andi_{$uid}@polri.go.id";

        $res = $this->post('/personalia', [
            'polda_id'     => $this->polda->id,
            'polres_id'    => $this->polres->id,
            'name'         => 'Bripka Andi Sulistyo, S.H.',
            'username'     => $username,
            'email'        => $email,
            'badge_number' => '8909' . rand(1000, 9999),
            'role'         => 'operator',
            'phone'        => '081234567888',
            'password'     => 'PolriPresisi#2026',
        ]);
        $res->assertRedirect();

        $this->assertDatabaseHas('users', [
            'username' => $username,
            'email'    => $email,
        ]);
    }

    public function test_reports_and_csv_export_stream(): void
    {
        $this->actingAs($this->user);

        $r1 = $this->get('/reports?polda=SULBAR');
        $r1->assertStatus(200);

        $r2 = $this->get('/reports/export/csv?polda=SULBAR');
        $r2->assertStatus(200);
        $this->assertTrue(str_contains($r2->headers->get('content-type'), 'text/csv'));
    }

    public function test_all_system_administration_modules_return_200(): void
    {
        $this->actingAs($this->user);

        $routes = [
            '/verification-queue',
            '/gallery',
            '/tasks',
            '/publications',
            '/personalia',
            '/audit-logs',
            '/profile',
            '/settings/polda',
            '/security',
            '/roles',
            '/settings/mail',
            '/database/backup',
            '/maintenance',
        ];

        foreach ($routes as $route) {
            $res = $this->get($route . '?polda=SULBAR');
            $res->assertStatus(200);
        }
    }

    public function test_multi_tenant_crud_operations(): void
    {
        $this->actingAs($this->user);

        // 1. Update Polda
        $updRes = $this->post('/settings/polda/update', [
            'code'             => 'SULBAR',
            'name'             => 'Polda Sulawesi Barat',
            'region'           => 'Sulawesi Barat',
            'timezone'         => 'Asia/Makassar',
            'kapolda_name'     => 'Irjen Pol. Drs. R. Adang Ginanjar S., M.M.',
            'kabid_humas_name' => 'Kombes Pol. Slamet Wahyudi, S.I.K., M.H.',
        ]);
        $updRes->assertRedirect();
        $this->assertDatabaseHas('poldas', ['code' => 'SULBAR', 'kapolda_name' => 'Irjen Pol. Drs. R. Adang Ginanjar S., M.M.']);

        // 2. Create New Tenant Polda (Mabes Level)
        $code = 'PAPUA_' . rand(100, 999);
        $createPoldaRes = $this->post('/settings/polda/create', [
            'code'             => $code,
            'name'             => 'Polda Papua Barat',
            'region'           => 'Papua Barat',
            'timezone'         => 'Asia/Jayapura',
            'kapolda_name'     => 'Irjen Pol. Johnny Eddizon Isir',
            'kabid_humas_name' => 'Kombes Pol. Ongky Isgunawan',
        ]);
        $createPoldaRes->assertRedirect();
        $this->assertDatabaseHas('poldas', ['code' => $code, 'timezone' => 'Asia/Jayapura']);

        // 3. Create New Polres under Polda
        $polda = Polda::where('code', $code)->first();
        $createPolresRes = $this->post('/settings/polres/create', [
            'polda_id'   => $polda->id,
            'code'       => 'MANOKWARI',
            'name'       => 'Polresta Manokwari',
            'short_name' => 'Manokwari',
            'tag_desc'   => 'Ibukota Provinsi Papua Barat',
            'latitude'   => -0.8615,
            'longitude'  => 134.0620,
        ]);
        $createPolresRes->assertRedirect();
        $this->assertDatabaseHas('polres', ['polda_id' => $polda->id, 'code' => 'MANOKWARI']);
    }
}
