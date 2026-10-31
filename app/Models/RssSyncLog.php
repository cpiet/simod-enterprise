<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RssSyncLog extends Model
{
    protected $fillable = [
        'polda_id',
        'sync_type',
        'status',
        'feeds_processed',
        'articles_fetched',
        'articles_stored',
        'articles_pruned',
        'duration_ms',
        'memory_usage_mb',
        'message',
    ];

    protected $casts = [
        'feeds_processed'  => 'integer',
        'articles_fetched' => 'integer',
        'articles_stored'  => 'integer',
        'articles_pruned'  => 'integer',
        'duration_ms'      => 'integer',
        'memory_usage_mb'  => 'float',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }
}
