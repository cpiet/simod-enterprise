<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

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
     * Helper log action.
     */
    public static function log(string $action, ?string $subjectType = null, ?string $subjectId = null, array $metadata = []): self
    {
        $poldaId = session('active_polda_id');
        if (!$poldaId && class_exists(Polda::class)) {
            $poldaId = Polda::where('code', 'SULBAR')->value('id') ?: Polda::value('id');
        }

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
}

