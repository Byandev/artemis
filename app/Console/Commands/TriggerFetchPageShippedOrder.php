<?php

namespace App\Console\Commands;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchPageShippedOrders;

class TriggerFetchPageShippedOrder extends Command
{
    protected $signature = 'trigger-fetch-page-shipped-orders';

    protected $description = 'Sync Pancake page orders filtered to shipped status (status[]=2).';

    public function handle()
    {
        Page::whereNotNull('orders_last_synced_at')
            ->whereNotNull('pos_token')
            ->whereNotNull('shop_id')
            ->whereNotNull('botcake_token')
            ->whereNotNull('infotxt_token')
            ->whereNotNull('infotxt_user_id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->each(function (Page $page) {
                dispatch(new FetchPageShippedOrders($page, 1, Carbon::parse($page->orders_last_synced_at)->unix(), Carbon::now()->unix()))->onQueue('pancake');
            });
    }
}
