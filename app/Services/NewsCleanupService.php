<?php

namespace App\Services;

use App\Models\MediaArticle;
use Illuminate\Database\Eloquent\Builder;

class NewsCleanupService
{
    /**
     * Query artikel yang boleh dibersihkan: BUKAN bookmark dan lebih lama dari $days hari.
     * $days = 0 berarti semua artikel non-bookmark. $poldaId null = semua Polda.
     */
    public function query(?int $poldaId, int $days): Builder
    {
        return MediaArticle::query()
            ->where('is_bookmarked', false)
            ->when($poldaId, fn ($q) => $q->where('polda_id', $poldaId))
            ->when($days > 0, function ($q) use ($days) {
                $cutoff = now()->subDays($days);
                $q->where(fn ($w) => $w->where('pub_date', '<', $cutoff)
                    ->orWhere(fn ($n) => $n->whereNull('pub_date')->where('created_at', '<', $cutoff)));
            });
    }

    public function count(?int $poldaId, int $days): int
    {
        return $this->query($poldaId, $days)->count();
    }

    public function purge(?int $poldaId, int $days): int
    {
        return $this->query($poldaId, $days)->delete();
    }
}
