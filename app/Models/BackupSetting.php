<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BackupSetting extends Model
{
    protected $fillable = [
        'is_auto_backup_enabled',
        'schedule_type',
        'backup_time',
        'retention_days',
        'gzip_compression',
        'last_backup_at',
        'last_backup_status',
        'last_backup_filename',
        'last_backup_size_bytes',
        'last_backup_message',
        'updated_by',
    ];

    protected $casts = [
        'is_auto_backup_enabled' => 'boolean',
        'gzip_compression'       => 'boolean',
        'retention_days'         => 'integer',
        'last_backup_size_bytes' => 'integer',
        'last_backup_at'         => 'datetime',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Singleton / current backup setting.
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'is_auto_backup_enabled' => true,
            'schedule_type'          => 'daily',
            'backup_time'            => '02:00',
            'retention_days'         => 14,
            'gzip_compression'       => true,
            'last_backup_status'     => 'idle',
        ]);
    }
}

