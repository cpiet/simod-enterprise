<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaFile extends Model
{
    use HasFactory;

    protected $table = 'media_files';

    protected $fillable = [
        'polda_id',
        'polres_id',
        'issue_id',
        'uploaded_by',
        'title',
        'caption',
        'file_type',
        'file_path',
        'file_url',
        'size_bytes',
        'mime_type',
        'review_status',
        'is_private',
    ];

    protected $casts = [
        'is_private' => 'boolean',
        'size_bytes' => 'integer',
    ];

    public function polda(): BelongsTo
    {
        return $this->belongsTo(Polda::class);
    }

    public function polres(): BelongsTo
    {
        return $this->belongsTo(Polres::class);
    }

    public function issue(): BelongsTo
    {
        return $this->belongsTo(Issue::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

