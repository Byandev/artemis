<?php

namespace App\Jobs;

use App\Models\Page;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FetchPageOrders implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(public Page $page, public int $page_number, public int $startTime, public int $endTime)
    {
        $this->page->loadMissing('workspace');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $page_number = $this->page_number;

        Log::error('https://pos.pages.fm/api/v1/shops/'.$this->page->shop_id.'/orders?api_key='.$this->page->pos_token."&page_size=100&page_number=$page_number&order_sources[]=-1&order_sources[]={$this->page->id}&startDateTime=$this->startTime&endDateTime=$this->endTime&updateStatus=updated_at");

        $response = Http::get('https://pos.pages.fm/api/v1/shops/'.$this->page->shop_id.'/orders?api_key='.$this->page->pos_token."&page_size=100&page_number=$page_number&order_sources[]=-1&order_sources[]={$this->page->id}&startDateTime=$this->startTime&endDateTime=$this->endTime&updateStatus=updated_at")
            ->throw();

        $response = $response->json();

        $totalPages = $response['total_pages'];

        $data = $response['data'];

        foreach ($data as $i => $order) {
            dispatch(new SyncOrder($this->page->workspace, $this->page, $order))->delay(now()->addSeconds($i));
        }

        if ($totalPages > $this->page_number) {
            dispatch(new FetchPageOrders($this->page, $page_number + 1, $this->startTime, $this->endTime))->delay(now()->addSecond(5));
        } else {
            $this->page->update(['orders_last_synced_at' => Carbon::createFromTimestamp($this->endTime)->addHours(8)]);
        }
    }
}
