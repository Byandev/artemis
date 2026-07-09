<?php

namespace App\Services\Sms;

/**
 * A text-message provider that can deliver a parcel-journey SMS. Implementations
 * hold their own per-page credentials and normalise the response into an
 * {@see SmsSendResult}. Provider is chosen per page via {@see SmsProviderFactory}.
 */
interface SmsProvider
{
    /**
     * Send an SMS to a recipient mobile number.
     *
     * @param  string  $to  Recipient mobile number (as stored on the notification)
     * @param  string  $message  The message body
     */
    public function send(string $to, string $message): SmsSendResult;
}
