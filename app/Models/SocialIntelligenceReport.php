<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialIntelligenceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'user_id',
        'report_date',
        'report_time',
        'title',
        'posts_count',
        'model_used',
        'latency_ms',
        'executive_brief',
        'top_concern',
        'sentiment_analysis',
        'buzzer_vs_organic',
        'critical_accounts',
        'cooling_system',
        'whatsapp_script',
        'counter_narrative',
        'virality_forecast',
        'source_hash',
    ];

    protected $casts = [
        'report_date'        => 'date',
        'sentiment_analysis' => 'array',
        'buzzer_vs_organic'  => 'array',
        'critical_accounts'  => 'array',
        'cooling_system'     => 'array',
        'counter_narrative'  => 'array',
        'virality_forecast'  => 'array',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

