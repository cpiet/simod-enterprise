<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Ringkasan komentar asli pada satu unggahan akun pantauan: komentar (maks. 50) beserta sentimen per komentar
 * (aturan NewsSentiment::explainSocial), hitungan sentimen, kata yang sering muncul, dan ringkasan SADEWA opsional.
 */
class SocialCommentDigest extends Model
{
    protected $fillable = [
        'polda_id', 'account_id', 'platform', 'post_url', 'post_caption', 'comments', 'total', 'positif', 'netral', 'negatif',
        'top_words', 'source_hash', 'ai_summary', 'ai_model', 'fetched_at', 'summarized_at',
    ];

    protected $casts = [
        'comments' => 'array', 'top_words' => 'array', 'fetched_at' => 'datetime', 'summarized_at' => 'datetime',
        'total' => 'integer', 'positif' => 'integer', 'netral' => 'integer', 'negatif' => 'integer',
    ];

    public function account()
    {
        return $this->belongsTo(MonitoredSocialAccount::class, 'account_id');
    }
}
