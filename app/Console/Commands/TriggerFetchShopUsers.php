<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopCustomers;
use Modules\Pancake\Jobs\FetchShopUsers;

class TriggerFetchShopUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'trigger-fetch-shops-users';

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
        Shop::whereHas('pages')
            ->each(function (Shop $shop) {
                dispatch(new FetchShopUsers($shop))->onQueue('pancake');
            });
    }
}
