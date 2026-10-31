<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialSetting extends Model
{
    use HasFactory;

    protected $table = 'social_settings';

    protected $fillable = [
        'polda_id',
        'apify_api_token',
        'keywords_tracked',
        'sync_frequency',
        'auto_sync_enabled',
        'last_synced_at',
    ];

    protected $casts = [
        'auto_sync_enabled' => 'boolean',
        'last_synced_at'    => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }
}
