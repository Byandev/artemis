<?php

namespace App\Console\Commands;

use App\Models\Invoice;
use App\Support\InvoiceMailer;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * Chases unpaid invoices as their due date approaches.
 *
 * Fires at each offset in config('invoice.reminder_days') — five days out,
 * three days out, then the due date itself. Each fires once per invoice and no
 * more; what guarantees that is the `reminders_sent` list on the invoice, not
 * the fact that a given day only comes round once.
 */
class SendInvoiceDueReminders extends Command
{
    protected $signature = 'invoices:send-due-reminders
                            {--date= : Treat this date as today, for catching up a missed run}
                            {--dry-run : List who would be reminded without sending or writing}';

    protected $description = 'Email a reminder for every unpaid invoice approaching or reaching its due date';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $today = $this->option('date')
            ? Carbon::parse($this->option('date'))->startOfDay()
            : Carbon::today();

        /** @var array<int, int> $offsets */
        $offsets = config('invoice.reminder_days');

        if (! $offsets) {
            $this->info('No reminder days configured — nothing to send.');

            return self::SUCCESS;
        }

        // Which due date each offset is asking about today, keyed by offset so
        // a matched invoice can say which reminder it is owed.
        $wanted = [];

        foreach ($offsets as $offset) {
            $wanted[$offset] = $today->copy()->addDays($offset)->toDateString();
        }

        $due = Invoice::query()
            // Drafts were never issued, and paid ones are settled. Only an
            // invoice that was sent and is still outstanding gets chased.
            ->where('status', Invoice::STATUS_SENT)
            ->whereIn('due_date', array_values($wanted))
            ->with('workspace.owner')
            ->orderBy('due_date')
            ->orderBy('id')
            ->get();

        $sent = 0;
        $failed = 0;

        foreach ($due as $invoice) {
            $offset = array_search($invoice->due_date->toDateString(), $wanted, true);

            if ($offset === false) {
                continue;
            }

            $already = $invoice->reminders_sent ?? [];

            // Each stage speaks once. A missed run caught up later must not
            // repeat a stage the payer has already had.
            if (in_array($offset, $already, true)) {
                continue;
            }

            $name = $invoice->workspace?->name ?? "workspace #{$invoice->workspace_id}";
            $stage = $offset === 0 ? 'due today' : "{$offset} days out";

            if ($dryRun) {
                $this->line(sprintf(
                    '  would remind %s — %s, %s%s (%s)',
                    $name,
                    $invoice->number,
                    config('invoice.currency_symbol'),
                    number_format((float) $invoice->total, 2),
                    $stage
                ));
                $sent++;

                continue;
            }

            $sentTo = InvoiceMailer::remind($invoice, $offset);

            if (! $sentTo) {
                $failed++;
                $this->error("  {$invoice->number}: could not send ({$stage}) — see the log.");

                continue;
            }

            // Recorded only on a send that actually went, so a failure is
            // retried by the next run rather than silently written off.
            $invoice->forceFill(['reminders_sent' => [...$already, $offset]])->save();
            $sent++;

            $this->line("  {$invoice->number} — {$name}, {$stage}, reminded {$sentTo}");
        }

        $this->info(sprintf(
            '%s %d reminder(s) for %s (%s days out). Failed %d.',
            $dryRun ? 'Would send' : 'Sent',
            $sent,
            $today->toDateString(),
            implode('/', $offsets),
            $failed
        ));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
