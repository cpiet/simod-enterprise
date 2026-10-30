<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecuritySetting extends Model
{
    protected $fillable = [
        'polda_id',
        'enable_turnstile',
        'turnstile_site_key',
        'turnstile_secret_key',
        'max_login_attempts',
        'lockout_minutes',
        'enable_pin',
        'pin_expiry_days',
        'session_timeout_minutes',
        'private_media_storage',
        'ip_whitelist',
        'updated_by',
    ];

    protected $casts = [
        'enable_turnstile'        => 'boolean',
        'enable_pin'              => 'boolean',
        'private_media_storage'   => 'boolean',
        'max_login_attempts'      => 'integer',
        'lockout_minutes'         => 'integer',
        'pin_expiry_days'         => 'integer',
        'session_timeout_minutes' => 'integer',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Get or create active security settings for a given Polda (or global).
     */
    public static function current(?int $poldaId = null): self
    {
        if ($poldaId) {
            $setting = static::where('polda_id', $poldaId)->first();
            if ($setting) {
                return $setting;
            }
        }

        return static::firstOrCreate(
            ['polda_id' => null],
            [
                'enable_turnstile'        => true,
                'turnstile_site_key'      => '0x4AAAAAAAiH8s-xxxxxxxx',
                'turnstile_secret_key'    => '0x4AAAAAAAiH8s-yyyyyyyyyyyyyyyyyyyyyyyy',
                'max_login_attempts'      => 5,
                'lockout_minutes'         => 15,
                'enable_pin'              => true,
                'pin_expiry_days'         => 90,
                'session_timeout_minutes' => 30,
                'private_media_storage'   => true,
                'ip_whitelist'            => "127.0.0.1\n10.12.4.15\n182.1.204.88",
            ]
        );
    }

    /**
     * Calculate a dynamic security index score (0-100) based on active rules.
     */
    public function calculateScore(): array
    {
        $score = 40; // Base score

        if ($this->enable_turnstile) {
            $score += 15;
            if (!empty($this->turnstile_site_key) && !empty($this->turnstile_secret_key)) {
                $score += 5;
            }
        }

        if ($this->max_login_attempts <= 3) {
            $score += 15;
        } elseif ($this->max_login_attempts <= 5) {
            $score += 10;
        } elseif ($this->max_login_attempts <= 10) {
            $score += 5;
        }

        if ($this->lockout_minutes >= 30) {
            $score += 10;
        } elseif ($this->lockout_minutes >= 15) {
            $score += 8;
        } elseif ($this->lockout_minutes >= 5) {
            $score += 4;
        }

        if ($this->session_timeout_minutes <= 15) {
            $score += 10;
        } elseif ($this->session_timeout_minutes <= 30) {
            $score += 8;
        } elseif ($this->session_timeout_minutes <= 60) {
            $score += 4;
        }

        if ($this->enable_pin) {
            $score += 6;
            if ($this->pin_expiry_days <= 90) {
                $score += 4;
            }
        }

        if ($this->private_media_storage) {
            $score += 5;
        }

        $score = min(100, max(0, $score));

        $category = 'Kategori Sangat Aman';
        $badgeColor = 'text-emerald-400';
        if ($score < 60) {
            $category = 'Kategori Rentan (Perlu Penguatan)';
            $badgeColor = 'text-rose-400';
        } elseif ($score < 80) {
            $category = 'Kategori Standar (Cukup)';
            $badgeColor = 'text-amber-400';
        }

        return [
            'score'      => $score,
            'category'   => $category,
            'color'      => $badgeColor,
        ];
    }
}
