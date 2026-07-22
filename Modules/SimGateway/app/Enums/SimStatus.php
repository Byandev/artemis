<?php

namespace Modules\SimGateway\Enums;

enum SimStatus: string
{
    case PendingShipment = 'pending_shipment';
    case Received = 'received';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingShipment => 'Pending shipment',
            self::Received => 'Received',
            self::Active => 'Active',
            self::Suspended => 'Suspended',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingShipment => 'warning',
            self::Received => 'info',
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Cancelled => 'danger',
        };
    }
}
