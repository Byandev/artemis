<?php

namespace App\Support;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * Raising an invoice off an admin plan change.
 *
 * A workspace coming off the free trial is being asked to pay for a plan it has
 * already been running on, and the trial ending is the moment payment is owed —
 * so that invoice falls due the same day. Any other move onto a paid plan is a
 * fresh arrangement and gets the usual terms from config('invoice.due_days').
 */
class SubscriptionInvoice
{
    /**
     * Whether a plan change should raise an invoice on the spot.
     *
     * Only one case does: a workspace leaving the free trial. Every other move
     * onto a paid plan is a subscription that carries on, and those are billed
     * by `invoices:generate-renewals` seven days before the period ends — so
     * invoicing here as well would bill the same month twice.
     *
     * @param  Subscription|null  $previous  The subscription as it stood before
     *                                       the change, or null if there was none.
     */
    public static function shouldBill(?Subscription $previous, SubscriptionPlan $plan, string $status): bool
    {
        // Only a subscription that is live and payable gets billed. Putting a
        // workspace on trial, or marking one past due or cancelled, is not a sale.
        if ($status !== Subscription::STATUS_ACTIVE) {
            return false;
        }

        // The free trial is free and Enterprise is priced by hand off-system.
        // Both carry a price of 0, and neither should raise a ₱0 invoice.
        if ((float) $plan->price_php <= 0) {
            return false;
        }

        // This also settles double-billing: converting leaves the workspace
        // active rather than trialing, so a second Save is no longer "from
        // trial" and raises nothing.
        return static::isFromTrial($previous);
    }

    /**
     * Whether the workspace is arriving from the free trial.
     *
     * Either mark counts: the plan itself, and the trialing status the admin UI
     * pairs it with. A workspace put on trial against some other plan is in the
     * same position — using the product without having paid for it yet.
     */
    public static function isFromTrial(?Subscription $previous): bool
    {
        if (! $previous) {
            return false;
        }

        return $previous->status === Subscription::STATUS_TRIALING
            || $previous->plan?->code === SubscriptionPlan::CODE_FREE_TRIAL;
    }

    /**
     * Move the subscription on, now that a renewal invoice has been paid.
     *
     * The new period is anchored to the period the invoice covered, not to the
     * day the money arrived — otherwise a customer who pays four days late
     * drags their billing anniversary four days later every month until it has
     * wandered right across the calendar. Anchored this way it stays the 1st.
     *
     * @return Subscription|null The subscription that moved, or null if none did.
     */
    public static function settle(Invoice $invoice): ?Subscription
    {
        // Only renewals carry a period. An upgrade already set its own dates,
        // and a one-off invoice has nothing to do with the subscription.
        if (! $invoice->period_end) {
            return null;
        }

        $subscription = Subscription::where('workspace_id', $invoice->workspace_id)->first();

        if (! $subscription) {
            return null;
        }

        // Already past this period, so it has moved once. Marking the same
        // invoice paid again is a correction, not another month.
        if ($subscription->current_period_end?->gt($invoice->period_end)) {
            return null;
        }

        $start = $invoice->period_end->copy()->startOfDay();

        $subscription->update([
            'current_period_start' => $start,
            'current_period_end' => $start->copy()->addMonth(),
            'status' => Subscription::STATUS_ACTIVE,
            'trial_ends_at' => null,
        ]);

        return $subscription;
    }

    /**
     * When a plan change falls due.
     *
     * Nothing is left to wait for when a trial ends — the workspace has had its
     * free run already, so the bill is due the same day. Everyone else gets the
     * standard terms.
     */
    public static function upgradeDueDate(bool $fromTrial): Carbon
    {
        $today = Carbon::now()->startOfDay();

        return $fromTrial ? $today : $today->addDays((int) config('invoice.due_days'));
    }

    /**
     * Build and save a subscription invoice, matching what an admin filling in
     * the invoice form by hand would have produced.
     *
     * @param  Carbon|null  $periodEnd  The billing period this covers. Set only
     *                                  by the renewal run, where it doubles as
     *                                  the key that stops a second invoice for
     *                                  the same period.
     * @param  string  $note  Tail of the line-item description.
     */
    public static function raise(
        Workspace $workspace,
        SubscriptionPlan $plan,
        Carbon $dueDate,
        ?Carbon $periodEnd = null,
        string $note = 'subscription',
    ): Invoice {
        $today = Carbon::now()->startOfDay();
        $owner = $workspace->owner;

        $invoice = new Invoice([
            'workspace_id' => $workspace->id,
            'subscription_plan_id' => $plan->id,
            'bill_to_name' => $owner?->name ?? $workspace->name,
            // The workspace's billing address if it has one, the owner if not.
            'bill_to_email' => $workspace->billingEmail(),
            'issue_date' => $today->copy(),
            'due_date' => $dueDate->copy(),
            'period_end' => $periodEnd?->copy(),
            'currency' => config('invoice.currency'),
            'tax_rate' => config('invoice.tax_rate'),
            'status' => Invoice::STATUS_SENT,
            'line_items' => [[
                'description' => "{$plan->name} plan — {$note}",
                'quantity' => 1,
                'unit_price' => (float) $plan->price_php,
            ]],
        ]);

        $invoice->number = Invoice::nextNumber((int) $today->format('Y'));
        $invoice->recalculate();
        $invoice->save();

        return $invoice;
    }
}
