<?php

namespace Modules\Pancake\Jobs;

use App\Models\Shop;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Modules\Pancake\Services\Pancake;

class FetchShopOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function __construct(
        public Shop $shop,
        public int $page_number,
        public int $startTime,
        public int $endTime,
        public bool $shipped = false,
    ) {}

    public function handle(): void
    {
        $page_number = $this->page_number;

        $this->shop->loadMissing('pages');

        $token = $this->shop->pages->firstWhere(fn ($page) => filled($page->pos_token))?->pos_token;

        if (! $token) {
            return;
        }

        $pancake = new Pancake($this->shop->id, $token);

        // No order_sources filter: pull every source for the shop — Facebook (-1),
        // Webcake (-7), and all pages — so source-less Webcake orders are captured.
        $params = "&page_size=100&page_number=$page_number&updateStatus=updated_at&extra_fields[]=return_rate";

        if ($this->shipped) {
            $params .= '&filter_status[]=2';
        } else {
            $params .= "&startDateTime=$this->startTime&endDateTime=$this->endTime";
        }

        $response = $pancake->listProducts($params);

        $totalPages = $response['total_pages'];

        $data = $response['data'];

        foreach ($data as $i => $order) {
            // Match the Pancake order's page_id to a local page in this shop. Webcake
            // orders have no page_id, so $page stays null and SyncOrder skips parcel
            // notifications for them while still persisting the order.
            $page = $order['page_id']
                ? $this->shop->pages->firstWhere('id', $order['page_id'])
                : null;

            dispatch(new SyncOrder($this->shop->workspace, $page, $order))
                ->delay(now()->addSeconds($i))
                ->onQueue('pancake');
        }

        if ($totalPages > $this->page_number) {
            dispatch(new FetchShopOrders($this->shop, $page_number + 1, $this->startTime, $this->endTime, $this->shipped))
                ->delay(now()->addSecond(5))
                ->onQueue('pancake');
        } elseif (! $this->shipped) {
            $this->shop->update(['orders_last_synced_at' => Carbon::createFromTimestamp($this->endTime)->subMinute(1)]);
        }
    }
}
