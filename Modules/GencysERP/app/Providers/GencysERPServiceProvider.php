<?php

namespace Modules\GencysERP\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\GencysERP\Console\Commands\ExpireStaleSyncRuns;
use Modules\GencysERP\Console\Commands\ResolveSupersededSyncRuns;
use Modules\GencysERP\Console\Commands\RunScheduledSyncBatch;
use Modules\GencysERP\Console\Commands\TriggerFetchGencysERPData;
use Modules\GencysERP\Console\Commands\TriggerFetchInternDailyRecordsCommand;
use Modules\GencysERP\Console\Commands\TriggerFetchPageDetailsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class GencysERPServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'GencysERP';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'gencyserp';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        RunScheduledSyncBatch::class,
        TriggerFetchGencysERPData::class,
        TriggerFetchInternDailyRecordsCommand::class,
        TriggerFetchPageDetailsCommand::class,
        ExpireStaleSyncRuns::class,
        ResolveSupersededSyncRuns::class,
    ];

    /**
     * Provider classes to register.
     *
     * @var string[]
     */
    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    /**
     * Define module schedules.
     *
     * @param  $schedule
     */
    // protected function configureSchedules(Schedule $schedule): void
    // {
    //     $schedule->command('inspire')->hourly();
    // }
}
