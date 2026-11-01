<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MediaTopic extends Model
{
    protected $fillable = [
        'polda_id',
        'title',
        'keywords',
        'description',
        'priority',
        'status',
        'created_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function manualArticles(): BelongsToMany
    {
        return $this->belongsToMany(MediaArticle::class, 'media_article_topic', 'media_topic_id', 'media_article_id')
            ->withTimestamps();
    }

    /**
     * Get array of cleaned lowercase keywords.
     *
     * @return array<string>
     */
    public function getKeywordsArray(): array
    {
        if (empty($this->keywords)) {
            return [];
        }

        $parts = preg_split('/[\r\n,]+/', (string) $this->keywords);
        $clean = [];

        foreach ($parts as $p) {
            $trimmed = mb_strtolower(trim($p));
            if ($trimmed !== '') {
                $clean[] = $trimmed;
            }
        }

        return array_values(array_unique($clean));
    }
}
