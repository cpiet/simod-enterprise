<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Polres extends Model
{
    protected $table = 'polres';

    protected $fillable = [
        'polda_id',
        'code',
        'name',
        'kapolres_name',
        'kasihumas_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
