<?php

namespace App\Models;

use App\Models\Concerns\BumpsShellCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Polres extends Model
{
    use BumpsShellCache;

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

    /**
     * Nama wilayah (mis. "Polresta Mamuju" -> "Mamuju") untuk mencocokkan berita yang menyebut wilayah ini.
     * Null bila terlalu pendek/ambigu (kurang dari 4 huruf).
     */
    public function placeKeyword(): ?string
    {
        $keyword = trim((string) preg_replace('/^(polrestabes|polresta|polres|polsek|polda)\s+/i', '', (string) $this->name));

        return mb_strlen($keyword) >= 4 ? $keyword : null;
    }
}
