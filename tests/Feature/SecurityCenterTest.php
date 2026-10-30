<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Polda;
use App\Models\SecuritySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_page_loads_with_real_database_settings(): void
    {
        $polda = Polda::create([
            'code'      => 'SULBAR',
            'name'      => 'Polda Sulawesi Barat',
            'region'    => 'Sulawesi Barat',
            'timezone'  => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $response = $this->get('/security?polda=SULBAR');
        $response->assertOk();

        // Verify record was created in database
        $this->assertDatabaseHas('security_settings', [
            'max_login_attempts' => 5,
            'lockout_minutes'    => 15,
        ]);
    }

    public function test_update_security_persists_to_database_and_writes_audit_log(): void
    {
        $polda = Polda::create([
            'code'      => 'SULBAR',
            'name'      => 'Polda Sulawesi Barat',
            'region'    => 'Sulawesi Barat',
            'timezone'  => 'Asia/Makassar',
            'is_active' => true,
        ]);

        $payload = [
            'enable_turnstile'        => true,
            'turnstile_site_key'      => '0x4AAAAAAAiH8s-SITEKEYTEST',
            'turnstile_secret_key'    => '0x4AAAAAAAiH8s-SECRETKEYTEST',
            'max_login_attempts'      => 3,
            'lockout_minutes'         => 30,
            'enable_pin'              => true,
            'pin_expiry_days'         => 60,
            'session_timeout_minutes' => 15,
            'private_media_storage'   => true,
            'ip_whitelist'            => "127.0.0.1\n10.0.0.1",
        ];

        $response = $this->post('/security?polda=SULBAR', $payload);
        $response->assertRedirect();

        // Assert database record updated
        $this->assertDatabaseHas('security_settings', [
            'max_login_attempts'      => 3,
            'lockout_minutes'         => 30,
            'session_timeout_minutes' => 15,
            'turnstile_site_key'      => '0x4AAAAAAAiH8s-SITEKEYTEST',
        ]);

        // Assert AuditLog created
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'security.update',
        ]);
    }
}
