<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssSyncSetting extends Model
{
    protected $fillable = [
        'polda_id',
        'sync_mode',
        'interval_minutes',
        'scheduled_times',
        'max_articles_per_feed',
        'max_retained_articles_per_polda',
        'auto_prune_days',
        'timeout_seconds',
        'is_active',
        'last_sync_at',
        'last_sync_status',
        'last_sync_message',
        'last_articles_count',
        'updated_by',
    ];

    protected $casts = [
        'scheduled_times'                 => 'array',
        'interval_minutes'                => 'integer',
        'max_articles_per_feed'           => 'integer',
        'max_retained_articles_per_polda' => 'integer',
        'auto_prune_days'                 => 'integer',
        'timeout_seconds'                 => 'integer',
        'is_active'                       => 'boolean',
        'last_sync_at'                    => 'datetime',
        'last_articles_count'             => 'integer',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get or create setting for specific Polda or Global
     */
    public static function forPolda(?int $poldaId = null): self
    {
        return self::firstOrCreate(
            ['polda_id' => $poldaId],
            [
                'sync_mode'                       => 'interval',
                'interval_minutes'                => 180, // Default 3 hours
                'scheduled_times'                 => ['06:00', '12:00', '18:00'],
                'max_articles_per_feed'           => 25,
                'max_retained_articles_per_polda' => 1000,
                'auto_prune_days'                 => 90,
                'timeout_seconds'                 => 8,
                'is_active'                       => true,
                'last_sync_status'                => 'idle',
            ]
        );
    }
}
