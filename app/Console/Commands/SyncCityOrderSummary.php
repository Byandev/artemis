<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pancake\Models\CityOrderSummary;
use Modules\Pancake\Models\Order;

class SyncCityOrderSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-city-order-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $items = Order::query()
            ->from('pancake_orders as po')
            ->leftJoin('shipping_addresses as sa', 'sa.order_id', '=', 'po.id')
            ->selectRaw("
                sa.district_id,
                sa.district_name,
                sa.province_id,
                sa.province_name,
                COUNT(DISTINCT CASE WHEN po.delivered_at IS NOT NULL THEN po.id END) as delivered_orders,
                COUNT(DISTINCT CASE WHEN po.returning_at IS NOT NULL THEN po.id END) as returned_orders
            ")
            ->whereIn('status', [3,4,5])
            ->whereNotNull('sa.district_id')
            ->groupBy(['sa.district_id', 'sa.district_name', 'sa.province_id', 'sa.province_name'])
            ->orderBy('sa.district_name')
            ->get()
            ->chunk(100)
            ->each(function ($items) {
                $items->each(function ($item) {
                    CityOrderSummary::updateOrCreate([
                        'district_id' => $item->district_id,
                    ], [
                        'province_id' => $item->province_id,
                        'province_name' => $item->province_name,
                        'name' => $item->district_name,
                        'delivered' => $item->delivered_orders,
                        'returned' => $item->returned_orders,
                        'rts_rate' => $item->returned_orders / ($item->returned_orders + $item->delivered_orders)
                    ]);
                });
            });
    }
}
