<?php

namespace Modules\MetaAds\Support;

/**
 * Meta's `account_status` codes for an ad account.
 *
 * Mirrors the ACCOUNT_STATUS map the ad-accounts table renders in
 * resources/js/pages/workspaces/integrations/meta-ad-accounts.tsx — keep the
 * labels and severity tiers in step so a Discord alert reads the same as the
 * UI badge.
 */
final class AccountStatus
{
    public const ACTIVE = 1;

    /** Delivery has stopped and someone has to act. Red badge in the UI. */
    public const ACTION = 'action';

    /** Billing or review states — still recoverable. Amber badge in the UI. */
    public const BILLING = 'billing';

    /** Closed, closing, or never reported. Grey badge in the UI. */
    public const DORMANT = 'dormant';

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
     * Severity tier per status code, matching the UI's badge colours. Anything
     * not listed falls to DORMANT — an unmapped code is not a known emergency,
     * so it is surfaced without crying wolf.
     *
     * @var array<int, string>
     */
    private const SEVERITIES = [
        2 => self::ACTION,
        3 => self::BILLING,
        7 => self::BILLING,
        8 => self::BILLING,
        9 => self::BILLING,
        100 => self::DORMANT,
        101 => self::DORMANT,
    ];

    /** Tiers worst-first, with the marker, wording and colour each reports under. */
    private const TIERS = [
        self::ACTION => ['emoji' => '🔴', 'label' => 'Action needed', 'color' => 0xED4245],
        self::BILLING => ['emoji' => '🟠', 'label' => 'Billing or review', 'color' => 0xE67E22],
        self::DORMANT => ['emoji' => '⚪', 'label' => 'Closed or never synced', 'color' => 0x99AAB5],
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

    /** Which tier a status reports under. */
    public static function severity(?int $status): string
    {
        if ($status === null) {
            return self::DORMANT;
        }

        return self::SEVERITIES[$status] ?? self::DORMANT;
    }

    /** Tier keys worst-first, for grouping and ordering. */
    public static function tiers(): array
    {
        return array_keys(self::TIERS);
    }

    /** Field heading: the marker plus the wording, e.g. "🔴 Action needed". */
    public static function heading(string $tier): string
    {
        return self::TIERS[$tier]['emoji'].' '.self::TIERS[$tier]['label'];
    }

    /** The wording alone, for prose that supplies its own punctuation. */
    public static function tierLabel(string $tier): string
    {
        return self::TIERS[$tier]['label'];
    }

    public static function emoji(string $tier): string
    {
        return self::TIERS[$tier]['emoji'];
    }

    /** Embed colour for a tier — the worst tier present sets the embed's colour. */
    public static function color(string $tier): int
    {
        return self::TIERS[$tier]['color'];
    }
}
