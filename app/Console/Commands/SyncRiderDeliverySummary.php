<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\RiderDeliverySummary;

class SyncRiderDeliverySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-rider-delivery-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync rider delivery summary with delivery_success, delivery_fail, and rts_rate';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        DB::table('parcel_journeys as pj')
            ->join('pancake_orders as po', 'po.id', '=', 'pj.order_id')
            ->selectRaw('
                pj.rider_name,
                pj.rider_mobile as rider_phone,
                COUNT(DISTINCT CASE WHEN po.delivered_at IS NOT NULL THEN po.id END) as delivery_success,
                COUNT(DISTINCT CASE WHEN po.returning_at IS NOT NULL THEN po.id END) as delivery_fail
            ')
            ->whereIn('po.status', [3, 4, 5])
            ->whereNotNull('pj.rider_name')
            ->whereNotNull('pj.rider_mobile')
            ->groupBy(['pj.rider_name', 'pj.rider_mobile'])
            ->orderBy('pj.rider_name')
            ->get()
            ->chunk(100)
            ->each(function ($items) {
                $items->each(function ($item) {
                    $total = $item->delivery_success + $item->delivery_fail;

                    RiderDeliverySummary::updateOrCreate(
                        ['rider_name' => $item->rider_name, 'rider_phone' => $item->rider_phone],
                        [
                            'delivery_success' => $item->delivery_success,
                            'delivery_fail' => $item->delivery_fail,
                            'rts_rate' => $total > 0 ? $item->delivery_fail / $total : 0,
                        ]
                    );
                });
            });

        $this->info('Rider delivery summary synced successfully.');
    }
}
