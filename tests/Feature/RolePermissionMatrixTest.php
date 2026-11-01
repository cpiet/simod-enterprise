<?php

namespace Tests\Feature;

use App\Models\Polda;
use App\Models\RolePermission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    use RefreshDatabase;

    protected Polda $polda;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::firstOrCreate(
            ['code' => 'SULBAR'],
            ['name' => 'Polda Sulawesi Barat', 'region' => 'Sulawesi Barat', 'timezone' => 'Asia/Makassar', 'is_active' => true]
        );

        $this->user = User::firstOrCreate(
            ['username' => 'test_officer'],
            [
                'polda_id'  => $this->polda->id,
                'name'      => 'Bripka Test Pengujian',
                'email'     => 'test.officer@polri.go.id',
                'password'  => bcrypt('secret123'),
                'role'      => 'super_admin_mabes',
                'is_active' => true,
            ]
        );
    }

    public function test_roles_page_loads_with_matrix_and_modules(): void
    {
        $response = $this->actingAs($this->user)->get('/roles?polda=ALL');
        $response->assertStatus(200);

        $response->assertInertia(fn ($page) =>
            $page->component('Settings/Roles')
                ->where('selectedCode', 'ALL')
                ->has('modules', 21)
                ->has('roleMatrix', 6)
        );
    }

    public function test_update_roles_matrix_persists_in_database(): void
    {
        $divhumas = Polda::firstOrCreate(
            ['code' => 'DIVHUMAS'],
            ['name' => 'Divisi Humas Polri (Mabes)', 'region' => 'Nasional', 'is_active' => true]
        );

        $payload = [
            'polda_id' => $divhumas->id,
            'matrix'   => [
                [
                    'role_key'    => 'operator',
                    'role_name'   => 'Operator Satwil',
                    'description' => 'Operator testing',
                    'permissions' => [
                        'dashboard_view' => true,
                        'issues_create'  => true,
                        'database_backup'=> false,
                    ],
                ],
                [
                    'role_key'    => 'kadiv_humas',
                    'role_name'   => 'Kadivhumas Polri',
                    'description' => 'Pimpinan tertinggi',
                    'permissions' => [
                        'dashboard_view' => true,
                        'database_backup'=> true,
                    ],
                ],
            ],
        ];

        $response = $this->actingAs($this->user)->post('/roles?polda=ALL', $payload);
        $response->assertRedirect();

        $this->assertDatabaseHas('role_permissions', [
            'role_key' => 'operator',
            'polda_id' => $divhumas->id,
        ]);

        $record = RolePermission::where('role_key', 'operator')
            ->where('polda_id', $divhumas->id)
            ->first();

        $this->assertNotNull($record);
        $this->assertTrue($record->permissions['dashboard_view']);
        $this->assertFalse($record->permissions['database_backup']);
    }

    public function test_reset_roles_matrix(): void
    {
        $response = $this->actingAs($this->user)->post('/roles/reset?polda=ALL');
        $response->assertRedirect();

        // Database should be re-seeded with 6 default roles
        $this->assertDatabaseCount('role_permissions', 6);
    }

    public function test_user_has_permission_method(): void
    {
        $operator = new User(['role' => 'operator']);
        $this->assertTrue($operator->hasPermission('issues_create'));
        $this->assertFalse($operator->hasPermission('database_backup'));

        $kadiv = new User(['role' => 'kadiv_humas']);
        $this->assertTrue($kadiv->hasPermission('database_backup'));
        $this->assertTrue($kadiv->hasPermission('issues_create'));
    }
}
