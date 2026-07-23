<?php

namespace App\Services\Sms;

/**
 * Normalised outcome of an SMS send, so the parcel-journey jobs don't have to
 * know provider-specific response shapes. There are three outcomes:
 *
 * - accepted: the provider took the message. If `tracksDelivery` is true the
 *   caller should poll for delivery status; if `awaitsCallback` is true the
 *   provider will push the final status to us later (record the id and stay
 *   pending); otherwise the message is treated as sent immediately.
 * - rejected: the provider answered but declined the message (e.g. bad number).
 *   The notification stays pending and `remarks` is stored for debugging.
 * - failed: the request itself failed (non-2xx). The notification is marked
 *   failed.
 */
final class SmsSendResult
{
    private function __construct(
        public bool $accepted,
        public bool $failed,
        public ?string $messageId,
        public bool $tracksDelivery,
        public ?string $remarks,
        public bool $awaitsCallback = false,
    ) {}

    public static function accepted(?string $messageId, bool $tracksDelivery, ?string $remarks): self
    {
        return new self(
            accepted: true,
            failed: false,
            messageId: $messageId,
            tracksDelivery: $tracksDelivery,
            remarks: $remarks,
        );
    }

    /**
     * Accepted, but the provider reports the final status asynchronously via a
     * push callback (e.g. the SIM Gateway device). Record the id and stay
     * pending; the callback flips the notification to sent/failed.
     */
    public static function acceptedAwaitingCallback(?string $messageId): self
    {
        return new self(
            accepted: true,
            failed: false,
            messageId: $messageId,
            tracksDelivery: false,
            remarks: null,
            awaitsCallback: true,
        );
    }

    public static function rejected(?string $remarks): self
    {
        return new self(
            accepted: false,
            failed: false,
            messageId: null,
            tracksDelivery: false,
            remarks: $remarks,
        );
    }

    public static function failed(?string $remarks): self
    {
        return new self(
            accepted: false,
            failed: true,
            messageId: null,
            tracksDelivery: false,
            remarks: $remarks,
        );
    }
}
