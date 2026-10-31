<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\BackupRecord;
use App\Models\BackupSetting;
use App\Models\Polda;
use App\Models\User;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DatabaseBackupTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Polda $polda;

    protected function setUp(): void
    {
        parent::setUp();

        $this->polda = Polda::create([
            'name'       => 'Polda Sulawesi Barat',
            'code'       => 'SULBAR',
            'is_active'  => true,
            'slug'       => 'sulbar',
            'alias'      => 'Sulbar',
            'latitude'   => -2.677,
            'longitude'  => 118.889,
            'timezone'   => 'Asia/Makassar',
        ]);

        $this->user = User::factory()->create([
            'role'      => 'superadmin',
            'polda_id'  => $this->polda->id,
            'is_active' => true,
        ]);

        $this->actingAs($this->user);
    }

    protected function tearDown(): void
    {
        // Bersihkan berkas tes jika ada
        $backupDir = storage_path('app/backups');
        if (File::isDirectory($backupDir)) {
            $files = File::files($backupDir);
            foreach ($files as $file) {
                if (str_starts_with($file->getFilename(), 'simod_enterprise_test_')) {
                    File::delete($file->getRealPath());
                }
            }
        }

        parent::tearDown();
    }

    public function test_backup_page_loads_with_real_database_stats(): void
    {
        $response = $this->get('/database/backup?polda=SULBAR');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Settings/Backup')
            ->has('stats')
            ->has('setting')
            ->has('backups')
            ->has('auditLogs')
            ->where('stats.table_count', fn ($val) => $val >= 0)
        );
    }

    public function test_create_manual_backup_creates_file_and_record(): void
    {
        $response = $this->post('/database/backup/create');

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $record = BackupRecord::latest('id')->first();
        $this->assertNotNull($record);
        $this->assertEquals('manual', $record->backup_type);
        $this->assertEquals('valid', $record->status);

        $filePath = storage_path('app/' . $record->filepath);
        $this->assertTrue(File::exists($filePath));

        // Audit Log tercatat
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'database.backup',
        ]);

        // Cleanup
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }

    public function test_update_backup_schedule_settings_persists(): void
    {
        $response = $this->post('/database/backup/settings', [
            'is_auto_backup_enabled' => true,
            'schedule_type'          => 'every_6_hours',
            'backup_time'            => '03:30',
            'retention_days'         => 30,
            'gzip_compression'       => true,
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $setting = BackupSetting::current();
        $this->assertTrue($setting->is_auto_backup_enabled);
        $this->assertEquals('every_6_hours', $setting->schedule_type);
        $this->assertEquals('03:30', $setting->backup_time);
        $this->assertEquals(30, $setting->retention_days);
        $this->assertTrue($setting->gzip_compression);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'database.settings',
        ]);
    }

    public function test_download_backup_returns_file_response(): void
    {
        $service = new DatabaseBackupService();
        $record = $service->createBackup('manual', $this->user->id, false);

        $response = $this->get('/database/backup/download/' . $record->filename);

        $response->assertStatus(200);
        $response->assertHeader('content-disposition');

        // Cleanup
        $filePath = storage_path('app/' . $record->filepath);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }

    public function test_restore_requires_exact_confirmation_word(): void
    {
        $service = new DatabaseBackupService();
        $record = $service->createBackup('manual', $this->user->id, false);

        // Test 1: Kata konfirmasi salah
        $failResponse = $this->post('/database/backup/restore/' . $record->filename, [
            'confirm_text' => 'SALAH',
        ]);
        $failResponse->assertSessionHas('error');

        // Test 2: Kata konfirmasi benar 'PULIHKAN'
        $successResponse = $this->post('/database/backup/restore/' . $record->filename, [
            'confirm_text' => 'PULIHKAN',
        ]);
        $successResponse->assertSessionHas('success');

        // Cleanup
        $filePath = storage_path('app/' . $record->filepath);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }

    public function test_delete_backup_removes_file_and_record(): void
    {
        $service = new DatabaseBackupService();
        $record = $service->createBackup('manual', $this->user->id, false);

        $filePath = storage_path('app/' . $record->filepath);
        $this->assertTrue(File::exists($filePath));

        $response = $this->delete('/database/backup/delete/' . $record->filename);
        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('backup_records', [
            'id' => $record->id,
        ]);
        $this->assertFalse(File::exists($filePath));
    }

    public function test_artisan_db_backup_command_runs_successfully(): void
    {
        $exitCode = Artisan::call('db:backup', ['--force' => true]);
        $this->assertEquals(0, $exitCode);

        $record = BackupRecord::latest('id')->first();
        $this->assertNotNull($record);

        // Cleanup
        $filePath = storage_path('app/' . $record->filepath);
        if (File::exists($filePath)) {
            File::delete($filePath);
        }
    }
}

