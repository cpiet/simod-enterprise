<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'polres_id',
        'created_by',
        'title',
        'slug',
        'summary',
        'category',
        'priority',
        'status',
        'sentiment',
        'source_media',
        'source_url',
        'virality_score',
        'engagement_count',
        'assigned_unit',
        'is_hoax',
        'fact_check_result',
        'official_clarification',
        'clarification_url',
        'clarified_at',
    ];

    protected $casts = [
        'is_hoax'      => 'boolean',
        'clarified_at' => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function polres(): BelongsTo
    {
        return $this->belongsTo(Polres::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
