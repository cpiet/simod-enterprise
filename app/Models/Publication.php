<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Publication extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'polres_id',
        'issue_id',
        'author_id',
        'channel',
        'title',
        'content_draft',
        'url',
        'approval_status',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function polres(): BelongsTo
    {
        return $this->belongsTo(Polres::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }
}
