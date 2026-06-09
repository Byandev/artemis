<?php

namespace Modules\Pancake\Jobs;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pancake\Services\Pancake;

class FetchPageOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Page $page,
        public int $page_number,
        public int $startTime,
        public int $endTime,
        public bool $shipped = false,
    ) {}

    public function handle(): void
    {
        $page_number = $this->page_number;

        $pancake = new Pancake($this->page->shop_id, $this->page->pos_token);

        $params = "&page_size=100&page_number=$page_number&order_sources[]=-1&order_sources[]={$this->page->id}&updateStatus=updated_at&extra_fields[]=return_rate";

        if ($this->shipped) {
            $params .= '&filter_status[]=2';
        } else {
            $params .= "&startDateTime=$this->startTime&endDateTime=$this->endTime";
        }

        $response = $pancake->listProducts($params);

        $totalPages = $response['total_pages'];

        $data = $response['data'];

        foreach ($data as $i => $order) {
            dispatch(new SyncOrder($this->page->workspace, $this->page, $order))->delay(now()->addSeconds($i))->onQueue('pancake');
        }

        if ($totalPages > $this->page_number) {
            dispatch(new FetchPageOrders($this->page, $page_number + 1, $this->startTime, $this->endTime, $this->shipped))->delay(now()->addSecond(5))->onQueue('pancake');
        } elseif (! $this->shipped) {
            $this->page->update(['orders_last_synced_at' => Carbon::createFromTimestamp($this->endTime)->subMinute(1)]);
        }
    }
}
