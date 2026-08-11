<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Support\InvoiceMailer;
use App\Support\SubscriptionInvoice;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bills each active subscription ahead of the day its period ends, so the
 * workspace has the invoice in hand before the renewal rather than after it.
 */
class GenerateRenewalInvoices extends Command
{
    protected $signature = 'invoices:generate-renewals
                            {--days= : Days of lead time (defaults to config invoice.renewal_lead_days)}
                            {--dry-run : List what would be raised without writing or emailing anything}';

    protected $description = 'Raise and email an invoice for every subscription renewing within the lead window';

    public function handle(): int
    {
        $leadDays = (int) ($this->option('days') ?? config('invoice.renewal_lead_days'));
        $dryRun = (bool) $this->option('dry-run');

        $today = Carbon::today();
        $renewalDay = $today->copy()->addDays($leadDays);

        /**
         * Exactly the day $leadDays out, not a range — a subscription is billed
         * on its seventh-day-before mark and on no other morning. The trade is
         * that a night the scheduler doesn't run is a renewal nobody bills;
         * `--days` is how you catch one up by hand.
         */
        $due = Subscription::query()
            ->where('status', Subscription::STATUS_ACTIVE)
            ->whereDate('current_period_end', $renewalDay)
            ->with(['workspace.owner', 'plan'])
            ->orderBy('current_period_end')
            ->get();

        $raised = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($due as $subscription) {
            $workspace = $subscription->workspace;
            $plan = $subscription->plan;
            $renewsOn = $subscription->current_period_end->copy()->startOfDay();

            if (! $workspace || ! $plan) {
                $skipped++;

                continue;
            }

            // Free trial and Enterprise both sit at 0 — nothing to bill.
            if ((float) $plan->price_php <= 0) {
                $skipped++;

                continue;
            }

            if ($this->alreadyBilled($subscription->workspace_id, $renewsOn)) {
                $skipped++;

                continue;
            }

            if ($dryRun) {
                $this->line(sprintf(
                    '  would bill %s — %s, renews %s',
                    $workspace->name,
                    $plan->name,
                    $renewsOn->toDateString()
                ));
                $raised++;

                continue;
            }

            try {
                // Renewal date plus the standard terms. Raised a week early so
                // it arrives before the period turns over, and payable for a
                // week after it does.
                $dueDate = $renewsOn->copy()->addDays((int) config('invoice.due_days'));

                $invoice = SubscriptionInvoice::raise($workspace, $plan, $dueDate, $renewsOn, 'renewal');
            } catch (UniqueConstraintViolationException) {
                // The index caught a race the check above could not — another
                // run got there first. Not an error, just already done.
                $skipped++;

                continue;
            } catch (Throwable $e) {
                $failed++;
                Log::error('Could not raise renewal invoice.', [
                    'workspace_id' => $workspace->id,
                    'renews_on' => $renewsOn->toDateString(),
                    'error' => $e->getMessage(),
                ]);
                $this->error("  {$workspace->name}: {$e->getMessage()}");

                continue;
            }

            // Never throws — a mail failure leaves the invoice standing, and
            // the line below says NOT EMAILED so it doesn't pass unnoticed.
            $sentTo = InvoiceMailer::send($invoice);
            $raised++;

            $this->line(sprintf(
                '  %s — %s, renews %s, %s',
                $invoice->number,
                $workspace->name,
                $renewsOn->toDateString(),
                $sentTo ? "emailed to {$sentTo}" : 'NOT EMAILED'
            ));
        }

        $this->info(sprintf(
            '%s %d renewal invoice(s) for subscriptions ending %s. Skipped %d, failed %d.',
            $dryRun ? 'Would raise' : 'Raised',
            $raised,
            $renewalDay->toDateString(),
            $skipped,
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** One invoice per workspace per renewal — `period_end` is what says so. */
    private function alreadyBilled(int $workspaceId, Carbon $renewsOn): bool
    {
        return Invoice::where('workspace_id', $workspaceId)
            ->whereDate('period_end', $renewsOn)
            ->exists();
    }
}
