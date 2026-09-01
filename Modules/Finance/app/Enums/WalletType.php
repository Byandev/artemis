<?php

namespace Modules\Finance\Enums;

/**
 * Which slot a Go Tyme wallet fills for its holder. Only accounts flagged
 * `is_user_wallet` carry one; company accounts leave it null.
 */
enum WalletType: string
{
    case Main = 'main';
    case Backup = 'backup';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Main => 'Main',
            self::Backup => 'Backup',
        };
    }
}
