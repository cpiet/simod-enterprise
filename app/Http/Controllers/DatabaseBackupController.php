<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\BackupRecord;
use App\Models\BackupSetting;
use App\Models\Issue;
use App\Models\Polda;
use App\Models\Publication;
use App\Models\Task;
use App\Services\DatabaseBackupService;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DatabaseBackupController extends Controller
{
    protected DatabaseBackupService $backupService;

    public function __construct(DatabaseBackupService $backupService)
    {
        $this->backupService = $backupService;
    }

    protected function getBaseData(Request $request): array
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
        if ($allPoldas->isEmpty()) {
            $allPoldas = Polda::all();
        }

        $selectedCode = $request->query('polda', 'SULBAR');
        $selectedPolda = null;

        if ($selectedCode !== 'ALL') {
            $selectedPolda = Polda::where('code', $selectedCode)->first();
            if (! $selectedPolda) {
                $selectedPolda = $allPoldas->first();
                $selectedCode = $selectedPolda ? $selectedPolda->code : 'SULBAR';
            }
        }

        $metrics = [
            'total_issues'       => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'pending_verify'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->whereIn('status', ['monitoring', 'investigasi'])->count(),
            'active_tasks'       => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => Publication::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return [$allPoldas, $selectedPolda, $selectedCode, $metrics];
    }

    /**
     * Tampilan Pusat Backup & Pemulihan Basis Data.
     */
    public function index(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        // Sinkronisasi berkas fisik di disk dengan tabel basis data
        $this->backupService->syncDiskFilesWithDatabase();

        $setting = BackupSetting::current();
        $dbStats = $this->backupService->getDatabaseStats();

        // Ambil daftar rekaman backup (terbaru)
        $backups = BackupRecord::with('creator')
            ->latest('created_at')
            ->paginate(15)
            ->withQueryString();

        // Format data untuk Vue
        $formattedBackups = $backups->through(function ($b) {
            return [
                'id'              => $b->id,
                'filename'        => $b->filename,
                'size'            => $b->formatted_size,
                'size_bytes'      => $b->size_bytes,
                'date'            => $b->created_at ? $b->created_at->format('d M Y, H:i') . ' WITA' : '-',
                'type'            => strtoupper($b->backup_type ?? 'MANUAL'),
                'status'          => strtoupper($b->status ?? 'VALID'),
                'checksum'        => $b->checksum_sha256 ? substr($b->checksum_sha256, 0, 16) . '...' : null,
                'checksum_full'   => $b->checksum_sha256,
                'tables_count'    => $b->tables_count,
                'duration'        => $b->duration_seconds,
                'creator_name'    => $b->creator?->name ?: 'Sistem Otomatis',
            ];
        });

        // 8 Log Audit Terkait Backup & Restore
        $auditLogs = AuditLog::with('user')
            ->whereIn('action', ['database.backup', 'database.restore', 'database.delete_backup', 'database.settings'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn ($log) => [
                'id'         => $log->id,
                'action'     => $log->action,
                'user'       => $log->user ? $log->user->name : 'Administrator',
                'ip'         => $log->ip_address ?: '127.0.0.1',
                'time_ago'   => $log->created_at ? $log->created_at->diffForHumans() : 'Baru saja',
                'created_at' => $log->created_at ? $log->created_at->format('d M Y, H:i') : now()->format('d M Y, H:i'),
                'metadata'   => $log->metadata,
            ]);

        return Inertia::render('Settings/Backup', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'backups'       => $formattedBackups,
            'setting'       => [
                'is_auto_backup_enabled' => (bool) $setting->is_auto_backup_enabled,
                'schedule_type'          => (string) $setting->schedule_type,
                'backup_time'            => (string) $setting->backup_time,
                'retention_days'         => (int) $setting->retention_days,
                'gzip_compression'       => (bool) $setting->gzip_compression,
                'last_backup_at'         => $setting->last_backup_at ? $setting->last_backup_at->format('d M Y, H:i') : null,
                'last_backup_status'     => (string) $setting->last_backup_status,
                'last_backup_filename'   => (string) $setting->last_backup_filename,
                'last_backup_message'    => (string) $setting->last_backup_message,
            ],
            'stats'         => $dbStats,
            'auditLogs'     => $auditLogs,
            'flash'         => [
                'success' => session('success'),
                'error'   => session('error'),
            ],
        ]);
    }

    /**
     * Buat snapshot cadangan manual seketika.
     */
    public function createBackup(Request $request): RedirectResponse
    {
        try {
            $record = $this->backupService->createBackup('manual', auth()->id());
            return back()->with('success', "Snapshot basis data baru berhasil dibuat: {$record->filename} ({$record->formatted_size})");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal membuat backup: ' . $e->getMessage());
        }
    }

    /**
     * Unduh berkas cadangan SQL ke komputer lokal pengguna.
     */
    public function download(string $filename): BinaryFileResponse|RedirectResponse
    {
        $cleanFilename = basename($filename);
        $fullPath = storage_path('app/backups/' . $cleanFilename);

        if (!File::exists($fullPath)) {
            return back()->with('error', "Berkas cadangan tidak ditemukan: {$cleanFilename}");
        }

        return response()->download($fullPath, $cleanFilename, [
            'Content-Type' => 'application/octet-stream',
        ]);
    }

    /**
     * Pulihkan / restore database dari snapshot yang tersimpan.
     */
    public function restore(Request $request, string $filename): RedirectResponse
    {
        $request->validate([
            'confirm_text' => 'required|string',
        ]);

        if (trim(strtoupper($request->confirm_text)) !== 'PULIHKAN') {
            return back()->with('error', 'Kata konfirmasi salah. Harap ketik "PULIHKAN" untuk memverifikasi pemulihan.');
        }

        try {
            $result = $this->backupService->restoreBackup($filename, auth()->id());
            return back()->with('success', $result['message']);
        } catch (Exception $e) {
            return back()->with('error', 'Gagal memulihkan basis data: ' . $e->getMessage());
        }
    }

    /**
     * Unggah berkas SQL eksternal dan langsung lakukan pemulihan.
     */
    public function uploadAndRestore(Request $request): RedirectResponse
    {
        $request->validate([
            'backup_file'  => 'required|file|max:204800', // Max 200MB
            'confirm_text' => 'required|string',
        ]);

        if (trim(strtoupper($request->confirm_text)) !== 'PULIHKAN') {
            return back()->with('error', 'Kata konfirmasi salah. Harap ketik "PULIHKAN" untuk memverifikasi pemulihan berkas.');
        }

        try {
            $file = $request->file('backup_file');
            $result = $this->backupService->uploadAndRestore($file, auth()->id());

            return back()->with('success', "Berkas {$file->getClientOriginalName()} berhasil diunggah dan seluruh struktur serta data berhasil dipulihkan!");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal mengunggah dan memulihkan basis data: ' . $e->getMessage());
        }
    }

    /**
     * Hapus berkas cadangan dari disk dan record.
     */
    public function deleteBackup(string $filename): RedirectResponse
    {
        try {
            $cleanFilename = basename($filename);
            $this->backupService->deleteBackup($cleanFilename, auth()->id());
            return back()->with('success', "Berkas cadangan {$cleanFilename} berhasil dihapus permanen.");
        } catch (Exception $e) {
            return back()->with('error', 'Gagal menghapus berkas: ' . $e->getMessage());
        }
    }

    /**
     * Simpan pengaturan jadwal backup otomatis (Backup Berdasarkan Waktu).
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_auto_backup_enabled' => 'required|boolean',
            'schedule_type'          => 'required|string|in:daily,every_6_hours,every_12_hours,weekly',
            'backup_time'            => ['required', 'string', 'date_format:H:i'],
            'retention_days'         => 'required|integer|min:1|max:365',
            'gzip_compression'       => 'required|boolean',
        ]);

        $setting = BackupSetting::current();
        $setting->update([
            'is_auto_backup_enabled' => $validated['is_auto_backup_enabled'],
            'schedule_type'          => $validated['schedule_type'],
            'backup_time'            => $validated['backup_time'],
            'retention_days'         => $validated['retention_days'],
            'gzip_compression'       => $validated['gzip_compression'],
            'updated_by'             => auth()->id(),
        ]);

        AuditLog::log('database.settings', BackupSetting::class, (string) $setting->id, [
            'is_auto_backup_enabled' => $setting->is_auto_backup_enabled,
            'schedule_type'          => $setting->schedule_type,
            'backup_time'            => $setting->backup_time,
            'retention_days'         => $setting->retention_days,
            'gzip_compression'       => $setting->gzip_compression,
        ]);

        return back()->with('success', 'Konfigurasi jadwal backup otomatis dan retensi data berhasil diperbarui!');
    }
}

