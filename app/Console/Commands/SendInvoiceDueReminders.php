<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Support\InvoiceMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Reminds the payer, on the day an unpaid invoice falls due, that it is due.
 *
 * One reminder per invoice and no more — no weekly chasing after that. What
 * guarantees the "no more" is `due_reminder_sent_at` on the invoice, not the
 * fact that today only happens once.
 */
class SendInvoiceDueReminders extends Command
{
    protected $signature = 'invoices:send-due-reminders
                            {--date= : Treat this date as today, for catching up a missed run}
                            {--dry-run : List who would be reminded without sending or writing}';

    protected $description = 'Email a reminder for every unpaid invoice falling due today';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        $due = Invoice::query()
            // Drafts were never issued, and paid ones are settled. Only an
            // invoice that was sent and is still outstanding gets chased.
            ->where('status', Invoice::STATUS_SENT)
            ->whereDate('due_date', $today)
            ->whereNull('due_reminder_sent_at')
            ->with('workspace.owner')
            ->orderBy('id')
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($due as $invoice) {
            $name = $invoice->workspace?->name ?? "workspace #{$invoice->workspace_id}";

            if ($dryRun) {
                $this->line(sprintf(
                    '  would remind %s — %s, %s%s',
                    $name,
                    $invoice->number,
                    config('invoice.currency_symbol'),
                    number_format((float) $invoice->total, 2)
                ));
                $sent++;

                continue;
            }

            $sentTo = InvoiceMailer::remind($invoice);

            if (! $sentTo) {
                $failed++;
                $this->error("  {$invoice->number}: could not send — see the log.");

                continue;
            }

            // Stamped only on a send that actually went, so a failure is
            // retried by the next run rather than silently written off.
            $invoice->forceFill(['due_reminder_sent_at' => Carbon::now()])->save();
            $sent++;

            $this->line("  {$invoice->number} — {$name}, reminded {$sentTo}");
        }

        $this->info(sprintf(
            '%s %d due-date reminder(s) for %s. Failed %d.',
            $dryRun ? 'Would send' : 'Sent',
            $sent,
            $today->toDateString(),
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
