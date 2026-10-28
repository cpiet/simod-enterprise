<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EarlyWarning extends Model
{
    protected $fillable = [
        'polda_id',
        'polres_id',
        'title',
        'level',
        'category',
        'facts',
        'reputation_risk',
        'protocol_0_2h',
        'protocol_2_6h',
        'protocol_6_24h',
        'pic_units',
        'status',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function polres(): BelongsTo
    {
        return $this->belongsTo(Polres::class);
    }
}
