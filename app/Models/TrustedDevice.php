<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Perangkat tepercaya untuk masuk cepat dengan PIN. Lihat App\Http\Controllers\Auth\QuickLoginController.
 */
class TrustedDevice extends Model
{
    /** Salah PIN berturut-turut sebelum perangkat dicabut (harus masuk lagi dengan kata sandi). */
    public const MAX_ATTEMPTS = 5;

    /** Masa berlaku perangkat (hari), diperpanjang setiap kali dipakai. */
    public const LIFETIME_DAYS = 90;

    public const COOKIE = 'simod_device';

    protected $fillable = ['user_id', 'token_hash', 'pin_hash', 'label', 'last_ip', 'failed_attempts', 'last_used_at', 'expires_at'];

    protected $hidden = ['token_hash', 'pin_hash'];

    protected function casts(): array
    {
        return [
            'last_used_at' => 'datetime',
            'expires_at'   => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** PIN terlalu mudah ditebak: angka kembar, urutan naik/turun. */
    public static function isWeakPin(string $pin): bool
    {
        if (preg_match('/^(\d)\1+$/', $pin)) {
            return true;
        }

        return in_array($pin, ['012345', '123456', '234567', '345678', '456789', '987654', '876543', '765432', '654321', '543210'], true);
    }
}
