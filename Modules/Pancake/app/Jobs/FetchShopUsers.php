<?php

namespace Modules\Pancake\Jobs;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Pancake\Models\ShopUser;
use Modules\Pancake\Models\User;
use Modules\Pancake\Services\Pancake;

class FetchShopUsers implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Shop $shop) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->shop->loadMissing('pages');

        $pancake = new Pancake($this->shop->id, $this->shop->pages->first()->pos_token);

        $response = $pancake->listUsers();

        collect($response['data'])->filter(function ($shopUser) {
            return isset($shopUser['user']['name'])
                && $shopUser['user']['name'] !== 'API_CONNECTION';
        })->each(function ($user) {
            User::updateOrCreate([
                'id' => $user['user']['id'],
            ], [
                'name' => $user['user']['name'],
                'email' => $user['user']['email'],
                'phone_number' => $user['user']['phone_number'],
                'fb_id' => $user['user']['fb_id'],
            ]);

            ShopUser::updateOrCreate([
                'id' => $user['id'],
            ], [
                'shop_id' => $this->shop->id,
                'user_id' => $user['user']['id'],
            ]);
        });

    }
}
