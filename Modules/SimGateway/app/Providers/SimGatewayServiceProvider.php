<?php

namespace Modules\SimGateway\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Modules\SimGateway\Console\Commands\SendTestSmsCommand;
use Modules\SimGateway\Jobs\ProcessScheduledMessagesJob;
use Modules\SimGateway\Services\Gateway\GatewayInterface;
use Modules\SimGateway\Services\Gateway\StubGateway;
use Modules\SimGateway\Services\Gateway\YxGpGateway;
use Nwidart\Modules\Support\ModuleServiceProvider;

class SimGatewayServiceProvider extends ModuleServiceProvider
{
    /**
     * The name of the module.
     */
    protected string $name = 'SimGateway';

    /**
     * The lowercase version of the module name.
     */
    protected string $nameLower = 'simgateway';

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
     * Register the service provider.
     */
    public function register(): void
    {
        parent::register();

        $this->commands([
            SendTestSmsCommand::class,
        ]);

        // Bind the active SMS gateway driver. Resolved lazily, so module config
        // (merged under the "simgateway" key) is available by resolution time.
        $this->app->singleton(GatewayInterface::class, function ($app) {
            return match (config('simgateway.driver', 'stub')) {
                'yxgp' => new YxGpGateway(
                    host: (string) config('simgateway.yxgp.host'),
                    username: (string) config('simgateway.yxgp.username'),
                    password: (string) config('simgateway.yxgp.password'),
                    charset: (string) config('simgateway.yxgp.charset', 'utf8'),
                    timeoutSeconds: (int) config('simgateway.yxgp.timeout_seconds', 10),
                    verifyTls: (bool) config('simgateway.yxgp.verify_tls', true),
                ),
                default => new StubGateway,
            };
        });
    }

    /**
     * Define module schedules.
     */
    protected function configureSchedules(Schedule $schedule): void
    {
        $schedule->job(new ProcessScheduledMessagesJob)
            ->everyMinute()
            ->name('simgateway-scheduled-messages');
    }
}
