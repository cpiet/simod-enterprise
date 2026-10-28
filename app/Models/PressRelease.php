<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PressRelease extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'polres_id',
        'author_id',
        'title',
        'press_number',
        'content',
        'talking_points',
        'dos_and_donts',
        'status',
        'spokesperson_name',
        'published_at',
    ];

    protected $casts = [
        'talking_points' => 'array',
        'dos_and_donts'  => 'array',
        'published_at'   => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function polres(): BelongsTo
    {
        return $this->belongsTo(Polres::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}

