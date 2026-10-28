<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\Polda;
use App\Models\Polres;
use App\Models\Publication;
use App\Models\Task;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SystemModuleController extends Controller
{
    protected function getBaseData(Request $request): array
    {
        $allPoldas = Polda::withCount('polres')->where('is_active', true)->get();
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
            'pending_verify'     => Issue::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'monitoring')->count(),
            'active_tasks'       => Task::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->where('status', 'in_progress')->count(),
            'total_publications' => Publication::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))->count(),
            'server_wita'        => now()->setTimezone($selectedPolda->timezone ?? 'Asia/Makassar')->format('d M Y, H:i:s') . ' WITA',
        ];

        return [$allPoldas, $selectedPolda, $selectedCode, $metrics];
    }

    /**
     * Antrean Verifikasi Laporan & Isu
     */
    public function verificationQueue(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $pendingIssues = Issue::with(['polres'])
            ->when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id))
            ->whereIn('status', ['monitoring', 'investigasi'])
            ->latest()
            ->paginate(10);

        return Inertia::render('VerificationQueue/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'issues'        => $pendingIssues,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Galeri Media Dokumentasi & Bukti
     */
    public function gallery(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Gallery/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Laporan & Rekapitulasi Humas
     */
    public function reports(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Reports/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Manajemen Personalia & Pengguna
     */
    public function personalia(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $users = User::when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id'))
            ->latest()
            ->paginate(15);

        return Inertia::render('Personalia/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'users'         => $users,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Audit Logs Aktivitas Sistem
     */
    public function auditLogs(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $logs = AuditLog::with('user')
            ->when($selectedPolda, fn($q) => $q->where('polda_id', $selectedPolda->id)->orWhereNull('polda_id'))
            ->latest()
            ->paginate(20);

        return Inertia::render('AuditLogs/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'logs'          => $logs,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Profil Pengguna & PIN Cepat
     */
    public function profile(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Profile/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Pengaturan Wilayah Polda & Satwil
     */
    public function poldaSettings(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        $polresList = $selectedPolda ? Polres::where('polda_id', $selectedPolda->id)->get() : [];

        return Inertia::render('Settings/Polda', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'polresList'    => $polresList,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Pusat Keamanan (Turnstile, PIN, Brute Force Protection)
     */
    public function securityCenter(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Security', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Matriks Hak Akses & Kewenangan (RBAC)
     */
    public function rolesMatrix(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Roles', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Pengaturan Mail Server (SMTP)
     */
    public function mailSettings(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Mail', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Backup Basis Data
     */
    public function databaseBackup(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Backup', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }

    /**
     * Mode Pemeliharaan Sistem
     */
    public function systemMaintenance(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);

        return Inertia::render('Settings/Maintenance', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
        ]);
    }
}
