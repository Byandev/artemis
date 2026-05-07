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
    protected $signature = 'botcake:trigger-fetch-flow-statistics';

    /**
     * The console command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['trigger-fetch-flow-statistics'];

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
        $delaySeconds = 0;

        Flow::query()
            ->chunkById(200, function ($flows) use (&$delaySeconds) {
                foreach ($flows as $flow) {
                    dispatch(new FetchFlowStatistics($flow))
                        ->delay(now()->addSeconds($delaySeconds))
                        ->onQueue('botcake');

                    $delaySeconds++;
                }
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
