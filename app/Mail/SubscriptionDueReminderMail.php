<?php

namespace App\Mail;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Reminder that a workspace subscription is coming up for renewal. Sent by
 * `subscriptions:send-due-reminders` at fixed offsets before the period ends.
 * Subject and body are generated, not admin-authored.
 */
class SubscriptionDueReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Subscription $subscription,
        public int $daysUntilDue = 0,
    ) {}

    public function envelope(): Envelope
    {
        $sellerEmail = config('invoice.seller.email');

        return new Envelope(
            subject: "Subscription Notice — {$this->workspaceName()} subscription {$this->duePhrase()}",
            // The copy asks the client to reply; without this that reply goes
            // to the raw MAIL_FROM address rather than the billing inbox.
            replyTo: $sellerEmail
                ? [new Address($sellerEmail, config('invoice.seller.name') ?: config('app.name'))]
                : [],
        );
    }

    /** "is due today" / "is due tomorrow" / "is due in 5 days". */
    public function duePhrase(): string
    {
        return match (true) {
            $this->daysUntilDue <= 0 => 'is due today',
            $this->daysUntilDue === 1 => 'is due tomorrow',
            default => "is due in {$this->daysUntilDue} days",
        };
    }

    public function workspaceName(): string
    {
        return $this->subscription->workspace?->name ?? 'Your workspace';
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscriptions.due-reminder',
            // Ships alongside the HTML: an HTML-only message scores worse with
            // spam filters and reads badly in text-only clients.
            text: 'emails.subscriptions.due-reminder-text',
            with: [
                'subscription' => $this->subscription,
                'workspaceName' => $this->workspaceName(),
                'duePhrase' => $this->duePhrase(),
                'daysUntilDue' => $this->daysUntilDue,
                'dueDate' => $this->subscription->dueDate(),
                'planName' => $this->subscription->plan?->name,
                'planPrice' => $this->subscription->plan?->price_php,
                'symbol' => config('invoice.currency_symbol'),
                'paymentInstructions' => config('invoice.payment_instructions'),
            ],
        );
    }
}
