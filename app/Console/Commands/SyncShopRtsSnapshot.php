<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SyncShopRtsSnapshot extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:shop-rts-snapshot {--days=14 : Size of the lookback window in days}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Snapshot each shop\'s value-weighted RTS rate over the previous N days (default 14) onto shops.rts_snapshot';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $days = max(1, (int) $this->option('days'));

        // The window is the N *completed* days before today, so the figure is
        // stable no matter what time of day the command runs.
        $end = Carbon::yesterday()->endOfDay();
        $start = Carbon::yesterday()->subDays($days - 1)->startOfDay();

        $this->info("Snapshotting shop RTS for {$start->toDateString()} → {$end->toDateString()} ({$days} days).");

        // Value-weighted, not count-weighted: returned pesos over
        // delivered+returned pesos. An order lands in the window on whichever of
        // delivered_at / returning_at it actually has, matching how the RTS
        // dashboards pick up an order (see RtsBaseQuery). A high-value return
        // therefore moves the rate more than a cheap one does.
        $rates = DB::table('pancake_orders')
            ->selectRaw('
                shop_id,
                SUM(CASE WHEN status = 3 THEN COALESCE(final_amount, 0) ELSE 0 END) AS delivered_amount,
                SUM(CASE WHEN status IN (4, 5) THEN COALESCE(final_amount, 0) ELSE 0 END) AS returned_amount
            ')
            ->whereNotNull('shop_id')
            ->whereIn('status', [3, 4, 5])
            ->where(function ($query) use ($start, $end) {
                $query->whereBetween('delivered_at', [$start, $end])
                    ->orWhereBetween('returning_at', [$start, $end]);
            })
            ->groupBy('shop_id')
            ->get()
            ->keyBy('shop_id');

        $now = now();
        $updated = 0;
        $cleared = 0;

        Shop::query()
            ->select(['id'])
            ->chunkById(200, function ($shops) use ($rates, $now, &$updated, &$cleared) {
                foreach ($shops as $shop) {
                    $row = $rates->get($shop->id);
                    $total = $row ? (float) $row->delivered_amount + (float) $row->returned_amount : 0.0;

                    // No delivered/returned value in the window means "no rate to
                    // show", not "0% RTS" — null keeps the two apart. A shop whose
                    // orders in the window all happen to be zero-amount lands here
                    // too, which is the honest answer: there is nothing to weight.
                    $snapshot = $total > 0
                        ? round((float) $row->returned_amount / $total, 4)
                        : null;

                    Shop::whereKey($shop->id)->update([
                        'rts_snapshot' => $snapshot,
                        'rts_snapshot_updated_at' => $now,
                    ]);

                    if ($snapshot === null) {
                        $cleared++;
                    } else {
                        $updated++;
                    }
                }
            });

        $this->info("Shop RTS snapshot synced: {$updated} with a rate, {$cleared} without orders in the window.");

        return self::SUCCESS;
    }
}
