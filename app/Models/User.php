<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'polda_id',
        'polres_id',
        'name',
        'username',
        'email',
        'password',
        'role',
        'badge_number',
        'phone',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function trustedDevices(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(TrustedDevice::class);
    }

    protected static function booted(): void
    {
        // Kata sandi diganti/di-reset atau akun dinonaktifkan: seluruh perangkat tepercaya (PIN) dicabut.
        static::saved(function (User $user) {
            if ($user->wasChanged('password') || ($user->wasChanged('is_active') && ! $user->is_active)) {
                $user->trustedDevices()->delete();
            }
        });
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'alerts_seen_at'    => 'datetime', // batas "sudah dibaca" peringatan social listening
        ];
    }

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function polres(): BelongsTo
    {
        return $this->belongsTo(Polres::class);
    }

    public function isSuperAdmin(): bool
    {
        return in_array($this->role, ['super_admin_mabes', 'kadiv_humas', 'superadmin'], true);
    }

    public function isPoldaLeader(): bool
    {
        return in_array($this->role, ['super_admin_mabes', 'kadiv_humas', 'polda_leader'], true);
    }

    public function isKadivHumas(): bool
    {
        return $this->role === 'kadiv_humas';
    }

    public function hasPermission(string $moduleKey): bool
    {
        return RolePermission::isGranted($this->role ?? 'operator', $moduleKey, $this->polda_id);
    }
}
