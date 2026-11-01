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
        'api_keys_pool',
        'gemini_model',
        'is_auto_analyze',
        'sync_mode',
        'interval_minutes',
        'scheduled_times',
        'temperature',
        'last_analyzed_at',
        'last_status',
        'last_error',
    ];

    protected $casts = [
        'api_keys_pool'    => 'array',
        'scheduled_times'  => 'array',
        'interval_minutes' => 'integer',
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
                'gemini_model'     => 'gemini-3.5-flash-lite',
                'is_auto_analyze'  => false,
                'sync_mode'        => 'after_rss',
                'interval_minutes' => 180,
                'scheduled_times'  => ['06:00', '12:00', '18:00', '22:00'],
                'temperature'      => 0.20,
            ]
        );
    }

    /**
     * Return clean list of all unique API keys from pool, single key, or environment.
     */
    public function getCleanKeysList(): array
    {
        $keys = [];

        if (is_array($this->api_keys_pool) && !empty($this->api_keys_pool)) {
            foreach ($this->api_keys_pool as $item) {
                if (is_string($item) && !empty(trim($item))) {
                    $keys[] = trim($item);
                } elseif (is_array($item) && !empty($item['key'])) {
                    $keys[] = trim($item['key']);
                }
            }
        }

        if (!empty($this->gemini_api_key) && !in_array(trim($this->gemini_api_key), $keys)) {
            array_unshift($keys, trim($this->gemini_api_key));
        }

        return array_values(array_unique(array_filter($keys)));
    }
}
