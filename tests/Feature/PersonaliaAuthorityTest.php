<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\Polres;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersonaliaAuthorityTest extends TestCase
{
    use RefreshDatabase;

    protected User $superAdmin;
    protected User $poldaLeader;
    protected User $operator;
    protected Polda $polda;
    protected Polres $polres;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(\Database\Seeders\EnterpriseDatabaseSeeder::class);

        $this->polda = Polda::where('code', 'SULBAR')->first();
        $this->polres = Polres::where('polda_id', $this->polda->id)->first() ?? Polres::create([
            'polda_id' => $this->polda->id,
            'name'     => 'Polres Mamuju',
            'code'     => 'POLRES_MAMUJU',
        ]);

        $this->superAdmin = User::where('role', 'super_admin_mabes')->first() ?? User::factory()->create([
            'role' => 'super_admin_mabes',
        ]);

        $this->poldaLeader = User::where('role', 'polda_leader')->first() ?? User::factory()->create([
            'role'     => 'polda_leader',
            'polda_id' => $this->polda->id,
        ]);

        $this->operator = User::where('role', 'operator')->first() ?? User::factory()->create([
            'role'      => 'operator',
            'polda_id'  => $this->polda->id,
            'polres_id' => $this->polres->id,
        ]);
    }

    public function test_can_update_personnel_data_and_role(): void
    {
        $this->actingAs($this->superAdmin);

        $targetUser = User::create([
            'name'         => 'Bripka Target Edit',
            'username'     => 'target_edit_user',
            'email'        => 'target.edit@polri.go.id',
            'role'         => 'operator',
            'badge_number' => '12345678',
            'password'     => bcrypt('password123'),
            'polda_id'     => $this->polda->id,
            'is_active'    => true,
        ]);

        $response = $this->put("/personalia/{$targetUser->id}", [
            'name'         => 'Bripka Target Edit Updated, S.H.',
            'username'     => 'target_edit_user_updated',
            'email'        => 'target.updated@polri.go.id',
            'role'         => 'analis',
            'badge_number' => '87654321',
            'polda_id'     => $this->polda->id,
            'polres_id'    => null,
            'is_active'    => true,
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'id'           => $targetUser->id,
            'name'         => 'Bripka Target Edit Updated, S.H.',
            'username'     => 'target_edit_user_updated',
            'role'         => 'analis',
            'badge_number' => '87654321',
        ]);
    }

    public function test_can_toggle_personnel_status(): void
    {
        $this->actingAs($this->superAdmin);

        $targetUser = User::create([
            'name'         => 'Briptu Status Toggle',
            'username'     => 'target_toggle_user',
            'email'        => 'target.toggle@polri.go.id',
            'role'         => 'operator',
            'badge_number' => '33334444',
            'password'     => bcrypt('password123'),
            'polda_id'     => $this->polda->id,
            'is_active'    => true,
        ]);

        // Toggle to inactive
        $res1 = $this->post("/personalia/{$targetUser->id}/toggle-status");
        $res1->assertStatus(302);
        $this->assertFalse((bool) $targetUser->fresh()->is_active);

        // Toggle back to active
        $res2 = $this->post("/personalia/{$targetUser->id}/toggle-status");
        $res2->assertStatus(302);
        $this->assertTrue((bool) $targetUser->fresh()->is_active);
    }

    public function test_cannot_deactivate_self_or_master_accounts(): void
    {
        $this->actingAs($this->superAdmin);

        // Cannot deactivate oneself
        $selfRes = $this->post("/personalia/{$this->superAdmin->id}/toggle-status");
        $selfRes->assertSessionHas('error');
        $this->assertTrue((bool) $this->superAdmin->fresh()->is_active);

        // Cannot deactivate master account (ID 1)
        $masterRes = $this->post("/personalia/1/toggle-status");
        $masterRes->assertSessionHas('error');
    }

    public function test_can_reset_personnel_password(): void
    {
        $this->actingAs($this->superAdmin);

        $targetUser = User::create([
            'name'         => 'Bripda Password Reset',
            'username'     => 'target_pwd_user',
            'email'        => 'target.pwd@polri.go.id',
            'role'         => 'operator',
            'badge_number' => '55556666',
            'password'     => bcrypt('oldPassword123'),
            'polda_id'     => $this->polda->id,
            'is_active'    => true,
        ]);

        $res = $this->post("/personalia/{$targetUser->id}/reset-password", [
            'new_password' => 'Presisi2026Baru!',
        ]);

        $res->assertStatus(302);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Presisi2026Baru!', $targetUser->fresh()->password));
    }

    public function test_can_delete_personnel_with_protections(): void
    {
        $this->actingAs($this->superAdmin);

        $targetUser = User::create([
            'name'         => 'Briptu To Delete',
            'username'     => 'to_delete_user',
            'email'        => 'to.delete@polri.go.id',
            'role'         => 'operator',
            'badge_number' => '99998888',
            'password'     => bcrypt('password123'),
            'polda_id'     => $this->polda->id,
            'is_active'    => true,
        ]);

        $res = $this->delete("/personalia/{$targetUser->id}");
        $res->assertStatus(302);
        $this->assertDatabaseMissing('users', ['id' => $targetUser->id]);

        // Attempting to delete self is rejected
        $selfRes = $this->delete("/personalia/{$this->superAdmin->id}");
        $selfRes->assertSessionHas('error');

        // Attempting to delete master account is rejected
        $masterRes = $this->delete("/personalia/1");
        $masterRes->assertSessionHas('error');
    }

    public function test_regional_admin_cannot_promote_to_mabes_or_edit_mabes_personnel(): void
    {
        $this->actingAs($this->poldaLeader);

        // Regional admin cannot promote an operator to Kadiv Humas
        $targetUser = User::create([
            'name'         => 'Bripka Regional Staff',
            'username'     => 'regional_staff_user',
            'email'        => 'regional.staff@polri.go.id',
            'role'         => 'operator',
            'badge_number' => '77778888',
            'password'     => bcrypt('password123'),
            'polda_id'     => $this->polda->id,
            'is_active'    => true,
        ]);

        $promoteRes = $this->put("/personalia/{$targetUser->id}", [
            'name'         => 'Bripka Regional Staff',
            'username'     => 'regional_staff_user',
            'email'        => 'regional.staff@polri.go.id',
            'role'         => 'kadiv_humas',
            'badge_number' => '77778888',
            'polda_id'     => $this->polda->id,
        ]);

        $promoteRes->assertSessionHas('error');
        $this->assertNotEquals('kadiv_humas', $targetUser->fresh()->role);

        // Regional admin cannot edit a Mabes user
        $mabesUser = User::where('role', 'kadiv_humas')->first();
        if ($mabesUser) {
            $editMabesRes = $this->put("/personalia/{$mabesUser->id}", [
                'name'         => 'Tampered Kadiv',
                'username'     => $mabesUser->username,
                'email'        => $mabesUser->email,
                'role'         => 'operator',
            ]);
            $editMabesRes->assertSessionHas('error');
        }
    }
}

