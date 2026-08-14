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
     * Whether a plan change should raise an invoice.
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

        // Saving the same active plan again is a correction, not a second month
        // — an admin who reopens the modal and hits Save shouldn't double-bill.
        // Coming back from past_due or canceled starts a new period, so it does.
        return ! ($previous
            && (int) $previous->subscription_plan_id === (int) $plan->id
            && $previous->status === Subscription::STATUS_ACTIVE);
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
     * Build and save the invoice for a plan change, matching what an admin
     * filling in the invoice form by hand would have produced.
     */
    public static function raise(Workspace $workspace, SubscriptionPlan $plan, bool $fromTrial): Invoice
    {
        $today = Carbon::now()->startOfDay();
        $owner = $workspace->owner;

        $invoice = new Invoice([
            'workspace_id' => $workspace->id,
            'subscription_plan_id' => $plan->id,
            'bill_to_name' => $owner?->name ?? $workspace->name,
            // The workspace's billing address if it has one, the owner if not.
            'bill_to_email' => $workspace->billingEmail(),
            'issue_date' => $today->copy(),
            // Nothing is left to wait for when a trial ends — the workspace has
            // had its free run already, so the bill is due the same day.
            'due_date' => $fromTrial
                ? $today->copy()
                : $today->copy()->addDays((int) config('invoice.due_days')),
            'currency' => config('invoice.currency'),
            'tax_rate' => config('invoice.tax_rate'),
            'status' => Invoice::STATUS_SENT,
            'line_items' => [[
                'description' => "{$plan->name} plan — subscription",
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
