<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Moves an active subscription to past_due once its period has run out unpaid.
 *
 * This does not change what the customer can do — `Subscription::isLapsed()`
 * already treats an active subscription past its period end as lapsed, so they
 * are locked out either way. What it fixes is the admin's view: without it the
 * workspace list shows a green "Active" badge for someone who cannot log in.
 */
class MarkPastDueSubscriptions extends Command
{
    protected $signature = 'subscriptions:mark-past-due
                            {--dry-run : List what would change without writing anything}';

    protected $description = 'Mark active subscriptions as past due once their period has ended unpaid';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $lapsed = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('current_period_end', '<', Carbon::today())
            ->with('workspace:id,name')
            ->get();

        $marked = 0;
        $unbilled = 0;

        foreach ($lapsed as $subscription) {
            $name = $subscription->workspace?->name ?? "workspace #{$subscription->workspace_id}";
            $endedOn = $subscription->current_period_end->toDateString();

            /**
             * Never brand someone a non-payer when no bill ever reached them.
             * Renewal invoices are raised on one morning only, so a night the
             * scheduler missed leaves a period with nothing sent — locking that
             * customer out and calling them past due would be our error, not
             * theirs. Surface it instead and leave them alone.
             */
            if (! $this->wasBilledFor($subscription)) {
                $unbilled++;

                Log::warning('Subscription period ended with no invoice raised for it.', [
                    'workspace_id' => $subscription->workspace_id,
                    'period_end' => $endedOn,
                ]);

                $this->warn("  {$name}: period ended {$endedOn} but no invoice was ever raised — left active.");

                continue;
            }

            if ($dryRun) {
                $this->line("  would mark past due: {$name} (ended {$endedOn})");
                $marked++;

                continue;
            }

            $subscription->update(['status' => Subscription::STATUS_PAST_DUE]);
            $marked++;

            $this->line("  {$name}: past due (ended {$endedOn})");
        }

        $this->info(sprintf(
            '%s %d subscription(s) past due. %d left active for want of an invoice.',
            $dryRun ? 'Would mark' : 'Marked',
            $marked,
            $unbilled
        ));

        return self::SUCCESS;
    }

    /** Whether an invoice was ever raised covering the period that just ended. */
    private function wasBilledFor(Subscription $subscription): bool
    {
        return Invoice::where('workspace_id', $subscription->workspace_id)
            ->whereDate('period_end', $subscription->current_period_end)
            ->exists();
    }
}
