<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The password reset email.
 *
 * Extends the framework's notification rather than replacing it, so the token
 * handling, the signed reset URL, and the `createUrlUsing` / `toMailUsing`
 * hooks all still behave — only the wording is ours.
 *
 * Deliberately not queued: the request waits on the Brevo call so the link is
 * in the inbox by the time the form says it was sent, and a send that fails
 * fails now rather than dying quietly in a queue nobody is watching.
 */
class ResetPasswordNotification extends ResetPassword
{
    protected function buildMailMessage($url): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        return (new MailMessage)
            ->subject('Reset your '.config('app.name').' password')
            ->greeting('Hi there,')
            ->line('Someone asked to reset the password for this account. If that was you, use the button below to choose a new one.')
            ->action('Reset password', $url)
            ->line("This link expires in {$minutes} minutes and can only be used once.")
            // Said plainly, so a reset someone didn't ask for doesn't read as a
            // breach — an unused link changes nothing.
            ->line("If you didn't ask for this, you can ignore this email. Your password stays as it is.");
    }
}
