<?php

namespace Modules\SimGateway\Enums;

enum MessageDirection: string
{
    case Outbound = 'outbound';
    case Inbound = 'inbound';

    public function label(): string
    {
        return match ($this) {
            self::Outbound => 'Outbound',
            self::Inbound => 'Inbound',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Outbound => 'info',
            self::Inbound => 'success',
        };
    }
}
