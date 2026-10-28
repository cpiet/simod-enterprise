<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'polres_id',
        'issue_id',
        'created_by',
        'title',
        'instructions',
        'assigned_unit',
        'assigned_officer',
        'due_at',
        'priority',
        'status',
        'closed_at',
    ];

    protected $casts = [
        'due_at'    => 'datetime',
        'closed_at' => 'datetime',
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

    public function updates(): HasMany
    {
        return $this->hasMany(TaskUpdate::class);
    }
}
