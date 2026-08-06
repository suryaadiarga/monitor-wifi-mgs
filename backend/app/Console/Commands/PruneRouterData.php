<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class PruneRouterData extends Command
{
    protected $signature = 'isp:router:prune {--chunk=500 : Jumlah maksimum row per operasi delete}';

    protected $description = 'Hapus histori polling RouterOS yang melewati masa retensi';

    public function handle(): int
    {
        $chunk = max(50, min((int) $this->option('chunk'), 2000));
        $counts = [
            'resource_history' => $this->deleteInBatches('router_metrics', 'recorded_at', now()->subDays((int) config('isp.mikrotik.resource_retention_days', 7)), $chunk),
            'interface_history' => $this->deleteInBatches('router_interface_metrics', 'recorded_at', now()->subDays((int) config('isp.mikrotik.interface_retention_days', 7)), $chunk),
            'sync_runs' => $this->deleteInBatches('router_sync_runs', 'finished_at', now()->subDays((int) config('isp.mikrotik.error_retention_days', 30)), $chunk),
        ];

        $sessionCutoff = now()->subDays((int) config('isp.mikrotik.session_retention_days', 30));
        foreach (['router_pppoe_active_sessions', 'router_hotspot_active_sessions'] as $table) {
            $counts[$table] = $this->deleteInBatches($table, 'missing_since', $sessionCutoff, $chunk, true);
        }

        $this->info('Pruning RouterOS selesai: '.json_encode($counts, JSON_UNESCAPED_SLASHES));

        return self::SUCCESS;
    }

    private function deleteInBatches(string $table, string $timestampColumn, \DateTimeInterface $cutoff, int $chunk, bool $requireNotNull = false): int
    {
        $deleted = 0;

        do {
            $query = DB::table($table)->where($timestampColumn, '<', $cutoff);
            if ($requireNotNull) {
                $query->whereNotNull($timestampColumn);
            }
            $ids = $query->orderBy('id')->limit($chunk)->pluck('id');
            $batch = $ids->isEmpty() ? 0 : DB::table($table)->whereIn('id', $ids)->delete();
            $deleted += $batch;
        } while ($batch === $chunk);

        return $deleted;
    }
}
