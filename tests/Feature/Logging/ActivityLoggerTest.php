<?php

use App\Enums\Logging\LogCategory;
use App\Enums\Logging\LogStatus;
use App\Enums\Logging\LogType;
use App\Enums\Logging\TriggerType;
use App\Facades\Activity;
use App\Models\ActivityLog;
use App\Models\User;
use App\Queries\ActivityLogMonitoringQuery;
use App\Support\Logging\LogsJobActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('records a user activity log with auth + request context', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    Activity::userActivity(
        LogCategory::Auth,
        'login',
        LogStatus::Success,
        'User logged in',
        ['guard' => 'web'],
    );

    $log = ActivityLog::query()->latest('id')->first();

    expect($log->log_type)->toBe(LogType::User)
        ->and($log->category)->toBe(LogCategory::Auth)
        ->and($log->action_type)->toBe('login')
        ->and($log->status)->toBe(LogStatus::Success)
        ->and($log->user_id)->toBe($user->id)
        ->and($log->metadata)->toMatchArray(['guard' => 'web']);
});

test('records a system event with job + schedule context', function () {
    Activity::systemEvent(LogCategory::ScheduledJob, 'fetch-erp-inventory', [
        'trigger' => TriggerType::Scheduled,
        'schedule' => '0 9,12,17 * * *',
        'job_name' => 'App\\Jobs\\FetchErpInventory',
        'message' => 'Fetched 120 items',
    ]);

    $log = ActivityLog::query()->latest('id')->first();

    expect($log->log_type)->toBe(LogType::System)
        ->and($log->trigger_type)->toBe(TriggerType::Scheduled)
        ->and($log->schedule)->toBe('0 9,12,17 * * *')
        ->and($log->job_name)->toBe('App\\Jobs\\FetchErpInventory')
        ->and($log->user_id)->toBeNull();
});

test('captures an exception as a failure with error detail', function () {
    Activity::failure(
        LogCategory::Integration,
        'sync-pancake-orders',
        new RuntimeException('API timed out'),
    );

    $log = ActivityLog::query()->failures()->latest('id')->first();

    expect($log->status)->toBe(LogStatus::Failure)
        ->and($log->message)->toBe('API timed out')
        ->and($log->error_detail)->toContain('API timed out')
        ->and($log->metadata)->toMatchArray(['exception' => RuntimeException::class]);
});

test('LogsJobActivity logs success and re-throws on failure', function () {
    $job = new class
    {
        use LogsJobActivity;

        public function ok(): string
        {
            return $this->withActivityLog('demo-ok', fn () => 'done');
        }

        public function boom(): void
        {
            $this->withActivityLog('demo-boom', function () {
                throw new RuntimeException('kaboom');
            });
        }
    };

    expect($job->ok())->toBe('done');

    expect(fn () => $job->boom())->toThrow(RuntimeException::class, 'kaboom');

    expect(ActivityLog::query()->where('action_type', 'demo-ok')->where('status', LogStatus::Success->value)->exists())->toBeTrue()
        ->and(ActivityLog::query()->where('action_type', 'demo-boom')->failures()->exists())->toBeTrue();
});

test('monitoring queries surface failures and summaries', function () {
    $user = User::factory()->create();

    Activity::build()->asUser()->user($user)->category(LogCategory::Auth)
        ->action('login')->status(LogStatus::Success)->save();

    Activity::failure(LogCategory::ScheduledJob, 'nightly-sync', 'boom')
        && ActivityLog::query()->latest('id')->first()->update(['log_type' => LogType::System, 'job_name' => 'NightlySync']);

    $monitor = new ActivityLogMonitoringQuery;

    expect($monitor->recentFailures(24))->toHaveCount(1)
        ->and($monitor->hasRecentFailures(60))->toBeTrue()
        ->and($monitor->userAuditTrail($user->id)->total())->toBeGreaterThanOrEqual(1)
        ->and($monitor->dailyActivitySummary(7))->not->toBeEmpty();
});
