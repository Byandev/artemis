<?php

use App\Mail\SubscriptionDueReminderMail;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

function subscriptionDueIn(Workspace $workspace, int $days, string $status = Subscription::STATUS_ACTIVE): Subscription
{
    $due = Carbon::today()->addDays($days);

    return Subscription::create([
        'workspace_id' => $workspace->id,
        'subscription_plan_id' => test()->plan->id,
        'status' => $status,
        'current_period_start' => Carbon::today()->subMonth(),
        // current_period_end is NOT NULL, and AdminWorkspaceController sets both
        // for a trial — so a trial carries the same date in each. The command
        // still matches trials on trial_ends_at, keyed off status.
        'current_period_end' => $due,
        'trial_ends_at' => $status === Subscription::STATUS_TRIALING ? $due : null,
    ]);
}

beforeEach(function () {
    Mail::fake();
    // The array cache store outlives a single test and subscription ids restart
    // under RefreshDatabase, so the de-dupe key can otherwise collide.
    Cache::flush();

    $this->owner = User::factory()->create(['email' => 'owner@example.com']);
    $this->plan = SubscriptionPlan::where('code', SubscriptionPlan::CODE_STARTER)->firstOrFail();
});

function workspaceFor(User $owner): Workspace
{
    return Workspace::factory()->create(['owner_id' => $owner->id]);
}

it('emails a workspace whose subscription is due in 5 days', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 5);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSent(SubscriptionDueReminderMail::class, function ($mail) {
        return $mail->daysUntilDue === 5 && $mail->hasTo('owner@example.com');
    });
});

it('emails at 3 days out', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 3);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->daysUntilDue === 3);
});

it('emails on the due date itself', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 0);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->daysUntilDue === 0);
});

it('stays silent on offsets it does not cover', function (int $days) {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, $days);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertNothingSent();
})->with([1, 2, 4, 6, 10, 30]);

it('uses trial_ends_at for a trialing subscription', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 3, Subscription::STATUS_TRIALING);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->daysUntilDue === 3);
});

it('ignores statuses that are genuinely finished', function (string $status) {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 3, $status);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertNothingSent();
})->with([
    Subscription::STATUS_CANCELED,
    Subscription::STATUS_EXPIRED,
]);

it('still chases a past_due subscription', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 3, Subscription::STATUS_PAST_DUE);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    // Overdue is the case most worth reminding about, not least.
    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->daysUntilDue === 3);
});

it('prefers the workspace billing email over the owner', function () {
    $workspace = workspaceFor($this->owner);
    $workspace->billingDetail()->create(['billing_email' => 'accounts@example.com']);
    subscriptionDueIn($workspace, 5);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->hasTo('accounts@example.com'));
});

it('does not email the same subscription twice on the same day', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 5);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();
    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSentCount(1);
});

it('sends each offset separately as the date approaches', function () {
    $workspace = workspaceFor($this->owner);
    // Due in 5 days today; the same subscription is 3 days out two days later.
    subscriptionDueIn($workspace, 5);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Carbon::setTestNow(Carbon::now()->addDays(2));
    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();
    Carbon::setTestNow();

    Mail::assertSentCount(2);
});

it('sends nothing on a dry run and leaves the day unclaimed', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 5);

    $this->artisan('subscriptions:send-due-reminders --dry-run')->assertSuccessful();
    Mail::assertNothingSent();

    // A dry run must not claim it, or the real run would skip.
    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();
    Mail::assertSentCount(1);
});

it('accepts custom offsets', function () {
    $workspace = workspaceFor($this->owner);
    subscriptionDueIn($workspace, 7);

    $this->artisan('subscriptions:send-due-reminders --days=7')->assertSuccessful();

    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->daysUntilDue === 7);
});

it('covers several workspaces in one run', function () {
    $a = workspaceFor($this->owner);
    $other = User::factory()->create(['email' => 'second@example.com']);
    $b = workspaceFor($other);

    subscriptionDueIn($a, 5);
    subscriptionDueIn($b, 3);

    $this->artisan('subscriptions:send-due-reminders')->assertSuccessful();

    Mail::assertSentCount(2);
    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->hasTo('owner@example.com'));
    Mail::assertSent(SubscriptionDueReminderMail::class, fn ($mail) => $mail->hasTo('second@example.com'));
});

it('renders the workspace name, due date and plan in the email', function () {
    $workspace = workspaceFor($this->owner);
    $subscription = subscriptionDueIn($workspace, 5);

    $mail = new SubscriptionDueReminderMail($subscription->fresh(['workspace', 'plan']), 5);

    expect($mail->envelope()->subject)
        ->toBe("Subscription Notice — {$workspace->name} subscription is due in 5 days")
        ->and($mail->render())->toContain($workspace->name)
        ->and($mail->render())->toContain(Carbon::today()->addDays(5)->format('F j, Y'));
});
