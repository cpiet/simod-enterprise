<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Peringatan social listening per Polda (dibuat otomatis dari data nyata akun pantauan; tidak pernah dikarang).
 * Tipe: kamtibmas (kata kunci Polri/kamtibmas), topik (cocok Topik Atensi aktif), lonjakan (keterlibatan ≥ 2×
 * median akun), komentar_negatif (≥ 40% dari ≥ 10 komentar asli bernada negatif).
 */
class SocialAlert extends Model
{
    protected $fillable = ['polda_id', 'account_id', 'platform', 'type', 'severity', 'title', 'body', 'post_url'];

    public function account()
    {
        return $this->belongsTo(MonitoredSocialAccount::class, 'account_id');
    }
}
