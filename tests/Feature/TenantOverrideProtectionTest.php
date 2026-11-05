<?php

namespace Tests\Feature;

use App\Models\MediaTopic;
use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Personel kewilayahan tidak boleh mengalihkan tenant lewat input klien (polda_id / polda_code / ?polda=).
 */
class TenantOverrideProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Polda $sulbar;
    private Polda $gorontalo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sulbar = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $this->gorontalo = Polda::create(['code' => 'GORONTALO', 'name' => 'Polda Gorontalo', 'region' => 'Gorontalo', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
    }

    private function user(string $role, ?int $poldaId): User
    {
        return User::create([
            'polda_id'  => $poldaId,
            'name'      => 'Uji ' . $role,
            'username'  => 'ovr_' . $role . '_' . uniqid(),
            'email'     => uniqid() . '@polri.go.id',
            'password'  => 'RahasiaUji#2026',
            'role'      => $role,
            'is_active' => true,
        ]);
    }

    public function test_regional_user_cannot_create_topic_for_another_polda(): void
    {
        $leader = $this->user('polda_leader', $this->sulbar->id);

        $this->actingAs($leader)->post('/media-intelligence/topics', [
            'title'      => 'Topik Uji Lintas Polda',
            'keywords'   => 'uji, lintas',
            'polda_code' => 'GORONTALO', // upaya tampering
        ])->assertRedirect();

        $topic = MediaTopic::where('title', 'Topik Uji Lintas Polda')->first();
        $this->assertNotNull($topic);
        $this->assertSame($this->sulbar->id, (int) $topic->polda_id, 'Topik harus tetap milik Polda pengguna.');
    }

    public function test_super_admin_may_still_choose_target_polda_for_topic(): void
    {
        $admin = $this->user('super_admin_mabes', null);

        $this->actingAs($admin)->post('/media-intelligence/topics', [
            'title'      => 'Topik Pilihan Admin',
            'keywords'   => 'admin',
            'polda_code' => 'GORONTALO',
        ])->assertRedirect();

        $this->assertSame($this->gorontalo->id, (int) MediaTopic::where('title', 'Topik Pilihan Admin')->value('polda_id'));
    }

    public function test_regional_user_cannot_reset_rbac_matrix_of_another_polda(): void
    {
        // Beri izin roles_matrix_manage kepada pimpinan Sulbar agar sampai ke controller.
        $permissions = array_fill_keys(array_column(RolePermission::defaultModules(), 'key'), true);
        RolePermission::updateOrCreate(
            ['polda_id' => $this->sulbar->id, 'role_key' => 'polda_leader'],
            ['role_name' => 'Pimpinan Polda', 'description' => 'uji', 'permissions' => $permissions]
        );
        $marker = ['marker' => true] + array_fill_keys(array_column(RolePermission::defaultModules(), 'key'), false);
        $other = RolePermission::create([
            'polda_id' => $this->gorontalo->id, 'role_key' => 'operator',
            'role_name' => 'Operator', 'description' => 'uji', 'permissions' => $marker,
        ]);

        $leader = $this->user('polda_leader', $this->sulbar->id);
        $this->actingAs($leader)->post('/roles/reset', ['polda_id' => $this->gorontalo->id]);

        $this->assertTrue((bool) ($other->fresh()->permissions['marker'] ?? false), 'Matriks Polda lain tidak boleh berubah.');
    }

    public function test_analyze_gemini_uses_session_tenant_not_query_param(): void
    {
        $leader = $this->user('polda_leader', $this->sulbar->id);

        // Tanpa kunci API -> 422, tetapi tenant yang dipakai harus Sulbar walaupun query meminta Gorontalo.
        $res = $this->actingAs($leader)->postJson('/media-intelligence/analyze-gemini?polda=GORONTALO');
        $res->assertStatus(422);
        $this->assertSame('SULBAR', session('active_polda_code'));
    }
}
