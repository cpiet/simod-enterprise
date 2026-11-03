<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SocialInvestigationCase extends Model
{
    protected $fillable = [
        'polda_id',
        'title',
        'keywords',
        'description',
        'priority',
        'status',
        'platforms',
        'posts_count',
        'total_views',
        'total_engagements',
        'sentiment_breakdown',
        'intelligence_notes',
        'created_by',
        'last_crawled_at',
    ];

    protected $casts = [
        'platforms'           => 'array',
        'sentiment_breakdown' => 'array',
        'last_crawled_at'     => 'datetime',
        'created_at'          => 'datetime',
        'updated_at'          => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(SocialPost::class, 'case_id');
    }

    /**
     * Get a short readable title for compact tabs.
     */
    public function getShortTitleAttribute(): string
    {
        // Try extracting known incident nouns or simplify
        $title = trim($this->title);
        if (preg_match('/(?:kasus|isu|tentang|terkait)\s+(.+)/i', $title, $m)) {
            $extracted = $m[1];
        } else {
            $extracted = $title;
        }

        return Str::limit($extracted, 25);
    }

    /**
     * Recalculate metrics from linked posts.
     */
    public function refreshMetrics(): void
    {
        $posts = $this->posts()->get();
        $count = $posts->count();
        $views = $posts->sum('views_count');
        $engagements = $posts->sum('likes_count') + $posts->sum('shares_count') + $posts->sum('comments_count');

        $pos = $posts->where('sentiment', 'positif')->count();
        $net = $posts->where('sentiment', 'netral')->count();
        $neg = $posts->where('sentiment', 'negatif')->count();

        $this->update([
            'posts_count'         => $count,
            'total_views'         => $views,
            'total_engagements'   => $engagements,
            'sentiment_breakdown' => [
                'positif' => $pos,
                'netral'  => $net,
                'negatif' => $neg,
            ],
            'last_crawled_at'     => now(),
        ]);
    }
}
