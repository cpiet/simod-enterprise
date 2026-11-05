<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SecuritySetting;
use App\Models\User;
use App\Services\SystemHealth;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Audit Log: lihat, saring, ekspor, dan bersihkan. Seluruh angka dan status berasal dari data nyata.
 *
 * Pembersihan (manual maupun otomatis) tidak pernah menghapus catatan pembersihan sebelumnya
 * (`AuditLog::PROTECTED_ACTIONS`) dan selalu meninggalkan catatan baru berisi siapa, berapa, dan kriteria.
 */
class AuditLogController extends Controller
{
    public const RETENTION_OPTIONS = [0, 30, 60, 90, 180, 365, 730];
    private const FILTER_KEYS = ['q', 'kategori', 'aksi', 'hasil', 'from', 'to', 'user'];

    public function index(Request $request): Response
    {
        [$allPoldas, $selectedPolda, $selectedCode, $metrics] = $this->getBaseData($request);
        $tz = $this->displayTimezone($selectedPolda, $selectedCode);
        $filters = $this->filtersFrom($request);
        $perPage = in_array((int) $request->query('per_page'), [20, 50, 100], true) ? (int) $request->query('per_page') : 20;

        $visible = fn () => AuditLog::query()->visibleTo($request->user(), $selectedCode, $selectedPolda);

        $logs = $visible()->filtered($filters, $tz)->with(['user:id,name,username,role', 'polda:id,code,name'])
            ->latest('audit_logs.created_at')->latest('audit_logs.id')
            ->paginate($perPage)->withQueryString()
            ->through(fn (AuditLog $l) => $this->present($l, $tz));

        $day = now()->subDay()->format('Y-m-d H:i:s');
        $stats = [
            'total'        => $visible()->count(),
            'last24'       => $visible()->where('audit_logs.created_at', '>=', $day)->count(),
            'failed24'     => $visible()->failures()->where('audit_logs.created_at', '>=', $day)->count(),
            'failed_login24' => $visible()->where('audit_logs.action', 'auth.login_failed')->where('audit_logs.created_at', '>=', $day)->count(),
            'users24'      => $visible()->whereNotNull('audit_logs.user_id')->where('audit_logs.created_at', '>=', $day)->distinct()->count('audit_logs.user_id'),
            'oldest'       => $this->formatTs($visible()->min('audit_logs.created_at'), $tz),
        ];

        $setting = SecuritySetting::current(null);
        $health = app(SystemHealth::class);

        return Inertia::render('AuditLogs/Index', [
            'allPoldas'     => $allPoldas,
            'selectedPolda' => $selectedPolda,
            'selectedCode'  => $selectedCode,
            'metrics'       => $metrics,
            'logs'          => $logs,
            'stats'         => $stats,
            'filters'       => $filters + ['per_page' => $perPage],
            'facets'        => $this->facets($visible, $filters, $tz),
            'timezone'      => $tz,
            'retention'     => ['days' => (int) $setting->audit_retention_days, 'options' => self::RETENTION_OPTIONS],
            'serverHealth'  => $health->snapshot($tz),
            'readiness'     => $health->checks($request, $setting),
            'scheduler'     => $health->scheduler(),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        [, $selectedPolda, $selectedCode] = $this->getBaseData($request);
        $tz = $this->displayTimezone($selectedPolda, $selectedCode);
        $filters = $this->filtersFrom($request);

        $query = AuditLog::query()->visibleTo($request->user(), $selectedCode, $selectedPolda)->filtered($filters, $tz)
            ->with(['user:id,name,username,role', 'polda:id,code,name'])->orderByDesc('audit_logs.id');

        AuditLog::log('audit.export', 'audit_logs', null, ['filters' => array_filter($filters)]);

        $file = 'audit-log-' . now($tz)->format('Ymd-His') . '.csv';

        return response()->streamDownload(function () use ($query, $tz) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Waktu', 'Zona waktu', 'Pengguna', 'Peran', 'Kategori', 'Operasi', 'Kode aksi', 'Hasil', 'Wilayah', 'Alamat IP', 'Rincian']);
            $query->limit(100000)->chunk(1000, function ($rows) use ($out, $tz) {
                foreach ($rows as $l) {
                    $p = $this->present($l, $tz);
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $p['time'], $tz, $p['user'], $p['role'], $p['category_label'], $p['label'], $p['action'],
                        $p['failed'] ? 'GAGAL' : 'BERHASIL', $p['polda'], $p['ip'], $p['detail'],
                    ]));
                }
            });
            fclose($out);
        }, $file, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Pratinjau: berapa log yang akan terhapus bila pembersihan dijalankan dengan pilihan ini. */
    public function purgePreview(Request $request): JsonResponse
    {
        $data = $this->validatePurge($request, false);
        [$query, $tz] = $this->purgeQuery($request, $data);

        $count = (clone $query)->count();

        return response()->json([
            'count'  => $count,
            'oldest' => $this->formatTs((clone $query)->min('audit_logs.created_at'), $tz),
            'newest' => $this->formatTs((clone $query)->max('audit_logs.created_at'), $tz),
        ]);
    }

    public function purge(Request $request): RedirectResponse|JsonResponse
    {
        $data = $this->validatePurge($request, true);
        [$query, $tz] = $this->purgeQuery($request, $data);

        $deleted = $this->deleteChunked($query);

        AuditLog::log('audit.purged', 'audit_logs', null, [
            'summary' => "Membersihkan {$deleted} log audit (" . $this->describeCriteria($data) . ').',
            'deleted' => $deleted,
            'mode'    => $data['mode'],
            'scope'   => $data['scope'] ?? 'all',
            'days'    => $data['days'] ?? null,
            'filters' => $data['mode'] === 'filtered' ? array_filter($this->filtersFrom($request)) : null,
        ]);

        $message = $deleted > 0 ? "{$deleted} log audit dibersihkan. Catatan pembersihan ini tersimpan di log." : 'Tidak ada log yang cocok untuk dibersihkan.';

        return $request->wantsJson()
            ? response()->json(['deleted' => $deleted, 'message' => $message])
            : back()->with('success', $message);
    }

    public function updateRetention(Request $request): RedirectResponse
    {
        $data = $request->validate(['days' => 'required|integer|in:' . implode(',', self::RETENTION_OPTIONS)]);

        $setting = SecuritySetting::current(null);
        $before = (int) $setting->audit_retention_days;
        $setting->update(['audit_retention_days' => (int) $data['days'], 'updated_by' => auth()->id()]);

        AuditLog::log('audit.retention', 'security_settings', (string) $setting->id, [
            'summary' => 'Retensi log audit diubah dari ' . ($before ?: 'selamanya') . ' menjadi ' . ($data['days'] ?: 'selamanya') . ' hari.',
        ]);

        return back()->with('success', $data['days'] > 0
            ? "Log audit lebih dari {$data['days']} hari akan dipangkas otomatis setiap hari pukul 02:30."
            : 'Pemangkasan otomatis dimatikan: log disimpan selamanya sampai dibersihkan manual.');
    }

    // ------------------------------------------------------------------ internal

    /** @return array<string, mixed> */
    private function filtersFrom(Request $request): array
    {
        $f = [];
        foreach (self::FILTER_KEYS as $key) {
            $f[$key] = trim((string) $request->query($key, $request->input($key, '')));
        }
        $f['user'] = (int) $f['user'] ?: '';

        return $f;
    }

    private function displayTimezone($selectedPolda, ?string $selectedCode): string
    {
        return $selectedCode === 'ALL' ? 'Asia/Jakarta' : ($selectedPolda->timezone ?? 'Asia/Makassar');
    }

    private function tzLabel(string $tz): string
    {
        return ['Asia/Jakarta' => 'WIB', 'Asia/Makassar' => 'WITA', 'Asia/Jayapura' => 'WIT'][$tz] ?? $tz;
    }

    private function formatTs($raw, string $tz): ?string
    {
        return $raw ? \Illuminate\Support\Carbon::parse($raw, 'UTC')->setTimezone($tz)->format('d M Y, H:i') . ' ' . $this->tzLabel($tz) : null;
    }

    /** @return array<string, mixed> */
    private function present(AuditLog $l, string $tz): array
    {
        $action = (string) $l->action;
        $category = AuditLog::categoryOf($action);
        $meta = is_array($l->metadata) ? $l->metadata : [];

        return [
            'id'             => $l->id,
            'time'           => $l->created_at ? $l->created_at->copy()->setTimezone($tz)->format('d M Y, H:i:s') . ' ' . $this->tzLabel($tz) : '-',
            'action'         => $action,
            'label'          => AuditLog::labelFor($action),
            'category'       => $category,
            'category_label' => AuditLog::CATEGORIES[$category]['label'] ?? 'Lainnya',
            'failed'         => AuditLog::isFailure($action),
            'user'           => $l->user?->name ?? (($meta['username'] ?? null) ? $meta['username'] . ' (tidak dikenal)' : 'Sistem'),
            'role'           => $l->user?->role,
            'polda'          => $l->polda?->code ?? 'Sistem',
            'ip'             => $l->ip_address ?: '-',
            'user_agent'     => $l->user_agent,
            'detail'         => AuditLog::summarize($meta),
            'meta'           => $meta === [] ? null : AuditLog::redact($meta),
            'subject'        => trim(($l->subject_type ?? '') . ($l->subject_id ? ' #' . $l->subject_id : '')),
        ];
    }

    /**
     * Pilihan filter dinamis dari isi log (kategori dengan jumlahnya, kode aksi yang benar-benar ada).
     *
     * @return array<string, mixed>
     */
    private function facets(\Closure $visible, array $filters, string $tz): array
    {
        $byAction = $visible()->filtered(array_merge($filters, ['kategori' => '', 'aksi' => '']), $tz)
            ->toBase()->selectRaw('audit_logs.action as a, COUNT(*) as c')->groupBy('audit_logs.action')->pluck('c', 'a')->all();

        $categories = [];
        $actions = [];
        foreach ($byAction as $action => $count) {
            $cat = AuditLog::categoryOf((string) $action);
            $categories[$cat] = ($categories[$cat] ?? 0) + (int) $count;
            if ($filters['kategori'] === '' || $filters['kategori'] === $cat) {
                $actions[] = ['code' => (string) $action, 'label' => AuditLog::labelFor((string) $action), 'count' => (int) $count];
            }
        }
        usort($actions, fn ($a, $b) => $b['count'] <=> $a['count']);

        $catList = [];
        foreach (AuditLog::CATEGORIES + ['lainnya' => ['label' => 'Lainnya']] as $key => $cat) {
            if (($categories[$key] ?? 0) > 0) {
                $catList[] = ['key' => $key, 'label' => $cat['label'], 'count' => $categories[$key]];
            }
        }

        $users = $visible()->whereNotNull('audit_logs.user_id')->toBase()
            ->selectRaw('audit_logs.user_id as uid, COUNT(*) as c')->groupBy('audit_logs.user_id')->orderByDesc('c')->limit(50)->pluck('c', 'uid')->all();
        $names = User::whereIn('id', array_keys($users))->pluck('name', 'id');

        return [
            'categories' => $catList,
            'actions'    => $actions,
            'users'      => collect($users)->map(fn ($c, $id) => ['id' => (int) $id, 'name' => $names[$id] ?? "#{$id}", 'count' => (int) $c])->values()->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function validatePurge(Request $request, bool $requireConfirm): array
    {
        $data = $request->validate([
            'mode'  => 'required|in:older_than,filtered,all',
            'days'  => 'required_if:mode,older_than|nullable|integer|in:7,30,90,180,365',
            'scope' => 'nullable|in:all,security',
        ]);

        // Pilihan yang menghapus tanpa batas umur wajib dikonfirmasi dengan mengetik HAPUS.
        if ($requireConfirm && in_array($data['mode'], ['filtered', 'all'], true) && strtoupper(trim((string) $request->input('confirm'))) !== 'HAPUS') {
            abort(response()->json(['message' => 'Ketik HAPUS untuk mengonfirmasi.'], 422));
        }

        return $data;
    }

    /** @return array{0: Builder, 1: string} */
    private function purgeQuery(Request $request, array $data): array
    {
        [, $selectedPolda, $selectedCode] = $this->getBaseData($request);
        $tz = $this->displayTimezone($selectedPolda, $selectedCode);

        $query = AuditLog::query()->visibleTo($request->user(), $selectedCode, $selectedPolda)
            ->whereNotIn('audit_logs.action', AuditLog::PROTECTED_ACTIONS);

        if (($data['scope'] ?? 'all') === 'security') {
            $query->securityEvents();
        }
        if ($data['mode'] === 'older_than') {
            $query->where('audit_logs.created_at', '<', now()->subDays((int) $data['days'])->format('Y-m-d H:i:s'));
        } elseif ($data['mode'] === 'filtered') {
            $query->filtered($this->filtersFrom($request), $tz);
        }

        return [$query, $tz];
    }

    /** Hapus per potongan agar tidak mengunci tabel besar di hosting. */
    private function deleteChunked(Builder $query, int $chunk = 2000): int
    {
        $total = 0;
        while (true) {
            $ids = (clone $query)->orderBy('audit_logs.id')->limit($chunk)->pluck('audit_logs.id');
            if ($ids->isEmpty()) {
                break;
            }
            $total += AuditLog::whereIn('id', $ids)->delete();
        }

        return $total;
    }

    private function describeCriteria(array $data): string
    {
        $scope = ($data['scope'] ?? 'all') === 'security' ? 'khusus aktivitas keamanan, ' : '';

        return $scope . match ($data['mode']) {
            'older_than' => "lebih lama dari {$data['days']} hari",
            'filtered'   => 'sesuai filter yang aktif',
            default      => 'seluruh log',
        };
    }

    private function csvSafe(mixed $v): string
    {
        $s = (string) $v;

        return $s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $s : $s;
    }
}
