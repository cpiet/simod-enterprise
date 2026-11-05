<?php

namespace Tests\Feature;

use App\Models\MediaIntelligenceReport;
use App\Models\Polda;
use App\Models\SecuritySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** Command Center daerah menampilkan ringkasan isu nasional (laporan Divhumas) secara baca-saja dan terbatas. */
class NationalBriefForRegionTest extends TestCase
{
    use RefreshDatabase;

    private function world(): array
    {
        $div = Polda::create(['code' => 'DIVHUMAS', 'name' => 'Divhumas Polri', 'region' => 'Nasional', 'timezone' => 'Asia/Jakarta', 'is_active' => true]);
        $sul = Polda::create(['code' => 'SULBAR', 'name' => 'Polda Sulbar', 'region' => 'x', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $oth = Polda::create(['code' => 'GORONTALO', 'name' => 'Polda Gorontalo', 'region' => 'y', 'timezone' => 'Asia/Makassar', 'is_active' => true]);
        $user = User::create(['polda_id' => $sul->id, 'name' => 'Kabid', 'username' => 'kb_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'polda_leader', 'is_active' => true]);

        foreach ([[$div, 'ISU NASIONAL X'], [$oth, 'ISU GORONTALO RAHASIA']] as [$p, $concern]) {
            MediaIntelligenceReport::create(['polda_id' => $p->id, 'report_date' => now()->toDateString(), 'title' => 't', 'top_concern' => $concern, 'executive_brief' => 'ringkasan ' . $concern, 'core_decision' => 'arahan ' . $concern]);
        }

        return [$div, $sul, $user];
    }

    public function test_region_sees_only_divhumas_brief_when_enabled(): void
    {
        [, , $user] = $this->world();

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('nationalBriefEnabled', true)
            ->where('nationalBrief.top_concern', 'ISU NASIONAL X')
            ->where('nationalBrief.core_decision', 'arahan ISU NASIONAL X')
            ->has('nationalBrief', 4));   // hanya 4 kunci: tidak membocorkan field lain
    }

    public function test_switch_off_hides_brief_and_is_audited(): void
    {
        [, , $user] = $this->world();
        SecuritySetting::current(null)->update(['share_national_brief' => false]);

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('nationalBriefEnabled', false)->where('nationalBrief', null));

        $this->actingAsSuperAdmin();
        $this->post('/security/national-brief', ['enabled' => true])->assertRedirect();
        $this->assertTrue((bool) SecuritySetting::current(null)->share_national_brief);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.national_brief']);
    }

    public function test_no_report_gives_null_and_operator_cannot_toggle(): void
    {
        [, , $user] = $this->world();
        MediaIntelligenceReport::query()->delete();

        $this->actingAs($user)->get('/')->assertInertia(fn (Assert $p) => $p
            ->where('nationalBriefEnabled', true)->where('nationalBrief', null));

        $op = User::create(['polda_id' => $user->polda_id, 'name' => 'Op', 'username' => 'op_' . uniqid(), 'email' => uniqid() . '@polri.go.id', 'password' => 'RahasiaUji#2026', 'role' => 'operator', 'is_active' => true]);
        $this->actingAs($op)->post('/security/national-brief', ['enabled' => false])->assertStatus(403);
    }
}
