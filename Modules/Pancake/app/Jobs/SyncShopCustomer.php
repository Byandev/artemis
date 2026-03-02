<?php

namespace Modules\Pancake\Jobs;

use App\Models\Shop;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Pancake\Models\Customer;

class SyncShopCustomer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct(public Shop $shop, public array $data) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Customer::updateOrCreate([
            'id' => $this->data['id'],
        ], [
            'shop_id' => $this->shop->id,
            'customer_id' => $this->data['customer_id'],
            'name' => $this->data['name'],
            'fb_id' => $this->data['fb_id'],
            'returned_order_count' => $this->data['returned_order_count'],
            'success_order_count' => $this->data['succeed_order_count'],
            'gender' => $this->data['gender'],
            'date_of_birth' => $this->data['date_of_birth'],
            'purchased_amount' => $this->data['purchased_amount'],
            'created_at' => $this->data['inserted_at'],
            'updated_at' => $this->data['updated_at'],
        ]);
    }
}
