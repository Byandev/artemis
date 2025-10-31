<?php

namespace App\Console\Commands;

use App\Jobs\FetchPageOrders;
use App\Models\Page;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

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
        $page = Page::find(541830885691274);
        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::parse($page->orders_last_synced_at)->unix(), \Carbon\Carbon::now()->unix()));
    }
}
