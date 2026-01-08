<?php

namespace App\Console\Commands;

use App\Jobs\FetchPageEmployees;
use App\Models\CustomerServiceRepresentative;
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
        Page::all()
            ->each(function (Page $page) {
                dispatch(new FetchPageEmployees($page));
            });
    }
}
