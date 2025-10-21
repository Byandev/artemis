<?php

namespace App\Console\Commands;

use App\Jobs\FetchPageOrders;
use App\Models\Page;
use Illuminate\Console\Command;

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
        $page = Page::find(745492068656489);
        dispatch(new FetchPageOrders($page, 1, \Carbon\Carbon::now()->startOfYear()->unix(), \Carbon\Carbon::now()->unix()));
    }
}
