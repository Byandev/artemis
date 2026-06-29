<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopOrders;

class TriggerFetchShopOrders extends Command
{
    protected $signature = 'trigger-fetch-shop-orders {id? : Sync only this shop ID}';

    protected $description = 'Sync Pancake shop orders (all sources, incl. Webcake). At 9/12/15/18/21 it pulls shipped orders (filter_status[]=2); otherwise it pulls orders updated since orders_last_synced_at.';

    public function handle()
    {
        $shipped = in_array((int) now()->format('G'), [9, 12, 15, 18, 21], true);

        Shop::whereNotNull('orders_last_synced_at')
            ->whereNotNull('pos_token')
            ->whereHas('pages', fn ($query) => $query->where('status', 'active'))
            ->when($this->argument('id'), fn ($query, $id) => $query->where('id', $id))
            ->orderBy('created_at', 'asc')
            ->get()
            ->each(function (Shop $shop) use ($shipped) {
                dispatch(new FetchShopOrders(
                    $shop,
                    1,
                    Carbon::parse($shop->orders_last_synced_at)->unix(),
                    Carbon::now()->unix(),
                    $shipped,
                ))->onQueue('pancake');
            });
    }
}
