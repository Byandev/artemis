<?php

namespace App\Notifications\Concerns;

/**
 * The addresses copied on invoice email, shared by everything that sends one.
 */
trait CopiesInvoiceEmail
{
    /**
     * Addresses copied on every invoice, so the business keeps its own record
     * of what went out. Comma separated in config for more than one inbox.
     *
     * A malformed entry is dropped rather than allowed to fail the send — a
     * typo in an internal copy must not stop the customer getting their bill.
     *
     * @return array<int, string>
     */
    protected function copies(object $notifiable): array
    {
        $configured = array_map('trim', explode(',', (string) config('invoice.cc')));

        // Not every notifiable answers this — don't assume one that does.
        $to = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail')
            : null;

        $to = is_string($to) ? strtolower($to) : null;

        return array_values(array_filter(
            $configured,
            fn (string $email) => filter_var($email, FILTER_VALIDATE_EMAIL)
                // Never copy the address it is already addressed to, or the
                // recipient gets the same mail twice over.
                && strtolower($email) !== $to
        ));
    }
}
