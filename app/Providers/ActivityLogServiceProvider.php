<?php

namespace App\Providers;

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Enums\Logging\TriggerType;
use App\Facades\Activity;
use App\Listeners\LogAuthenticationActivity;
use App\Models\Page;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Observers\ActivityLogObserver;
use Illuminate\Console\Events\ScheduledTaskFailed;
use Illuminate\Console\Events\ScheduledTaskFinished;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Modules\Botcake\Models\Sequence;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\MetaAds\Models\OptimizationRule;
use Modules\MetaAds\Models\Report;

/**
 * Central wiring for automatic activity logging:
 *   - a generic observer on curated domain models (data create/update/delete);
 *   - the authentication event subscriber (login/logout/failed/…);
 *   - queue + scheduler listeners so job failures are always captured.
 *
 * This keeps "log everything that mutates state" in one place instead of
 * scattering Activity:: calls across every controller.
 */
class ActivityLogServiceProvider extends ServiceProvider
{
    /**
     * Domain models whose writes should be audited. Add to this list to extend
     * coverage; deliberately curated to avoid logging noisy internal/pivot
     * tables (sessions, tokens, sync bookkeeping).
     *
     * @var array<int, class-string>
     */
    private array $observedModels = [
        Page::class,
        Product::class,
        Shop::class,
        Workspace::class,
        Role::class,
        Team::class,
        User::class,
        InventoryItem::class,
        PurchasedOrder::class,
        Account::class,
        Transaction::class,
        OptimizationRule::class,
        Report::class,
        Sequence::class,
    ];

    public function boot(): void
    {
        foreach ($this->observedModels as $model) {
            if (class_exists($model)) {
                $model::observe(ActivityLogObserver::class);
            }
        }

        Event::subscribe(LogAuthenticationActivity::class);

        $this->registerJobListeners();
    }

    /** Queue + scheduler failure/health logging. */
    private function registerJobListeners(): void
    {
        Event::listen(JobFailed::class, function (JobFailed $event) {
            Activity::systemEvent(LogCategory::ScheduledJob, 'queue.job.failed', [
                'status' => LogStatus::Failure,
                'trigger' => TriggerType::Event,
                'job_name' => $event->job->resolveName(),
                'message' => 'Queued job failed',
                'error' => $event->exception,
                'metadata' => ['connection' => $event->connectionName],
            ]);
        });

        Event::listen(ScheduledTaskFinished::class, function (ScheduledTaskFinished $event) {
            Activity::systemEvent(LogCategory::ScheduledJob, 'scheduled.task.finished', [
                'status' => LogStatus::Success,
                'trigger' => TriggerType::Scheduled,
                'schedule' => $event->task->expression ?? null,
                'job_name' => $this->taskName($event->task),
                'message' => 'Scheduled task completed',
                'metadata' => ['runtime_ms' => isset($event->runtime) ? (int) round($event->runtime * 1000) : null],
            ]);
        });

        Event::listen(ScheduledTaskFailed::class, function (ScheduledTaskFailed $event) {
            Activity::systemEvent(LogCategory::ScheduledJob, 'scheduled.task.failed', [
                'status' => LogStatus::Failure,
                'trigger' => TriggerType::Scheduled,
                'schedule' => $event->task->expression ?? null,
                'job_name' => $this->taskName($event->task),
                'message' => 'Scheduled task failed',
                'error' => $event->exception,
            ]);
        });
    }

    private function taskName(mixed $task): ?string
    {
        return $task->command ?? $task->description ?? null;
    }
}
