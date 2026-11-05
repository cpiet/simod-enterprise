<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\SecuritySetting;
use Illuminate\Console\Command;

/**
 * Pangkas log audit yang lebih tua dari retensi (Audit Log → "Retensi otomatis"; bawaan 180 hari, 0 = nonaktif).
 * Catatan pembersihan (`audit.purged`, `audit.auto_pruned`) tidak pernah ikut dihapus.
 */
class PruneAuditLogsCommand extends Command
{
    protected $signature = 'audit:prune {--days= : Timpa retensi (hari); kosong = pakai pengaturan}';

    protected $description = 'Pangkas log audit yang lebih lama dari masa retensi';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) SecuritySetting::current(null)->audit_retention_days;

        if ($days <= 0) {
            $this->info('Retensi log audit nonaktif (0 hari): tidak ada yang dipangkas.');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days)->format('Y-m-d H:i:s');
        $total = 0;
        do {
            $ids = AuditLog::where('created_at', '<', $cutoff)
                ->whereNotIn('action', AuditLog::PROTECTED_ACTIONS)
                ->orderBy('id')->limit(2000)->pluck('id');
            $total += $ids->isEmpty() ? 0 : AuditLog::whereIn('id', $ids)->delete();
        } while ($ids->isNotEmpty());

        if ($total > 0) {
            AuditLog::create([
                'polda_id' => null, 'user_id' => null, 'action' => 'audit.auto_pruned', 'subject_type' => 'audit_logs',
                'metadata' => ['summary' => "Pemangkasan otomatis: {$total} log lebih lama dari {$days} hari dihapus.", 'deleted' => $total, 'days' => $days],
                'ip_address' => '127.0.0.1', 'user_agent' => 'CLI/Scheduler', 'created_at' => now(),
            ]);
        }

        $this->info("{$total} log audit dipangkas (retensi {$days} hari).");

        return self::SUCCESS;
    }
}
