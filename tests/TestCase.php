<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Jeda coba ulang 503 Gemini dinolkan agar test tidak menunggu (produksi: 3 dtk lalu 6 dtk).
        config(['services.gemini.retry_delays' => [0, 0]]);
    }

    /**
     * Login sebagai Super Admin Mabes (tanpa kunci tenant) untuk pengujian rute yang kini wajib autentikasi.
     */
    protected function actingAsSuperAdmin(): static
    {
        $user = User::firstOrCreate(
            ['username' => 'test_superadmin'],
            [
                'name'      => 'Super Admin Test',
                'email'     => 'test.superadmin@polri.go.id',
                'password'  => bcrypt('secret123'),
                'role'      => 'super_admin_mabes',
                'is_active' => true,
            ]
        );

        return $this->actingAs($user);
    }
}
