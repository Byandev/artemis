<?php

namespace Modules\SimGateway\Enums;

enum MessageStatus: string
{
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sending => 'Sending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Received => 'Received',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Queued => 'muted',
            self::Sending => 'info',
            self::Sent => 'info',
            self::Delivered => 'success',
            self::Failed => 'danger',
            self::Received => 'success',
        };
    }
}
