<?php

namespace Modules\Botcake\Console;

use App\Models\Page;
use Illuminate\Console\Command;
use Modules\Botcake\Jobs\FetchFlows;

class TriggerFetchFlows extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trigger-fetch-flows';

    /**
     * The console command description.
     */
    protected $description = 'Command description.';

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Page::whereNotNull('botcake_token')->get()->each(function ($page) {
            dispatch(new FetchFlows($page))->onQueue('botcake');
        });
    }

    /**
     * Get the console command arguments.
     */
    protected function getArguments(): array
    {
        return [];
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [

        ];
    }
}
