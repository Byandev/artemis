<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Sample data for trying the renewal email by hand.
 *
 * Builds a workspace whose subscription ends exactly $days from today, so the
 * very next `invoices:generate-renewals` picks it up and sends a real email.
 * Everything it creates is tagged so `--cleanup` can take it all back out.
 */
class SeedRenewalDemo extends Command
{
    protected $signature = 'invoices:seed-renewal-demo
                            {email? : Where the invoice should be emailed (defaults to MAIL_FROM_ADDRESS)}
                            {--days=7 : Days from today the subscription should end}
                            {--due-in= : Instead, make an unpaid invoice falling due in N days (0 = today), for the reminder stages}
                            {--plan=growth : Plan code to put the workspace on}
                            {--cleanup : Delete everything a previous run of this command created}';

    protected $description = 'Create a workspace due to renew, or an unpaid invoice coming due, so the emails can be tried end to end';

    /** Tag on the slug, so cleanup can find its own leavings and nothing else. */
    private const TAG = 'renewal-demo';

    public function handle(): int
    {
        if (app()->isProduction()) {
            $this->error('This makes throwaway data and will not run in production.');

            return self::FAILURE;
        }

        if ($this->option('cleanup')) {
            return $this->cleanup();
        }

        $email = $this->argument('email') ?: config('mail.from.address');

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error("'{$email}' is not an email address.");

            return self::FAILURE;
        }

        $plan = SubscriptionPlan::where('code', $this->option('plan'))->first();

        if (! $plan) {
            $this->error("No plan with code '{$this->option('plan')}'.");

            return self::FAILURE;
        }

        if ((float) $plan->price_php <= 0) {
            $this->error("The {$plan->name} plan costs nothing, so it would never raise an invoice. Try --plan=growth.");

            return self::FAILURE;
        }

        if ($this->option('due-in') !== null) {
            return $this->seedInvoiceComingDue($email, $plan, (int) $this->option('due-in'));
        }

        $days = (int) $this->option('days');
        $endsOn = Carbon::today()->addDays($days);
        $slug = self::TAG.'-'.Str::lower(Str::random(6));

        DB::transaction(function () use ($email, $plan, $endsOn, $slug) {
            $owner = User::create([
                'name' => 'Renewal Demo Owner',
                'email' => $slug.'@example.test',
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            $workspace = Workspace::create([
                'name' => 'Renewal Demo '.Carbon::now()->format('M j, H:i'),
                'slug' => $slug,
                'owner_id' => $owner->id,
                // The invoice goes here, not to the throwaway owner address.
                'billing_email' => $email,
            ]);

            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $plan->id,
                'status' => Subscription::STATUS_ACTIVE,
                'current_period_start' => $endsOn->copy()->subMonth(),
                'current_period_end' => $endsOn,
            ]);
        });

        $this->newLine();
        $this->info('Created a workspace due to renew.');
        $this->line("  workspace    {$slug}");
        $this->line("  plan         {$plan->name} — ".config('invoice.currency_symbol').number_format((float) $plan->price_php, 2));
        $this->line("  renews on    {$endsOn->toDateString()}  ({$days} days from today)");
        $this->line("  invoice to   {$email}");
        $this->line('  due date     '.$endsOn->copy()->addDays((int) config('invoice.due_days'))->toDateString());

        $this->newLine();
        $this->comment('Next:');
        $this->line('  php artisan invoices:generate-renewals --dry-run   # check it is picked up');
        $this->line('  php artisan invoices:generate-renewals             # raise it and send the email');
        $this->line('  php artisan invoices:seed-renewal-demo --cleanup   # remove it afterwards');
        $this->newLine();

        return self::SUCCESS;
    }

    /**
     * An issued, unpaid invoice falling due in $inDays — the state the reminder
     * stages look for. Dated as a real renewal would be: the period ended a
     * week before the due date, so the workspace is already paused by then and
     * the email says so.
     */
    private function seedInvoiceComingDue(string $email, SubscriptionPlan $plan, int $inDays): int
    {
        $dueOn = Carbon::today()->addDays($inDays);
        $periodEnd = $dueOn->copy()->subDays((int) config('invoice.due_days'));
        $slug = self::TAG.'-'.Str::lower(Str::random(6));

        // Past due once the period has gone; still running if it hasn't.
        $status = $periodEnd->isPast()
            ? Subscription::STATUS_PAST_DUE
            : Subscription::STATUS_ACTIVE;

        $invoice = DB::transaction(function () use ($email, $plan, $dueOn, $periodEnd, $slug, $status) {
            $owner = User::create([
                'name' => 'Renewal Demo Owner',
                'email' => $slug.'@example.test',
                'password' => bcrypt(Str::random(32)),
                'email_verified_at' => now(),
            ]);

            $workspace = Workspace::create([
                'name' => 'Renewal Demo '.Carbon::now()->format('M j, H:i'),
                'slug' => $slug,
                'owner_id' => $owner->id,
                'billing_email' => $email,
            ]);

            Subscription::create([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $plan->id,
                'status' => $status,
                'current_period_start' => $periodEnd->copy()->subMonth(),
                'current_period_end' => $periodEnd,
            ]);

            $invoice = new Invoice([
                'workspace_id' => $workspace->id,
                'subscription_plan_id' => $plan->id,
                'bill_to_name' => 'Demo Payer',
                'bill_to_email' => $email,
                'issue_date' => $periodEnd->copy()->subDays(7),
                'due_date' => $dueOn,
                'period_end' => $periodEnd,
                'currency' => config('invoice.currency'),
                'tax_rate' => config('invoice.tax_rate'),
                'status' => Invoice::STATUS_SENT,
                'line_items' => [[
                    'description' => "{$plan->name} plan — renewal",
                    'quantity' => 1,
                    'unit_price' => (float) $plan->price_php,
                ]],
            ]);

            $invoice->number = Invoice::nextNumber((int) $dueOn->format('Y'));
            $invoice->recalculate();
            $invoice->save();

            return $invoice;
        });

        $stages = config('invoice.reminder_days');
        $willFire = in_array($inDays, $stages, true);

        $this->newLine();
        $this->info('Created an unpaid invoice coming due.');
        $this->line("  workspace    {$slug}  ({$status})");
        $this->line("  invoice      {$invoice->number} — ".config('invoice.currency_symbol').number_format((float) $invoice->total, 2));
        $this->line("  period ended {$periodEnd->toDateString()}");
        $this->line("  due          {$dueOn->toDateString()}  (in {$inDays} days)");
        $this->line("  reminder to  {$email}");
        $this->line('  stages       '.implode(', ', $stages).' days before due');

        $this->newLine();

        if (! $willFire) {
            $this->warn("  {$inDays} is not a reminder stage — nothing will fire today. Use --due-in=".implode(' or --due-in=', $stages).'.');
            $this->newLine();
        }

        $this->comment('Next:');
        $this->line('  php artisan invoices:send-due-reminders --dry-run');
        $this->line('  php artisan invoices:send-due-reminders            # sends the reminder email');
        $this->line('  php artisan invoices:seed-renewal-demo --cleanup   # remove it afterwards');
        $this->newLine();

        return self::SUCCESS;
    }

    private function cleanup(): int
    {
        $workspaces = Workspace::where('slug', 'like', self::TAG.'-%')->get();

        // Invoices and subscriptions go with the workspace via the FK cascade;
        // the owner is a separate row and has to be named directly.
        $removed = 0;
        $invoices = 0;

        foreach ($workspaces as $workspace) {
            $invoices += Invoice::where('workspace_id', $workspace->id)->count();

            $ownerId = $workspace->owner_id;
            $workspace->delete();
            User::where('id', $ownerId)->where('email', 'like', self::TAG.'-%@example.test')->delete();
            $removed++;
        }

        $this->info("Removed {$removed} demo workspace(s) and {$invoices} invoice(s).");

        return self::SUCCESS;
    }
}
