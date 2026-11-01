<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaArticle extends Model
{
    protected $fillable = [
        'polda_id',
        'feed_id',
        'title',
        'source_name',
        'category',
        'url',
        'image_url',
        'summary',
        'pub_date',
        'sentiment',
        'is_flagged',
        'is_bookmarked',
    ];

    protected $casts = [
        'pub_date'   => 'datetime',
        'is_flagged' => 'boolean',
        'is_bookmarked' => 'boolean',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(MediaFeed::class, 'feed_id');
    }

    public function manualTopics(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(MediaTopic::class, 'media_article_topic', 'media_article_id', 'media_topic_id')
            ->withTimestamps();
    }
}
