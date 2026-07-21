<?php

namespace Modules\SimGateway\Enums;

enum SimCarrier: string
{
    case Globe = 'globe';
    case Smart = 'smart';
    case Dito = 'dito';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Globe => 'Globe',
            self::Smart => 'Smart',
            self::Dito => 'DITO',
            self::Other => 'Other',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Globe => 'info',
            self::Smart => 'success',
            self::Dito => 'warning',
            self::Other => 'muted',
        };
    }
}
