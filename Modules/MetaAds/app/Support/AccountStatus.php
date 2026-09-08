<?php

namespace Modules\MetaAds\Support;

/**
 * Meta's `account_status` codes for an ad account.
 *
 * Mirrors the ACCOUNT_STATUS map the ad-accounts table renders in
 * resources/js/pages/workspaces/integrations/meta-ad-accounts.tsx — keep the
 * labels in step so a Discord alert reads the same as the UI badge.
 */
final class AccountStatus
{
    public const ACTIVE = 1;

    /** @var array<int, string> */
    public const LABELS = [
        1 => 'Active',
        2 => 'Disabled',
        3 => 'Unsettled',
        7 => 'Pending Risk Review',
        8 => 'Pending Settlement',
        9 => 'In Grace Period',
        100 => 'Pending Closure',
        101 => 'Closed',
    ];

    /**
     * Human label for a status code. A null status means Meta has never
     * reported one (the account has not synced yet), and an unmapped code
     * means Meta added one we do not know about — both are surfaced rather
     * than hidden, since either still means "not confirmed active".
     */
    public static function label(?int $status): string
    {
        if ($status === null) {
            return 'Unknown';
        }

        return self::LABELS[$status] ?? "Status {$status}";
    }

    /**
     * Whether Meta currently reports the account as serving. A sync failure on
     * a non-active account usually has its cause right here — unsettled
     * billing, a grace period, a disabled account — rather than in whatever
     * the job happened to throw.
     */
    public static function isActive(?int $status): bool
    {
        return $status === self::ACTIVE;
    }
}
