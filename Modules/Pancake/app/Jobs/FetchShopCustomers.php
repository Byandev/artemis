<?php

namespace Modules\Pancake\Jobs;

use App\Models\Shop;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Pancake\Services\Pancake;

class FetchShopCustomers implements ShouldQueue
{
    use \Illuminate\Foundation\Queue\Queueable;

    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public Shop $shop, public int $page_number, public int $startTime, public int $endTime)
    {

    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $this->shop->loadMissing('pages');

        $page_number = $this->page_number;

        $pancake = new Pancake($this->shop->id, $this->shop->pages->first()->pos_token);

        $response = $pancake->listCustomers("&page_size=100&page_number=$page_number&start_time_updated_at=$this->startTime&end_time_updated_at=$this->endTime");

        $totalPages = $response['total_pages'];

        $data = $response['data'];

        foreach ($data as $i => $customer) {
            dispatch(new SyncShopCustomer($this->shop, $customer))->delay(now()->addSeconds($i));
        }

        if ($totalPages > $this->page_number) {
            dispatch(new FetchShopCustomers($this->shop, $page_number + 1, $this->startTime, $this->endTime))->delay(now()->addSeconds(5));
        }
    }
}
