<?php

namespace App\Enums;

/**
 * The third-party services a user can connect their own account to.
 *
 * The value is what lands in `user_integrations.service`, so it is a stable
 * slug rather than a display name — renaming the product must not orphan every
 * stored token.
 */
enum IntegrationService: string
{
    case Welle = 'welle';

    /** How the service is named in the interface. */
    public function label(): string
    {
        return match ($this) {
            self::Welle => 'Welle',
        };
    }
}
