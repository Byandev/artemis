<?php

namespace Modules\Botcake\Console;

use Illuminate\Console\Command;
use Modules\Botcake\Jobs\FetchSequenceStatistics;
use Modules\Botcake\Models\Sequence;

class TriggerFetchSequenceStatistics extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'trigger-fetch-sequence-statistics';

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
        Sequence::get()
            ->each(function (Sequence $sequence) {
                dispatch(new FetchSequenceStatistics($sequence))->onQueue('botcake');
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
