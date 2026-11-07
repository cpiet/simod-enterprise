<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Akun media sosial yang dipantau per Polda (Instagram, TikTok, X, YouTube, Halaman Facebook).
 * `posts` berisi snapshot unggahan publik terakhir yang berhasil diambil; metrik yang tidak tersedia = null.
 */
class MonitoredSocialAccount extends Model
{
    public const PLATFORMS = ['instagram', 'tiktok', 'x', 'youtube', 'facebook'];

    protected $table = 'monitored_social_accounts';

    protected $fillable = ['polda_id', 'platform', 'auto_monitor', 'username', 'display_name', 'profile', 'posts', 'last_synced_at', 'sync_status', 'last_error'];

    protected $casts = ['posts' => 'array', 'profile' => 'array', 'last_synced_at' => 'datetime', 'auto_monitor' => 'boolean'];

    /** Tautan profil publik akun di platformnya. */
    public function profileUrl(): string
    {
        $u = rawurlencode($this->username);

        return match ($this->platform) {
            'tiktok' => "https://www.tiktok.com/@{$u}",
            'x' => "https://x.com/{$u}",
            'youtube' => "https://www.youtube.com/@{$u}",
            'facebook' => "https://www.facebook.com/{$u}",
            default => "https://www.instagram.com/{$u}/",
        };
    }
}
