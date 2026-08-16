<?php

namespace App\Console\Commands;

use App\Mail\SubscriptionDueReminderMail;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendSubscriptionDueReminders extends Command
{
    protected $signature = 'subscriptions:send-due-reminders
                            {--days= : Comma-separated offsets to remind at (default 5,3,0)}
                            {--dry-run : List who would be emailed without sending anything}';

    protected $description = 'Email workspaces whose subscription falls due in 5 days, 3 days, or today';

    /** Days before the period ends that warrant a reminder. */
    private const DEFAULT_OFFSETS = [5, 3, 0];

    public function handle(): int
    {
        $offsets = $this->resolveOffsets();
        $dryRun = (bool) $this->option('dry-run');
        $today = Carbon::today();

        $sent = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($offsets as $days) {
            $target = $today->copy()->addDays($days);

            // A trial ends on trial_ends_at; everything else renews at
            // current_period_end — so the column to match depends on status.
            $due = Subscription::query()
                ->with(['workspace.owner:id,name,email', 'workspace.billingDetail', 'plan:id,name,price_php'])
                ->where(function ($q) use ($target) {
                    $q->where(fn ($t) => $t
                        ->where('status', Subscription::STATUS_TRIALING)
                        ->whereDate('trial_ends_at', $target))
                        // past_due is included on purpose: an overdue account is
                        // exactly the one worth chasing. Only canceled/expired
                        // are genuinely finished and get nothing.
                        ->orWhere(fn ($a) => $a
                            ->whereIn('status', [
                                Subscription::STATUS_ACTIVE,
                                Subscription::STATUS_PAST_DUE,
                            ])
                            ->whereDate('current_period_end', $target));
                })
                ->get();

            foreach ($due as $subscription) {
                $recipient = $this->recipientFor($subscription);

                if (! $recipient) {
                    $this->warn("#{$subscription->id}: no billing or owner email — skipped.");
                    $skipped++;

                    continue;
                }

                $label = $subscription->workspace?->name ?? "subscription #{$subscription->id}";

                if ($dryRun) {
                    $this->line("[{$days}d] {$label} → {$recipient}");

                    continue;
                }

                // Claim before sending so a re-run (or a second worker) can't
                // email twice; released again if the send itself fails.
                $key = "subscription-due-notice:{$subscription->id}:{$days}:{$target->toDateString()}";

                if (! Cache::add($key, true, $today->copy()->addDays(max($days, 1))->endOfDay())) {
                    $this->line("[{$days}d] {$label}: already notified — skipped.");
                    $skipped++;

                    continue;
                }

                try {
                    Mail::to($recipient)->send(new SubscriptionDueReminderMail($subscription, $days));
                    $this->info("[{$days}d] {$label} → {$recipient}");
                    $sent++;
                } catch (Throwable $e) {
                    // Let the next run retry rather than silently dropping it.
                    Cache::forget($key);
                    Log::error("Subscription due reminder failed for #{$subscription->id}: {$e->getMessage()}");
                    $this->error("[{$days}d] {$label}: {$e->getMessage()}");
                    $failed++;
                }
            }
        }

        if ($dryRun) {
            $this->info('Dry run — nothing sent.');

            return self::SUCCESS;
        }

        $this->info("Sent {$sent}, skipped {$skipped}, failed {$failed}.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Billing email first, falling back to the workspace owner's account email.
     */
    private function recipientFor(Subscription $subscription): ?string
    {
        return $subscription->workspace?->billingDetail?->billing_email
            ?: $subscription->workspace?->owner?->email;
    }

    /**
     * @return list<int>
     */
    private function resolveOffsets(): array
    {
        $raw = (string) ($this->option('days') ?? '');

        if (trim($raw) === '') {
            return self::DEFAULT_OFFSETS;
        }

        $offsets = collect(explode(',', $raw))
            ->map(fn ($d) => (int) trim($d))
            ->filter(fn ($d) => $d >= 0)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();

        return $offsets ?: self::DEFAULT_OFFSETS;
    }
}
