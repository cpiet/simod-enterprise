<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Riwayat percakapan SADEWA AI per akun pengguna (privat: hanya pemilik yang dapat membaca).
 */
class SadewaConversation extends Model
{
    public const MAX_MESSAGES = 60;

    protected $fillable = ['user_id', 'polda_id', 'title', 'scope', 'messages'];

    protected $casts = ['messages' => 'array'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
