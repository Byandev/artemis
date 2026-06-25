<?php

namespace Modules\GencysERP\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\GencysERP\Console\Commands\TriggerFetchDailySalesTrackerCommand;
use Modules\GencysERP\Console\Commands\TriggerFetchUnitCodeCommand;
use Modules\GencysERP\Console\Commands\TriggerFetchUnitCodeInventoryCommand;
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
        TriggerFetchDailySalesTrackerCommand::class,
        TriggerFetchUnitCodeCommand::class,
        TriggerFetchUnitCodeInventoryCommand::class,
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
