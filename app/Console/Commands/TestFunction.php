<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pancake\Models\OrderForDelivery;

class TestFunction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test-function';

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
        $items = OrderForDelivery::with([
            'order'=> function ($query) {
                $query->select(['id', 'order_number', 'status_name', 'final_amount', 'parcel_status', 'tracking_code']);
            },
            'conferrer' => function ($query) {
                $query->select(['id', 'name']);
            },
            'page' => function ($query) {
                $query->select(['id', 'name']);
            },
        ])->paginate();

        dd($items->toArray());
    }
}
