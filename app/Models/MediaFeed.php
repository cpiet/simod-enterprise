<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaFeed extends Model
{
    protected $fillable = [
        'polda_id',
        'name',
        'url',
        'category',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function articles(): HasMany
    {
        return $this->hasMany(MediaArticle::class, 'feed_id');
    }
}
