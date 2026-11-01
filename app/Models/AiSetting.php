<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiSetting extends Model
{
    use HasFactory;

    protected $table = 'ai_settings';

    protected $fillable = [
        'polda_id',
        'gemini_api_key',
        'gemini_model',
        'is_auto_analyze',
        'temperature',
        'last_analyzed_at',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'is_auto_analyze'  => 'boolean',
        'temperature'      => 'float',
        'last_analyzed_at' => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    /**
     * Get or create setting for specific Polda (or global when null).
     */
    public static function forPolda(?int $poldaId = null): self
    {
        return static::firstOrCreate(
            ['polda_id' => $poldaId],
            [
                'gemini_model'    => 'gemini-2.5-pro',
                'is_auto_analyze' => false,
                'temperature'     => 0.20,
            ]
        );
    }
}

