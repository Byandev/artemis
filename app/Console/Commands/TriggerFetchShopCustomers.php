<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopCustomers;

class TriggerFetchShopCustomers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-shop-customers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        Shop::whereNotNull('pos_token')
            ->get()
            ->each(function (Shop $shop) {
                dispatch(new FetchShopCustomers($shop, 1, \Carbon\Carbon::parse($shop->customers_last_synced_at)->unix(), \Carbon\Carbon::now()->unix()));
            });
    }
}
