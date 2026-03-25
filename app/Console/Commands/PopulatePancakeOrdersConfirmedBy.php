<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\User;

class PopulatePancakeOrdersConfirmedBy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'populate-pancake-orders-confirmed-by';

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
        User::whereNotNull('fb_id')->get()->each(function (User $user) {
            Order::whereNull('confirmed_by')
                ->where('conferrer_id', $user->fb_id)
                ->update(['confirmed_by' => $user->id]);
        });
    }
}
