<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\MediaArticle;
use App\Models\MediaFeed;
use App\Models\MediaFile;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EndToEndAuditStressTest extends TestCase
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
            ['username' => 'qa_auditor'],
            [
                'polda_id'     => $this->polda->id,
                'polres_id'    => $this->polres->id,
                'name'         => 'AKBP QA Auditor Presisi',
                'email'        => 'qa.auditor@polri.go.id',
                'password'     => bcrypt('auditor123'),
                'role'         => 'super_admin_mabes',
                'badge_number' => '12345678',
                'is_active'    => true,
            ]
        );
    }

    /**
     * Test Issue creation validation failure when required fields are empty.
     */
    public function test_issue_store_validation_empty_payload(): void
    {
        $this->actingAs($this->user);

        $response = $this->post('/issues', []);
        $response->assertSessionHasErrors(['polda_id', 'title', 'summary', 'category', 'priority']);
    }

    /**
     * Test Issue creation edge cases: empty polres string, extreme payload length.
     */
    public function test_issue_store_with_empty_polres_string_and_valid_data(): void
    {
        $this->actingAs($this->user);

        $title = 'Audit Insiden Kamtibmas Simulasi Wilayah Pesisir ' . uniqid();
        $response = $this->post('/issues', [
            'polda_id'      => $this->polda->id,
            'polres_id'     => '', // Empty string should be handled safely as null
            'title'         => $title,
            'summary'       => 'Deskripsi lengkap kronologi pengamanan wilayah pesisir terpadu.',
            'category'      => 'Harkamtibmas',
            'priority'      => 'medium',
            'sentiment'     => 'positif',
            'location_text' => 'Pelabuhan Simboro Mamuju',
        ]);

        $response->assertRedirect();
        $issue = Issue::where('title', $title)->first();
        $this->assertNotNull($issue);
        $this->assertNull($issue->polres_id);
    }

    /**
     * Test Return issue requires a non-empty reason string.
     */
    public function test_issue_return_requires_non_whitespace_reason(): void
    {
        $this->actingAs($this->user);

        $issue = Issue::create([
            'polda_id'    => $this->polda->id,
            'title'       => 'Isu Uji Pengembalian ' . uniqid(),
            'summary'     => 'Ringkasan laporan uji pengembalian revisi.',
            'category'    => 'Kriminalitas',
            'priority'    => 'low',
            'status'      => 'investigasi',
        ]);

        $res = $this->post("/issues/{$issue->id}/return", ['reason' => '   ']);
        $res->assertSessionHas('error');
        $issue->refresh();
        $this->assertEquals('investigasi', $issue->status);
    }

    /**
     * Test Resolve issue requires non-whitespace resolved_note.
     */
    public function test_issue_resolve_requires_non_whitespace_note(): void
    {
        $this->actingAs($this->user);

        $issue = Issue::create([
            'polda_id'    => $this->polda->id,
            'title'       => 'Isu Uji Penyelesaian Tuntas ' . uniqid(),
            'summary'     => 'Ringkasan laporan uji penyelesaian.',
            'category'    => 'Pelayanan Publik',
            'priority'    => 'medium',
            'status'      => 'investigasi',
        ]);

        $res = $this->post("/issues/{$issue->id}/resolve", ['resolved_note' => '']);
        $res->assertSessionHas('error');
        $issue->refresh();
        $this->assertNotEquals('selesai', $issue->status);

        $resValid = $this->post("/issues/{$issue->id}/resolve", ['resolved_note' => 'Penanganan tuntas secara humanis.']);
        $resValid->assertSessionHas('success');
        $issue->refresh();
        $this->assertEquals('selesai', $issue->status);
    }

    /**
     * Test Task dispatch creation and user association.
     */
    public function test_task_dispatch_creation_stores_created_by(): void
    {
        $this->actingAs($this->user);

        $res = $this->post('/tasks', [
            'polda_id'      => $this->polda->id,
            'title'         => 'Patroli Dialogis Presisi Menjelang Pilkada',
            'instructions'  => 'Lakukan monitoring dan dialog dengan tokoh masyarakat.',
            'assigned_unit' => 'Sat Samapta Polresta Mamuju',
            'priority'      => 'high',
        ]);

        $res->assertRedirect();
        $task = Task::where('title', 'Patroli Dialogis Presisi Menjelang Pilkada')->latest()->first();
        $this->assertNotNull($task);
        $this->assertEquals($this->user->id, $task->created_by);
        $this->assertEquals('in_progress', $task->status);
    }

    /**
     * Test Publication creation stores author_id.
     */
    public function test_publication_creation_stores_author_id(): void
    {
        $this->actingAs($this->user);

        $res = $this->post('/publications', [
            'polda_id'      => $this->polda->id,
            'channel'       => 'tribratanews',
            'title'         => 'Polresta Mamuju Gelar Jumat Curhat di Wilayah Pesisir',
            'content_draft' => 'Kegiatan rutin Jumat Curhat mendengarkan aspirasi warga nelayan.',
        ]);

        $res->assertRedirect();
        $pub = Publication::where('title', 'Polresta Mamuju Gelar Jumat Curhat di Wilayah Pesisir')->latest()->first();
        $this->assertNotNull($pub);
        $this->assertEquals($this->user->id, $pub->author_id);
        $this->assertEquals('published', $pub->approval_status);
    }

    /**
     * Test Media upload handles empty polres_id safely.
     */
    public function test_gallery_store_media_with_null_polres(): void
    {
        $this->actingAs($this->user);

        $res = $this->post('/gallery', [
            'polda_id'  => $this->polda->id,
            'polres_id' => '',
            'title'     => 'Foto Dokumentasi Humas Presisi',
            'file_type' => 'photo',
            'file_url'  => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=800',
        ]);

        $res->assertRedirect();
        $media = MediaFile::where('title', 'Foto Dokumentasi Humas Presisi')->latest()->first();
        $this->assertNotNull($media);
        $this->assertNull($media->polres_id);
    }

    /**
     * Test Personalia validation prevents duplicate username and email.
     */
    public function test_personalia_validation_prevents_duplicates(): void
    {
        $this->actingAs($this->user);

        $res = $this->post('/personalia', [
            'name'         => 'Duplicate Officer',
            'username'     => $this->user->username,
            'email'        => $this->user->email,
            'role'         => 'operator',
            'password'     => 'password123',
        ]);

        $res->assertSessionHasErrors(['username', 'email']);
    }

    /**
     * Test Security settings validation bounds.
     */
    public function test_security_settings_validation_bounds(): void
    {
        $this->actingAs($this->user);

        $res = $this->post('/security', [
            'enable_turnstile'        => true,
            'max_login_attempts'      => 0, // min is 1
            'lockout_minutes'         => 99999, // max is 1440
            'enable_pin'              => true,
            'pin_expiry_days'         => 500, // max is 365
            'session_timeout_minutes' => 2, // min is 5
            'private_media_storage'   => true,
        ]);

        $res->assertSessionHasErrors(['max_login_attempts', 'lockout_minutes', 'pin_expiry_days', 'session_timeout_minutes']);
    }

    /**
     * Test RBAC matrix update and audit log writes metadata.
     */
    public function test_roles_matrix_update_writes_proper_audit_log(): void
    {
        $this->actingAs($this->user);

        $matrix = [
            [
                'role_key'    => 'operator',
                'role_name'   => 'Operator Satwil',
                'description' => 'Petugas input laporan lapangan',
                'permissions' => ['dashboard_view', 'issues_view', 'issues_create'],
            ],
        ];

        $res = $this->post('/roles?polda=SULBAR', [
            'matrix' => $matrix,
        ]);

        $res->assertRedirect();

        $log = AuditLog::where('action', 'roles.matrix_update')->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertIsArray($log->metadata);
        $this->assertEquals(1, $log->metadata['roles_count']);
    }

    /**
     * Test Search and filter empty states return 200 OK without errors.
     */
    public function test_search_and_filters_empty_states_return_200(): void
    {
        $this->actingAs($this->user);

        $routes = [
            '/issues?q=NonExistentKeywordXYZ999&status=all&priority=all',
            '/social-intelligence?q=NonExistentKOLXYZ&platform=all&sentiment=all',
            '/verification-queue?tab=polres&priority=critical',
            '/tasks?status=completed',
            '/publications?channel=tiktok',
            '/gallery?format=video&q=NoMatchingMediaTitle',
            '/reports?category=Bencana&status=arsip',
            '/personalia?q=NonExistentName123&role=all',
            '/audit-logs?q=NonExistentAction&action=all',
        ];

        foreach ($routes as $route) {
            $res = $this->get($route);
            $res->assertStatus(200);
        }
    }
}

