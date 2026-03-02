<?php

namespace Modules\Botcake\Console;

use Illuminate\Console\Command;
use Modules\Botcake\Jobs\FetchFlowStatistics;
use Modules\Botcake\Models\Flow;

class TriggerFetchFlowStatistics extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trigger-fetch-flow-statistics';

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
        Flow::limit(10)
            ->get()
            ->each(function (Flow $flow, $index) {
                dispatch(new FetchFlowStatistics($flow))->delay(now()->addSeconds($index))->onQueue('botcake');
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
        return [];
    }
}
