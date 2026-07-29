<?php

namespace App\Support;

use App\Models\Workspace;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Auto-tagging keeps an RMO row's call status in step with the courier's parcel
 * status: once the parcel reports "delivered", the RMO status follows to
 * "DELIVERED" on its own, with nobody touching the row.
 *
 * The pairs are fixed — only the two that mean the same thing on both sides —
 * so a workspace's only choice is whether auto-tagging runs at all.
 *
 * The tagging itself is driven by the `rmo:apply-auto-tag` command, which runs
 * nightly once the day's parcel syncs have landed.
 */
class RmoAutoTag
{
    /**
     * Parcel status (normalised) => RMO status. Deliberately not configurable:
     * every other parcel status is a judgement call that belongs to a CSR.
     */
    public const MAP = [
        'delivered' => 'DELIVERED',
        'returning' => 'RETURNING',
    ];

    /**
     * Collapse a courier's parcel status to the key shape used by the map:
     * "DELIVERED", "Delivered" and "delivered" all match.
     */
    public static function normalize(?string $parcelStatus): string
    {
        return str_replace(' ', '_', strtolower(trim((string) $parcelStatus)));
    }

    /**
     * The RMO status this parcel status should tag to, or null when the
     * workspace has auto-tagging off or the parcel status isn't one of the two.
     */
    public static function statusFor(Workspace $workspace, ?string $parcelStatus): ?string
    {
        if (! $workspace->rmoAutoTagStatusEnabled()) {
            return null;
        }

        return self::MAP[self::normalize($parcelStatus)] ?? null;
    }

    /**
     * Re-tag one workspace's RMO rows on $date, and report how many rows
     * changed. A no-op when the workspace has auto-tagging off.
     *
     * Shared by the `rmo:apply-auto-tag` command and the settings save, so the
     * two can't drift into tagging differently.
     */
    public static function apply(Workspace $workspace, string $date): int
    {
        if (! $workspace->rmoAutoTagStatusEnabled()) {
            return 0;
        }

        $tagged = 0;

        foreach (self::MAP as $parcelStatus => $rmoStatus) {
            $tagged += OrderForDelivery::query()
                ->where('workspace_id', $workspace->id)
                ->whereDate('delivery_date', $date)
                // Match the same normalisation applied to the map keys, so a
                // stored "DELIVERED" still matches the "delivered" key.
                ->whereRaw("LOWER(REPLACE(parcel_status, ' ', '_')) = ?", [$parcelStatus])
                // Skip rows already carrying the target status — this runs on a
                // schedule, so most passes should update nothing at all.
                ->where(function ($query) use ($rmoStatus) {
                    $query->where('status', '!=', $rmoStatus)
                        ->orWhereNull('status');
                })
                ->update(['status' => $rmoStatus]);
        }

        return $tagged;
    }
}
