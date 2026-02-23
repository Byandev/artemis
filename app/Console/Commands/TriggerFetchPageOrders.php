<?php

namespace App\Console\Commands;

use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class TriggerFetchPageOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-page-orders';

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
        Page::whereNotNull('orders_last_synced_at')
            ->whereNotNull('pos_token')
            ->whereNotNull('shop_id')
            ->whereNotNull('botcake_token')
            ->whereNotNull('infotxt_token')
            ->whereNotNull('infotxt_user_id')
            ->get()
            ->each(function (Page $page) {
                dispatch(new \App\Jobs\FetchPageOrders($page, 1, \Carbon\Carbon::parse($page->orders_last_synced_at)->unix(), \Carbon\Carbon::now()->unix()));
            });
    }
}
