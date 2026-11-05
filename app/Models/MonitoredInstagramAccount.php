<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonitoredInstagramAccount extends Model
{
    protected $fillable = ['polda_id', 'username', 'display_name', 'posts', 'last_synced_at', 'sync_status'];

    protected $casts = ['posts' => 'array', 'last_synced_at' => 'datetime'];
}
