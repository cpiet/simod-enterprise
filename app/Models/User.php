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

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
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
        return in_array($this->role, ['super_admin_mabes', 'kadiv_humas'], true);
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
