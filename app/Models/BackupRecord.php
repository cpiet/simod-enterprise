<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class BackupRecord extends Model
{
    protected $fillable = [
        'filename',
        'disk',
        'filepath',
        'size_bytes',
        'formatted_size',
        'backup_type',
        'status',
        'checksum_sha256',
        'tables_count',
        'duration_seconds',
        'created_by',
        'metadata',
    ];

    protected $casts = [
        'size_bytes'       => 'integer',
        'tables_count'     => 'integer',
        'duration_seconds' => 'decimal:2',
        'metadata'         => 'array',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if physical file exists on disk.
     */
    public function fileExists(): bool
    {
        return Storage::disk($this->disk ?? 'local')->exists($this->filepath);
    }

    /**
     * Helper format size.
     */
    public static function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}
