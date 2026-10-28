<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaIntelligenceReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'polda_id',
        'report_date',
        'report_time',
        'title',
        'snapshot_national_status',
        'snapshot_national_desc',
        'snapshot_sulbar_status',
        'snapshot_sulbar_desc',
        'top_concern',
        'executive_brief',
        'core_decision',
        'sentiment_national',
        'sentiment_sulbar',
        'priority_responses',
        'national_issues',
        'sulbar_issues',
        'early_warnings',
        'strategic_contents',
        'agenda_setting',
        'recommendations',
        'raw_input',
    ];

    protected $casts = [
        'report_date'        => 'date',
        'sentiment_national' => 'array',
        'sentiment_sulbar'   => 'array',
        'priority_responses' => 'array',
        'national_issues'    => 'array',
        'sulbar_issues'      => 'array',
        'early_warnings'     => 'array',
        'strategic_contents' => 'array',
        'agenda_setting'     => 'array',
        'recommendations'    => 'array',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }
}
