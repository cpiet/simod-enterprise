<?php

namespace App\Console\Commands;

use App\Models\BackupSetting;
use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;
use Throwable;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'db:backup 
                            {--type=scheduled : Tipe snapshot (scheduled atau manual)}
                            {--force : Paksa backup tanpa memverifikasi jam jadwal}
                            {--retention= : Batas retensi hari untuk auto-pruning}';

    protected $description = 'Cadangkan basis data SIMOD Enterprise ke berkas snapshot SQL terkompresi';

    public function handle(DatabaseBackupService $backupService): int
    {
        $type = $this->option('type') ?: 'scheduled';
        $force = (bool) $this->option('force');
        $setting = BackupSetting::current();

        $this->info("=== SIMOD ENTERPRISE DATABASE BACKUP ENGINE ===");

        if (!$force && $type === 'scheduled') {
            if (!$setting->is_auto_backup_enabled) {
                $this->warn("Otomasi backup database sedang DINONAKTIFKAN di pengaturan.");
                return Command::SUCCESS;
            }

            if (!DatabaseBackupService::isScheduledToRunNow()) {
                $this->line("Belum saatnya menjalankan backup otomatis (Jadwal: {$setting->schedule_type} @ {$setting->backup_time}). Dilewati.");
                return Command::SUCCESS;
            }
        }

        $this->line("Memulai pencadangan basis data [Tipe: {$type}]...");

        try {
            $record = $backupService->createBackup($type, null);

            $this->info("✓ Sukses membuat cadangan basis data!");
            $this->table(
                ['Parameter', 'Nilai'],
                [
                    ['Nama Berkas', $record->filename],
                    ['Ukuran', $record->formatted_size],
                    ['Integritas SHA-256', substr($record->checksum_sha256, 0, 16) . '...'],
                    ['Durasi Pengerjaan', $record->duration_seconds . ' detik'],
                    ['Lokasi Penyimpanan', storage_path('app/' . $record->filepath)],
                ]
            );

            $retention = $this->option('retention') ? (int) $this->option('retention') : $setting->retention_days;
            if ($retention > 0) {
                $pruned = $backupService->pruneOldBackups($retention);
                if ($pruned > 0) {
                    $this->line("Dibersihkan {$pruned} berkas cadangan lama (retensi > {$retention} hari).");
                }
            }

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Gagal mencadangkan basis data: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}

