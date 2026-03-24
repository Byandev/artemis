<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pancake\Models\CityOrderSummary;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ProvinceOrderSummary;

class SyncProvinceOrderSummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-province-order-summary';

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
                sa.province_id,
                sa.province_name,
                COUNT(DISTINCT CASE WHEN po.delivered_at IS NOT NULL THEN po.id END) as delivered_orders,
                COUNT(DISTINCT CASE WHEN po.returning_at IS NOT NULL THEN po.id END) as returned_orders
            ")
            ->whereIn('status', [3,4,5])
            ->whereNotNull('sa.province_id')
            ->groupBy(['sa.province_id', 'sa.province_name'])
            ->orderBy('sa.province_name')
            ->get()
            ->chunk(100)
            ->each(function ($items) {
                $items->each(function ($item) {
                    ProvinceOrderSummary::updateOrCreate([
                        'province_id' => $item->province_id,
                    ], [
                        'name' => $item->province_name,
                        'delivered' => $item->delivered_orders,
                        'returned' => $item->returned_orders,
                        'rts_rate' => $item->returned_orders / ($item->returned_orders + $item->delivered_orders)
                    ]);
                });
            });
    }
}
