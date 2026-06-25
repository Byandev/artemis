<?php

namespace Modules\MetaAds\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\MetaAds\Console\Commands\CaptureBudgetSnapshotsCommand;
use Modules\MetaAds\Console\Commands\DiscoverSystemAccountsCommand;
use Modules\MetaAds\Console\Commands\EvaluateEntityMonitorCommand;
use Modules\MetaAds\Console\Commands\EvaluateOptimizationRulesCommand;
use Modules\MetaAds\Console\Commands\ReportPageBudgetsToDiscordCommand;
use Modules\MetaAds\Console\Commands\ReportTeamBudgetsToDiscordCommand;
use Modules\MetaAds\Console\Commands\ReportUserBudgetsToDiscordCommand;
use Modules\MetaAds\Console\Commands\SyncAdAccountsCommand;
use Modules\MetaAds\Console\Commands\SyncAdsCommand;
use Modules\MetaAds\Console\Commands\SyncAdSetsCommand;
use Modules\MetaAds\Console\Commands\SyncAllCommand;
use Modules\MetaAds\Console\Commands\SyncCampaignsCommand;
use Modules\MetaAds\Console\Commands\SyncCreativesCommand;
use Modules\MetaAds\Console\Commands\SyncInsightsCommand;
use Nwidart\Modules\Support\ModuleServiceProvider;

class MetaAdsServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'MetaAds';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'metaads';

    /**
     * Command classes to register.
     *
     * @var string[]
     */
    protected array $commands = [
        SyncAdAccountsCommand::class,
        SyncCampaignsCommand::class,
        SyncAdSetsCommand::class,
        SyncAdsCommand::class,
        SyncCreativesCommand::class,
        SyncInsightsCommand::class,
        SyncAllCommand::class,
        CaptureBudgetSnapshotsCommand::class,
        DiscoverSystemAccountsCommand::class,
        EvaluateOptimizationRulesCommand::class,
        EvaluateEntityMonitorCommand::class,
        ReportPageBudgetsToDiscordCommand::class,
        ReportUserBudgetsToDiscordCommand::class,
        ReportTeamBudgetsToDiscordCommand::class,
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
