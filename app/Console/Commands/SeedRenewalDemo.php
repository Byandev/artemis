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
                            {--plan=growth : Plan code to put the workspace on}
                            {--cleanup : Delete everything a previous run of this command created}';

    protected $description = 'Create a workspace due to renew, so the renewal email can be tried end to end';

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
