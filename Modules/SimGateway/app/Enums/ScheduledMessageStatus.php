<?php

namespace Modules\SimGateway\Enums;

enum ScheduledMessageStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'muted',
        };
    }
}
