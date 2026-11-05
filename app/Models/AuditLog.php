<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Catatan aktivitas sistem (siapa melakukan apa, kapan, dari mana).
 *
 * Log dapat dibersihkan oleh administrator (manual atau retensi otomatis), tetapi setiap pembersihan SELALU
 * tercatat sebagai `audit.purged` / `audit.auto_pruned` dan catatan pembersihan itu sendiri tidak ikut terhapus.
 */
class AuditLog extends Model
{
    public $timestamps = false;

    /** Tindakan pembersihan: dilindungi dari penghapusan agar jejak "siapa menghapus log" tetap ada. */
    public const PROTECTED_ACTIONS = ['audit.purged', 'audit.auto_pruned'];

    /** Kategori tampilan -> awalan kode aksi (bagian sebelum titik). */
    public const CATEGORIES = [
        'auth'       => ['label' => 'Masuk & Keluar',      'prefixes' => ['auth']],
        'akun_akses' => ['label' => 'Akun & Hak Akses',    'prefixes' => ['personalia', 'roles', 'security', 'menu', 'polda', 'user']],
        'isu'        => ['label' => 'Laporan Isu',         'prefixes' => ['issue', 'task', 'publication', 'press_release']],
        'intelijen'  => ['label' => 'Radar & Analisis',    'prefixes' => ['apify', 'social_settings', 'social_intelligence', 'social_case', 'media', 'ai']],
        'sistem'     => ['label' => 'Sistem & Pemeliharaan', 'prefixes' => ['database', 'news', 'audit', 'rss', 'settings']],
    ];

    /** Label Bahasa Indonesia untuk kode aksi yang dikenal. Kode lain ditampilkan dalam bentuk yang dirapikan. */
    public const LABELS = [
        'auth.login'                => 'Berhasil masuk',
        'auth.login_failed'         => 'Gagal masuk',
        'auth.logout'               => 'Keluar',
        'auth.password_changed'     => 'Ganti kata sandi',
        'security.update'           => 'Ubah pengaturan keamanan',
        'roles.matrix_update'       => 'Ubah matriks hak akses',
        'roles.matrix_reset'        => 'Reset matriks hak akses',
        'personalia.create'         => 'Akun personel dibuat',
        'personalia.update'         => 'Akun personel diubah',
        'personalia.toggle_status'  => 'Status akun diubah',
        'personalia.reset_password' => 'Kata sandi akun direset',
        'personalia.delete'         => 'Akun personel dihapus',
        'user.created'              => 'Akun dibuat',
        'polda.update'              => 'Data Polda diubah',
        'menu.create'               => 'Menu dibuat',
        'menu.update'               => 'Menu diubah',
        'menu.toggle'               => 'Menu ditampilkan/disembunyikan',
        'menu.delete'               => 'Menu dihapus',
        'menu.reset_defaults'       => 'Menu dikembalikan ke bawaan',
        'issue.create'              => 'Laporan isu dibuat',
        'issue.archive'             => 'Laporan isu diarsipkan',
        'issue.restore'             => 'Laporan isu dipulihkan',
        'issue.delete'              => 'Laporan isu dipindahkan ke Sampah',
        'issue.delete_permanent'    => 'Laporan isu dihapus permanen',
        'issue.bulk'                => 'Aksi massal pada laporan isu',
        'database.backup'           => 'Cadangan basis data dibuat',
        'database.restore'          => 'Basis data dipulihkan',
        'database.delete_backup'    => 'Cadangan dihapus',
        'database.settings'         => 'Pengaturan cadangan diubah',
        'news.cleanup'              => 'Pembersihan berita',
        'apify.sync'                => 'Sinkron Apify',
        'apify.trends_sync'         => 'Sinkron tren Apify',
        'apify.keyword_crawl'       => 'Lacak kata kunci Apify',
        'social_settings.update'    => 'Pengaturan medsos diubah',
        'social_intelligence.ai_analysis' => 'Analisis AI medsos',
        'social_case.archive'       => 'Kasus medsos diarsipkan',
        'audit.purged'              => 'Log audit dibersihkan',
        'ai.block_cleared'          => 'Penahanan permintaan AI dicabut',
        'security.national_brief'   => 'Ringkasan isu nasional diubah',
        'audit.auto_pruned'         => 'Log audit dipangkas otomatis',
        'audit.retention'           => 'Retensi log audit diubah',
        'demo.prepare'              => 'Persiapan demo (data contoh dibersihkan)',
        'audit.export'              => 'Log audit diekspor',
    ];

    /** Kunci metadata yang nilainya tidak boleh tampil/diekspor. */
    private const SENSITIVE_KEYS = ['password', 'pass', 'token', 'secret', 'api_key', 'apikey', 'key', 'authorization'];

    protected $fillable = [
        'polda_id',
        'user_id',
        'action',
        'subject_type',
        'subject_id',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'metadata'   => 'array',
        'created_at' => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Helper log action. Peristiwa tanpa tenant aktif (mis. gagal masuk sebelum login) dicatat sebagai tingkat sistem (polda_id null).
     */
    public static function log(string $action, ?string $subjectType = null, ?string $subjectId = null, array $metadata = []): self
    {
        $poldaId = session('active_polda_id');

        return static::create([
            'polda_id'     => $poldaId ?: null,
            'user_id'      => auth()->id(),
            'action'       => $action,
            'subject_type' => $subjectType,
            'subject_id'   => $subjectId,
            'metadata'     => $metadata,
            'ip_address'   => request() ? request()->ip() : '127.0.0.1',
            'user_agent'   => request() ? request()->userAgent() : 'CLI/System',
            'created_at'   => now(),
        ]);
    }

    // ------------------------------------------------------------------ presentasi

    public static function labelFor(string $action): string
    {
        if (isset(self::LABELS[$action])) {
            return self::LABELS[$action];
        }

        return ucfirst(trim(str_replace(['.', '_'], ' ', $action)));
    }

    public static function categoryOf(string $action): string
    {
        $prefix = explode('.', $action, 2)[0];
        foreach (self::CATEGORIES as $key => $cat) {
            if (in_array($prefix, $cat['prefixes'], true)) {
                return $key;
            }
        }

        return 'lainnya';
    }

    /** Peristiwa gagal/ditolak/terkunci. */
    public static function isFailure(string $action): bool
    {
        return (bool) preg_match('/(failed|denied|locked|blocked)/', $action);
    }

    /**
     * Ringkasan metadata satu baris, dengan nilai sensitif disamarkan.
     */
    public static function summarize(?array $meta, int $max = 220): string
    {
        if (empty($meta)) {
            return '';
        }

        if (! empty($meta['summary']) && is_string($meta['summary'])) {
            return mb_substr($meta['summary'], 0, $max);
        }
        if (! empty($meta['report_code']) && ! empty($meta['title'])) {
            return mb_substr("[{$meta['report_code']}] {$meta['title']}", 0, $max);
        }

        $parts = [];
        foreach (self::redact($meta) as $k => $v) {
            $parts[] = $k . ': ' . (is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE));
        }

        return mb_substr(implode(', ', $parts), 0, $max);
    }

    /**
     * Salinan metadata dengan nilai sensitif diganti "[disamarkan]".
     *
     * @param array<string, mixed> $meta
     * @return array<string, mixed>
     */
    public static function redact(array $meta): array
    {
        $out = [];
        foreach ($meta as $k => $v) {
            if (in_array(strtolower((string) $k), self::SENSITIVE_KEYS, true) || preg_match('/(password|token|secret|api_?key)/i', (string) $k)) {
                $out[$k] = '[disamarkan]';
            } elseif (is_array($v)) {
                $out[$k] = self::redact($v);
            } else {
                $out[$k] = $v;
            }
        }

        return $out;
    }

    // ------------------------------------------------------------------ kueri

    /**
     * Batasi log pada yang boleh dilihat pengguna:
     *  - personel daerah: hanya Polda-nya sendiri;
     *  - Mabes/Super Admin pada mode Nasional: seluruhnya;
     *  - Mabes/Super Admin pada satu Polda: log Polda itu + log tingkat sistem (tanpa Polda).
     */
    public function scopeVisibleTo(Builder $query, ?User $user, ?string $selectedCode, ?Polda $selectedPolda): Builder
    {
        if ($user && ! $user->isSuperAdmin() && $user->polda_id) {
            return $query->where('audit_logs.polda_id', $user->polda_id);
        }
        if ($selectedCode === 'ALL' || ! $selectedPolda) {
            return $query;
        }

        return $query->where(fn (Builder $w) => $w->where('audit_logs.polda_id', $selectedPolda->id)->orWhereNull('audit_logs.polda_id'));
    }

    /** Peristiwa keamanan akun: masuk/keluar, kata sandi, akun, hak akses, parameter keamanan. */
    public function scopeSecurityEvents(Builder $query): Builder
    {
        return $query->where(function (Builder $w) {
            foreach (array_merge(self::CATEGORIES['auth']['prefixes'], self::CATEGORIES['akun_akses']['prefixes']) as $prefix) {
                $w->orWhere('audit_logs.action', 'like', $prefix . '.%');
            }
        });
    }

    public function scopeInCategory(Builder $query, string $category): Builder
    {
        if ($category === 'lainnya') {
            return $query->where(function (Builder $w) {
                foreach (self::CATEGORIES as $cat) {
                    foreach ($cat['prefixes'] as $prefix) {
                        $w->where('audit_logs.action', 'not like', $prefix . '.%');
                    }
                }
            });
        }
        $prefixes = self::CATEGORIES[$category]['prefixes'] ?? [];

        return $query->where(function (Builder $w) use ($prefixes) {
            $w->whereRaw('1 = 0');
            foreach ($prefixes as $prefix) {
                $w->orWhere('audit_logs.action', 'like', $prefix . '.%');
            }
        });
    }

    public function scopeFailures(Builder $query): Builder
    {
        return $query->where(function (Builder $w) {
            foreach (['failed', 'denied', 'locked', 'blocked'] as $word) {
                $w->orWhere('audit_logs.action', 'like', '%' . $word . '%');
            }
        });
    }

    /**
     * Terapkan filter pengguna (q, kategori, aksi, hasil, from, to, user). Nilai tak valid diabaikan.
     *
     * @param array<string, mixed> $f
     */
    public function scopeFiltered(Builder $query, array $f, string $timezone = 'Asia/Makassar'): Builder
    {
        $q = trim((string) ($f['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_substr($q, 0, 100)) . '%';
            $query->where(function (Builder $w) use ($like) {
                $w->whereRaw("audit_logs.action LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("audit_logs.ip_address LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("audit_logs.subject_type LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("audit_logs.subject_id LIKE ? ESCAPE '!'", [$like])
                    ->orWhereRaw("CAST(audit_logs.metadata AS CHAR) LIKE ? ESCAPE '!'", [$like])
                    ->orWhereHas('user', fn (Builder $u) => $u->whereRaw("name LIKE ? ESCAPE '!'", [$like])->orWhereRaw("username LIKE ? ESCAPE '!'", [$like]));
            });
        }

        $category = (string) ($f['kategori'] ?? '');
        if ($category !== '' && (isset(self::CATEGORIES[$category]) || $category === 'lainnya')) {
            $query->inCategory($category);
        }

        $action = (string) ($f['aksi'] ?? '');
        if ($action !== '') {
            $query->where('audit_logs.action', $action);
        }

        $result = (string) ($f['hasil'] ?? '');
        if ($result === 'gagal') {
            $query->failures();
        } elseif ($result === 'berhasil') {
            $query->where(fn (Builder $w) => $w->where('audit_logs.action', 'not like', '%failed%')
                ->where('audit_logs.action', 'not like', '%denied%')
                ->where('audit_logs.action', 'not like', '%locked%')
                ->where('audit_logs.action', 'not like', '%blocked%'));
        }

        $user = (int) ($f['user'] ?? 0);
        if ($user > 0) {
            $query->where('audit_logs.user_id', $user);
        }

        foreach (['from' => ['>=', 'startOfDay'], 'to' => ['<=', 'endOfDay']] as $key => [$op, $edge]) {
            $date = (string) ($f[$key] ?? '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && strtotime($date) !== false) {
                $query->where('audit_logs.created_at', $op, now($timezone)->setDateFrom(new \DateTimeImmutable($date))->{$edge}()->utc()->format('Y-m-d H:i:s'));
            }
        }

        return $query;
    }
}
