<?php

namespace App\Console\Commands;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchPageOrders;

class TriggerFetchPageOrders extends Command
{
    protected $signature = 'trigger-fetch-page-orders {id? : Sync only this page ID}';

    protected $description = 'Sync Pancake page orders. At 9/12/15/18/21 it pulls shipped orders (filter_status[]=2); otherwise it pulls orders updated since orders_last_synced_at.';

    public function handle()
    {
        $shipped = in_array((int) now()->format('G'), [9, 12, 15, 18, 21], true);

        Page::whereNotNull('orders_last_synced_at')
            ->whereNotNull('pos_token')
            ->whereNotNull('shop_id')
            ->where('status', 'active')
            ->when($this->argument('id'), fn ($query, $id) => $query->where('id', $id))
            ->orderBy('created_at', 'asc')
            ->get()
            ->each(function (Page $page) use ($shipped) {
                dispatch(new FetchPageOrders(
                    $page,
                    1,
                    Carbon::parse($page->orders_last_synced_at)->unix(),
                    Carbon::now()->unix(),
                    $shipped,
                ))->onQueue('pancake');
            });
    }
}
