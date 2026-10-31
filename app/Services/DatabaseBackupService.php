<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\BackupRecord;
use App\Models\BackupSetting;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    protected string $disk = 'local';
    protected string $backupDir = 'backups';

    public function __construct()
    {
        $fullPath = storage_path('app/' . $this->backupDir);
        File::ensureDirectoryExists($fullPath);
    }

    /**
     * Cari path binary mysqldump.
     */
    public function findMysqldumpPath(): ?string
    {
        $explicit = config('database.connections.mysql.dump.dump_binary_path');
        if ($explicit && File::exists($explicit . '/mysqldump.exe')) {
            return $explicit . '/mysqldump.exe';
        }
        if ($explicit && File::exists($explicit . '/mysqldump')) {
            return $explicit . '/mysqldump';
        }

        // Jalur standar Laragon Windows
        $laragonPaths = [
            'D:/Foto/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe',
            'C:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe',
            'D:/laragon/bin/mysql/mysql-8.0.30-winx64/bin/mysqldump.exe',
        ];

        foreach ($laragonPaths as $lp) {
            if (File::exists($lp)) {
                return $lp;
            }
        }

        // Cari di folder laragon/bin/mysql secara dinamis jika terpasang di path lain
        $possibleRoots = ['D:/Foto/laragon/bin/mysql', 'C:/laragon/bin/mysql', 'D:/laragon/bin/mysql'];
        foreach ($possibleRoots as $root) {
            if (File::isDirectory($root)) {
                $files = glob($root . '/*/bin/mysqldump.exe');
                if (!empty($files) && File::exists($files[0])) {
                    return str_replace('\\', '/', $files[0]);
                }
            }
        }

        // Coba di sistem PATH
        $which = PHP_OS_FAMILY === 'Windows' ? 'where mysqldump' : 'which mysqldump';
        $output = @shell_exec($which);
        if ($output) {
            $lines = explode("\n", trim($output));
            if (!empty($lines[0]) && File::exists(trim($lines[0]))) {
                return trim($lines[0]);
            }
        }

        return null;
    }

    /**
     * Cari path binary mysql untuk restore.
     */
    public function findMysqlPath(): ?string
    {
        $mysqldump = $this->findMysqldumpPath();
        if ($mysqldump) {
            $mysql = str_replace('mysqldump.exe', 'mysql.exe', $mysqldump);
            if (File::exists($mysql)) {
                return $mysql;
            }
            $mysqlUnix = str_replace('mysqldump', 'mysql', $mysqldump);
            if (File::exists($mysqlUnix)) {
                return $mysqlUnix;
            }
        }

        $which = PHP_OS_FAMILY === 'Windows' ? 'where mysql' : 'which mysql';
        $output = @shell_exec($which);
        if ($output) {
            $lines = explode("\n", trim($output));
            if (!empty($lines[0]) && File::exists(trim($lines[0]))) {
                return trim($lines[0]);
            }
        }

        return null;
    }

    /**
     * Buat snapshot cadangan database.
     */
    public function createBackup(string $type = 'manual', ?int $userId = null, ?bool $forceGzip = null): BackupRecord
    {
        $startTime = microtime(true);
        $setting = BackupSetting::current();
        $useGzip = $forceGzip !== null ? $forceGzip : $setting->gzip_compression;

        $timestamp = Carbon::now()->format('Ymd_His');
        $baseName = 'simod_enterprise_' . $timestamp . '_' . strtolower($type);
        $rawSqlPath = storage_path('app/' . $this->backupDir . '/' . $baseName . '.sql');

        $driver = DB::getDriverName();
        $tablesCount = 0;

        try {
            if ($driver === 'mysql') {
                $mysqldump = $this->findMysqldumpPath();
                if ($mysqldump) {
                    $this->dumpWithMysqldump($mysqldump, $rawSqlPath);
                } else {
                    $this->dumpWithPhpPdo($rawSqlPath);
                }
                $tablesCount = count(DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'"));
            } else {
                // SQLite atau database lain (digunakan untuk testing otomatis)
                $this->dumpWithPhpPdo($rawSqlPath);
                $tablesCount = count(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"));
            }

            // Gzip compression jika diaktifkan
            $finalPath = $rawSqlPath;
            $finalFilename = $baseName . '.sql';

            if ($useGzip && function_exists('gzopen')) {
                $gzPath = $rawSqlPath . '.gz';
                $this->compressFileToGzip($rawSqlPath, $gzPath);
                if (File::exists($rawSqlPath)) {
                    File::delete($rawSqlPath);
                }
                $finalPath = $gzPath;
                $finalFilename = $baseName . '.sql.gz';
            }

            $sizeBytes = File::size($finalPath);
            $formattedSize = BackupRecord::formatBytes($sizeBytes);
            $checksum = hash_file('sha256', $finalPath);
            $duration = round(microtime(true) - $startTime, 2);

            $relativeStoragePath = $this->backupDir . '/' . $finalFilename;

            $record = BackupRecord::create([
                'filename'         => $finalFilename,
                'disk'             => $this->disk,
                'filepath'         => $relativeStoragePath,
                'size_bytes'       => $sizeBytes,
                'formatted_size'   => $formattedSize,
                'backup_type'      => $type,
                'status'           => 'valid',
                'checksum_sha256'  => $checksum,
                'tables_count'     => $tablesCount,
                'duration_seconds' => $duration,
                'created_by'       => $userId,
                'metadata'         => [
                    'driver'        => $driver,
                    'compressed'    => $useGzip,
                    'generated_by'  => $userId ? 'user_id:' . $userId : 'system_scheduler',
                    'original_date' => Carbon::now()->toIso8601String(),
                ],
            ]);

            // Update setting status
            $setting->update([
                'last_backup_at'         => Carbon::now(),
                'last_backup_status'     => 'success',
                'last_backup_filename'   => $finalFilename,
                'last_backup_size_bytes' => $sizeBytes,
                'last_backup_message'    => "Backup {$type} berhasil dibuat ({$formattedSize}, {$duration}s)",
            ]);

            // Catat ke Audit Log
            AuditLog::log('database.backup', BackupRecord::class, (string) $record->id, [
                'filename' => $finalFilename,
                'size'     => $formattedSize,
                'type'     => $type,
                'duration' => $duration . 's',
            ]);

            // Otomatis bersihkan backup lama sesuai retensi
            $this->pruneOldBackups($setting->retention_days);

            return $record;
        } catch (Exception $e) {
            Log::error('Database Backup Error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);

            $setting->update([
                'last_backup_status'  => 'failed',
                'last_backup_message' => 'Gagal membuat backup: ' . $e->getMessage(),
            ]);

            if (File::exists($rawSqlPath)) {
                File::delete($rawSqlPath);
            }

            throw $e;
        }
    }

    /**
     * Dump menggunakan binary mysqldump.
     */
    protected function dumpWithMysqldump(string $binaryPath, string $outputPath): void
    {
        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        $command = [
            $binaryPath,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
        ];

        if (!empty($password)) {
            $command[] = '--password=' . $password;
        }

        $command = array_merge($command, [
            '--single-transaction',
            '--quick',
            '--routines',
            '--triggers',
            '--skip-lock-tables',
            '--default-character-set=utf8mb4',
            $database,
            '--result-file=' . $outputPath,
        ]);

        $process = new Process($command);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('mysqldump failed: ' . $process->getErrorOutput());
        }
    }

    /**
     * Pure PHP PDO Table Exporter (Fallback Universal).
     */
    public function dumpWithPhpPdo(string $outputPath): void
    {
        $handle = fopen($outputPath, 'w');
        if (!$handle) {
            throw new Exception("Tidak dapat membuka file tujuan: {$outputPath}");
        }

        $driver = DB::getDriverName();
        $date = Carbon::now()->toDateTimeString();

        fwrite($handle, "-- ========================================================\n");
        fwrite($handle, "-- SIMOD Enterprise Database Snapshot\n");
        fwrite($handle, "-- Generated by: Pure PHP PDO Exporter\n");
        fwrite($handle, "-- Driver: {$driver}\n");
        fwrite($handle, "-- Date: {$date}\n");
        fwrite($handle, "-- ========================================================\n\n");

        if ($driver === 'mysql') {
            fwrite($handle, "SET FOREIGN_KEY_CHECKS=0;\n");
            fwrite($handle, "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n");
            fwrite($handle, "SET NAMES utf8mb4;\n\n");

            $tables = DB::select("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
            foreach ($tables as $tableObj) {
                $tableArr = (array) $tableObj;
                $tableName = reset($tableArr);

                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "-- Struktur Tabel `{$tableName}`\n");
                fwrite($handle, "-- --------------------------------------------------------\n");
                fwrite($handle, "DROP TABLE IF EXISTS `{$tableName}`;\n");

                $createResult = DB::select("SHOW CREATE TABLE `{$tableName}`");
                $createArr = (array) $createResult[0];
                $createSql = $createArr['Create Table'] ?? '';
                fwrite($handle, $createSql . ";\n\n");

                // Dump data
                $rows = DB::table($tableName)->get();
                if ($rows->isNotEmpty()) {
                    fwrite($handle, "INSERT INTO `{$tableName}` VALUES\n");
                    $rowLines = [];
                    foreach ($rows as $row) {
                        $values = [];
                        foreach ((array) $row as $val) {
                            if ($val === null) {
                                $values[] = 'NULL';
                            } elseif (is_numeric($val)) {
                                $values[] = $val;
                            } else {
                                $values[] = "'" . addcslashes((string) $val, "\0\n\r\t\032\\'") . "'";
                            }
                        }
                        $rowLines[] = "(" . implode(", ", $values) . ")";
                    }
                    fwrite($handle, implode(",\n", $rowLines) . ";\n\n");
                }
            }

            fwrite($handle, "SET FOREIGN_KEY_CHECKS=1;\n");
        } else {
            // SQLite Fallback
            fwrite($handle, "PRAGMA foreign_keys = OFF;\n\n");
            $tables = DB::select("SELECT name, sql FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            foreach ($tables as $t) {
                $tableName = $t->name;
                fwrite($handle, "DROP TABLE IF EXISTS \"{$tableName}\";\n");
                if (!empty($t->sql)) {
                    fwrite($handle, $t->sql . ";\n");
                }

                $rows = DB::table($tableName)->get();
                foreach ($rows as $row) {
                    $cols = array_keys((array) $row);
                    $vals = array_map(function ($v) {
                        return $v === null ? 'NULL' : "'" . addslashes((string) $v) . "'";
                    }, array_values((array) $row));

                    fwrite($handle, "INSERT INTO \"{$tableName}\" (\"" . implode('", "', $cols) . "\") VALUES (" . implode(", ", $vals) . ");\n");
                }
                fwrite($handle, "\n");
            }
            fwrite($handle, "PRAGMA foreign_keys = ON;\n");
        }

        fclose($handle);
    }

    /**
     * Restore database dari file snapshot.
     */
    public function restoreBackup(string $filename, ?int $userId = null): array
    {
        $startTime = microtime(true);
        $cleanFilename = basename($filename);
        $fullPath = storage_path('app/' . $this->backupDir . '/' . $cleanFilename);

        if (!File::exists($fullPath)) {
            throw new Exception("Berkas cadangan tidak ditemukan: {$cleanFilename}");
        }

        $isGzipped = str_ends_with($cleanFilename, '.gz');
        $sqlPath = $fullPath;
        $tempUncompressedPath = null;

        if ($isGzipped) {
            $tempUncompressedPath = storage_path('app/' . $this->backupDir . '/temp_' . time() . '.sql');
            $this->decompressGzipToFile($fullPath, $tempUncompressedPath);
            $sqlPath = $tempUncompressedPath;
        }

        try {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                $mysql = $this->findMysqlPath();
                if ($mysql) {
                    $this->restoreWithMysqlBinary($mysql, $sqlPath);
                } else {
                    $this->restoreWithPhpPdo($sqlPath);
                }
            } else {
                $this->restoreWithPhpPdo($sqlPath);
            }

            $duration = round(microtime(true) - $startTime, 2);

            // Perbarui record jika ada
            $record = BackupRecord::where('filename', $cleanFilename)->first();
            if ($record) {
                $record->update([
                    'status'   => 'restored',
                    'metadata' => array_merge($record->metadata ?? [], [
                        'last_restored_at' => Carbon::now()->toIso8601String(),
                        'restored_by'      => $userId,
                    ]),
                ]);
            }

            // Catat ke Audit Log
            AuditLog::log('database.restore', BackupRecord::class, (string) ($record?->id ?: 0), [
                'filename' => $cleanFilename,
                'duration' => $duration . 's',
            ]);

            return [
                'success'  => true,
                'filename' => $cleanFilename,
                'duration' => $duration,
                'message'  => "Basis data berhasil dipulihkan dari {$cleanFilename} dalam {$duration} detik.",
            ];
        } finally {
            if ($tempUncompressedPath && File::exists($tempUncompressedPath)) {
                File::delete($tempUncompressedPath);
            }
        }
    }

    /**
     * Restore dengan binary mysql.
     */
    protected function restoreWithMysqlBinary(string $binaryPath, string $sqlPath): void
    {
        $dbConfig = config('database.connections.mysql');
        $host = $dbConfig['host'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? '3306';
        $database = $dbConfig['database'];
        $username = $dbConfig['username'] ?? 'root';
        $password = $dbConfig['password'] ?? '';

        $command = [
            $binaryPath,
            '--host=' . $host,
            '--port=' . $port,
            '--user=' . $username,
            '--default-character-set=utf8mb4',
        ];

        if (!empty($password)) {
            $command[] = '--password=' . $password;
        }

        $command[] = $database;

        $process = new Process($command);
        $process->setInput(fopen($sqlPath, 'r'));
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new Exception('mysql restore failed: ' . $process->getErrorOutput());
        }
    }

    /**
     * Restore universal menggunakan PHP PDO (Statement Chunking).
     */
    public function restoreWithPhpPdo(string $sqlPath): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        } else {
            DB::statement('PRAGMA foreign_keys = OFF;');
        }

        $handle = fopen($sqlPath, 'r');
        if (!$handle) {
            throw new Exception("Tidak dapat membaca berkas SQL: {$sqlPath}");
        }

        $query = '';
        while (($line = fgets($handle)) !== false) {
            $trimmed = trim($line);
            if (empty($trimmed) || str_starts_with($trimmed, '--') || str_starts_with($trimmed, '/*')) {
                continue;
            }

            $query .= $line;
            if (str_ends_with($trimmed, ';')) {
                try {
                    DB::unprepared($query);
                } catch (Exception $e) {
                    // Abaikan comment / set yang tidak didukung jika ada
                }
                $query = '';
            }
        }

        if (!empty(trim($query))) {
            try {
                DB::unprepared($query);
            } catch (Exception $e) {
                // Abaikan
            }
        }

        fclose($handle);

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        } else {
            DB::statement('PRAGMA foreign_keys = ON;');
        }
    }

    /**
     * Hapus berkas backup.
     */
    public function deleteBackup(string $filename, ?int $userId = null): bool
    {
        $cleanFilename = basename($filename);
        $fullPath = storage_path('app/' . $this->backupDir . '/' . $cleanFilename);

        if (File::exists($fullPath)) {
            File::delete($fullPath);
        }

        $record = BackupRecord::where('filename', $cleanFilename)->first();
        if ($record) {
            $record->delete();
        }

        AuditLog::log('database.delete_backup', BackupRecord::class, (string) ($record?->id ?: 0), [
            'filename' => $cleanFilename,
            'user_id'  => $userId,
        ]);

        return true;
    }

    /**
     * Unggah berkas SQL eksternal dan langsung pulihkan / siapkan.
     */
    public function uploadAndRestore(UploadedFile $file, ?int $userId = null): array
    {
        $ext = strtolower($file->getClientOriginalExtension());
        $originalName = $file->getClientOriginalName();

        if (!in_array($ext, ['sql', 'gz'])) {
            throw new Exception("Ekstensi berkas tidak valid. Harap unggah berkas .sql atau .sql.gz");
        }

        $timestamp = Carbon::now()->format('Ymd_His');
        $safeName = 'uploaded_' . $timestamp . '_' . preg_replace('/[^a-zA-Z0-9_\.]/', '_', $originalName);
        $targetPath = storage_path('app/' . $this->backupDir . '/' . $safeName);

        $file->move(storage_path('app/' . $this->backupDir), $safeName);

        $size = File::size($targetPath);
        $checksum = hash_file('sha256', $targetPath);

        $record = BackupRecord::create([
            'filename'         => $safeName,
            'disk'             => $this->disk,
            'filepath'         => $this->backupDir . '/' . $safeName,
            'size_bytes'       => $size,
            'formatted_size'   => BackupRecord::formatBytes($size),
            'backup_type'      => 'uploaded',
            'status'           => 'valid',
            'checksum_sha256'  => $checksum,
            'tables_count'     => 0,
            'duration_seconds' => 0,
            'created_by'       => $userId,
            'metadata'         => [
                'original_filename' => $originalName,
                'uploaded_at'       => Carbon::now()->toIso8601String(),
            ],
        ]);

        // Langsung jalankan pemulihan
        $restoreResult = $this->restoreBackup($safeName, $userId);

        return [
            'success'        => true,
            'record'         => $record,
            'restore_result' => $restoreResult,
        ];
    }

    /**
     * Kompresi file SQL ke Gzip.
     */
    protected function compressFileToGzip(string $sourcePath, string $destPath): void
    {
        $fp = fopen($sourcePath, 'rb');
        $gz = gzopen($destPath, 'wb9');

        if (!$fp || !$gz) {
            throw new Exception("Gagal menginisialisasi kompresi Gzip.");
        }

        while (!feof($fp)) {
            gzwrite($gz, fread($fp, 1024 * 512));
        }

        fclose($fp);
        gzclose($gz);
    }

    /**
     * Ekstraksi Gzip ke file SQL sementara.
     */
    protected function decompressGzipToFile(string $sourcePath, string $destPath): void
    {
        $gz = gzopen($sourcePath, 'rb');
        $fp = fopen($destPath, 'wb');

        if (!$gz || !$fp) {
            throw new Exception("Gagal mengekstrak berkas Gzip.");
        }

        while (!gzeof($gz)) {
            fwrite($fp, gzread($gz, 1024 * 512));
        }

        gzclose($gz);
        fclose($fp);
    }

    /**
     * Bersihkan snapshot lama sesuai retensi hari.
     */
    public function pruneOldBackups(int $retentionDays): int
    {
        if ($retentionDays <= 0) {
            return 0;
        }

        $threshold = Carbon::now()->subDays($retentionDays);
        $oldRecords = BackupRecord::where('created_at', '<', $threshold)
            ->where('status', '!=', 'restored')
            ->get();

        $prunedCount = 0;
        foreach ($oldRecords as $rec) {
            $path = storage_path('app/' . $rec->filepath);
            if (File::exists($path)) {
                File::delete($path);
            }
            $rec->delete();
            $prunedCount++;
        }

        return $prunedCount;
    }

    /**
     * Sinkronisasikan berkas fisik di direktori backup dengan basis data.
     */
    public function syncDiskFilesWithDatabase(): void
    {
        $fullDir = storage_path('app/' . $this->backupDir);
        if (!File::isDirectory($fullDir)) {
            return;
        }

        $files = File::files($fullDir);
        foreach ($files as $file) {
            $filename = $file->getFilename();
            if (str_starts_with($filename, 'temp_') || str_starts_with($filename, '.')) {
                continue;
            }

            $exists = BackupRecord::where('filename', $filename)->exists();
            if (!$exists) {
                $size = $file->getSize();
                $type = str_contains($filename, '_scheduled') ? 'scheduled' : (str_contains($filename, 'uploaded') ? 'uploaded' : 'manual');
                BackupRecord::create([
                    'filename'         => $filename,
                    'disk'             => $this->disk,
                    'filepath'         => $this->backupDir . '/' . $filename,
                    'size_bytes'       => $size,
                    'formatted_size'   => BackupRecord::formatBytes($size),
                    'backup_type'      => $type,
                    'status'           => 'valid',
                    'checksum_sha256'  => hash_file('sha256', $file->getRealPath()),
                    'tables_count'     => 0,
                    'duration_seconds' => 0,
                    'created_at'       => Carbon::createFromTimestamp($file->getMTime()),
                ]);
            }
        }
    }

    /**
     * Hitung statistik basis data riil (Ukuran MB, Jumlah Tabel, Engine).
     */
    public function getDatabaseStats(): array
    {
        $driver = DB::getDriverName();
        $totalSizeBytes = 0;
        $tableCount = 0;
        $engine = 'MySQL / MariaDB';

        if ($driver === 'mysql') {
            $dbName = config('database.connections.mysql.database');
            $stats = DB::select("
                SELECT 
                    COUNT(*) as table_count,
                    COALESCE(SUM(data_length + index_length), 0) AS total_size
                FROM information_schema.TABLES
                WHERE table_schema = ?
            ", [$dbName]);

            if (!empty($stats)) {
                $totalSizeBytes = (int) $stats[0]->total_size;
                $tableCount = (int) $stats[0]->table_count;
            }
            $engine = 'InnoDB MySQL 8.x';
        } else {
            // SQLite
            $tables = DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tableCount = count($tables);
            $dbPath = config('database.connections.sqlite.database');
            if ($dbPath && File::exists($dbPath)) {
                $totalSizeBytes = File::size($dbPath);
            } else {
                $totalSizeBytes = 1024 * 1024 * 3; // Estimasi 3MB
            }
            $engine = 'SQLite In-Memory / File';
        }

        $sizeMb = round($totalSizeBytes / (1024 * 1024), 2);

        return [
            'driver'          => $driver,
            'engine'          => $engine,
            'size_bytes'      => $totalSizeBytes,
            'size_mb'         => $sizeMb . ' MB',
            'table_count'     => $tableCount,
            'database_name'   => config('database.connections.' . $driver . '.database', 'simod_enterprise'),
        ];
    }

    /**
     * Cek apakah saat ini waktunya menjalankan backup otomatis.
     */
    public static function isScheduledToRunNow(): bool
    {
        $setting = BackupSetting::current();
        if (!$setting->is_auto_backup_enabled) {
            return false;
        }

        $now = Carbon::now();
        $targetTime = $setting->backup_time ?? '02:00';
        [$targetHour, $targetMinute] = explode(':', $targetTime);

        switch ($setting->schedule_type) {
            case 'every_6_hours':
                if (!$setting->last_backup_at) return true;
                return $setting->last_backup_at->diffInHours($now) >= 6;

            case 'every_12_hours':
                if (!$setting->last_backup_at) return true;
                return $setting->last_backup_at->diffInHours($now) >= 12;

            case 'weekly':
                // Hari Minggu dan jam sesuai
                if ($now->dayOfWeek !== Carbon::SUNDAY) return false;
                if ($now->hour !== (int) $targetHour) return false;
                if ($setting->last_backup_at && $setting->last_backup_at->isToday()) return false;
                return true;

            case 'daily':
            default:
                // Jam sama dengan targetHour dan belum backup hari ini
                if ($now->hour !== (int) $targetHour) return false;
                if ($setting->last_backup_at && $setting->last_backup_at->isToday()) return false;
                return true;
        }
    }
}
