<?php

namespace Modules\Inventory\Console\Commands;

use Illuminate\Console\Command;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderStatusLog;

/**
 * Re-derive `paid_at` on every purchase order that carries a status trail.
 *
 * Needed once because the column was originally populated inline by the sync,
 * so trails written by any other path left it null. Safe to re-run: it reads
 * the logs and writes the same answer every time, and skips orders already
 * holding the right value.
 */
class BackfillPurchasedOrderPaidAtCommand extends Command
{
    protected $signature = 'inventory:backfill-po-paid-at
                            {--workspace= : Limit to one workspace id}
                            {--dry-run : Report what would change without writing}';

    protected $description = 'Re-derive paid_at on purchase orders from their status logs';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = PurchasedOrder::query()
            ->whereHas('statusLogs')
            ->when($this->option('workspace'), fn ($q, $id) => $q->where('workspace_id', $id));

        $total = (clone $query)->count();

        if ($total === 0) {
            $this->info('No purchase orders carry a status trail yet — nothing to backfill.');

            return self::SUCCESS;
        }

        $changed = 0;
        $cleared = 0;
        $bar = $this->output->createProgressBar($total);

        $query->with('statusLogs')->chunkById(200, function ($orders) use (&$changed, &$cleared, $dryRun, $bar) {
            foreach ($orders as $order) {
                $before = $order->paid_at?->toDateTimeString();

                $after = $order->statusLogs
                    ->filter(fn ($log) => $log->logged_at && strtolower(trim($log->status)) === PurchasedOrderStatusLog::PAID)
                    ->min('logged_at')?->toDateTimeString();

                if ($before !== $after) {
                    $changed++;
                    if ($after === null) {
                        $cleared++;
                    }
                    if (! $dryRun) {
                        $order->recalculatePaidAt();
                    }
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $verb = $dryRun ? 'would change' : 'changed';
        $this->info("{$total} order(s) with a trail; {$changed} {$verb}".($cleared ? " ({$cleared} cleared to null)" : '').'.');

        if ($dryRun) {
            $this->comment('Dry run — nothing written.');
        }

        return self::SUCCESS;
    }
}
