<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Issue extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'polres_id',
        'created_by',
        'report_code',
        'title',
        'slug',
        'summary',
        'location_text',
        'occurred_at',
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
        'resolved_note',
    ];

    protected $casts = [
        'is_hoax'      => 'boolean',
        'clarified_at' => 'datetime',
        'occurred_at'  => 'datetime',
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

    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(IssueStatusHistory::class)->orderBy('created_at', 'asc');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function publications(): HasMany
    {
        return $this->hasMany(Publication::class);
    }

    public function pressReleases(): HasMany
    {
        return $this->hasMany(PressRelease::class);
    }
}
