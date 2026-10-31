<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialPost extends Model
{
    use HasFactory;

    protected $table = 'social_posts';

    protected $fillable = [
        'polda_id',
        'polres_id',
        'platform',
        'author_name',
        'author_handle',
        'author_avatar',
        'is_kol',
        'kol_category',
        'post_id',
        'content',
        'post_url',
        'thumbnail_url',
        'likes_count',
        'shares_count',
        'comments_count',
        'views_count',
        'sentiment',
        'virality_score',
        'is_bookmarked',
        'posted_at',
    ];

    protected $casts = [
        'is_kol'         => 'boolean',
        'is_bookmarked'  => 'boolean',
        'likes_count'    => 'integer',
        'shares_count'   => 'integer',
        'comments_count' => 'integer',
        'views_count'    => 'integer',
        'virality_score' => 'integer',
        'posted_at'      => 'datetime',
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
