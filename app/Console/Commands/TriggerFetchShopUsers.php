<?php

namespace App\Console\Commands;

use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Modules\Pancake\Jobs\FetchShopUsers;
use Modules\Pancake\Models\User;

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
        //        $startDate = Carbon::now()->startOfYear()->format('Y-m-d H:i:s');
        //        $endDate = Carbon::now()->endOfYear()->format('Y-m-d H:i:s');

        //        $users = User::query()
        //            ->withCount([
        //                'orders' => function ($query) use ($startDate, $endDate) {
        //                    $query->whereBetween('confirmed_at', [$startDate, $endDate]);
        //                }
        //            ])
        //            ->withSum([
        //                'orders as sales' => function ($query) use ($startDate, $endDate) {
        //                    $query->whereBetween('confirmed_at', [$startDate, $endDate]);
        //                }
        //            ], 'final_amount')
        //            ->orderByDesc('sales')
        //            ->get();

        Shop::whereHas('pages')
            ->each(function (Shop $shop) {
                dispatch(new FetchShopUsers($shop))->onQueue('pancake');
            });
    }
}
