<?php

namespace Modules\GencysERP\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\GencysERP\Console\Commands\ExpireStaleSyncRuns;
use Modules\GencysERP\Console\Commands\ResolveSupersededSyncRuns;
use Modules\GencysERP\Console\Commands\SyncInventoryFromGencysOrders;
use Modules\GencysERP\Console\Commands\TriggerFetchDailySalesTrackerCommand;
use Modules\GencysERP\Console\Commands\TriggerFetchERPPurchaseOrders;
use Modules\GencysERP\Console\Commands\TriggerFetchERPTransactionHistory;
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
        TriggerFetchDailySalesTrackerCommand::class,
        TriggerFetchERPPurchaseOrders::class,
        TriggerFetchERPTransactionHistory::class,
        TriggerFetchInternDailyRecordsCommand::class,
        TriggerFetchPageDetailsCommand::class,
        ExpireStaleSyncRuns::class,
        ResolveSupersededSyncRuns::class,
        SyncInventoryFromGencysOrders::class,
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
