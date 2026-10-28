<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Polda extends Model
{
    protected $fillable = [
        'code',
        'name',
        'region',
        'timezone',
        'logo_url',
        'kapolda_name',
        'kabid_humas_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function polres(): HasMany
    {
        return $this->hasMany(Polres::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function earlyWarnings(): HasMany
    {
        return $this->hasMany(EarlyWarning::class);
    }

    public function mediaFeeds(): HasMany
    {
        return $this->hasMany(MediaFeed::class);
    }
}
