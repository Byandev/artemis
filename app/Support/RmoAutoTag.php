<?php

namespace App\Support;

use App\Models\Workspace;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Auto-tagging keeps an RMO row's call status in step with the courier's parcel
 * status: once the parcel reports "delivered", the RMO status follows to
 * "DELIVERED" on its own, with nobody touching the row.
 *
 * Which parcel statuses tag, and what they tag to, is per-workspace and lives in
 * rmo_settings.auto_tag_status_map. Everything here works on normalised keys —
 * couriers send "OUT FOR DELIVERY" and "out_for_delivery" interchangeably.
 *
 * The tagging itself is driven by the `rmo:apply-auto-tag` command, which runs
 * on a schedule after the parcel syncs have landed fresh parcel statuses.
 */
class RmoAutoTag
{
    /** Parcel statuses that can drive an auto-tag, keyed by normalised value. */
    public const PARCEL_STATUSES = [
        'delivered' => 'Delivered',
        'returning' => 'Returning',
        'returned' => 'Returned',
        'undeliverable' => 'Undeliverable',
        'on_the_way' => 'On the Way',
        'out_for_delivery' => 'Out for Delivery',
        'shipped' => 'Shipped',
        'in_transit' => 'In Transit',
        'pending' => 'Pending',
        'cancelled' => 'Cancelled',
    ];

    /** RMO statuses a row may be tagged with — mirrors ORDER_STATUSES in TS. */
    public const RMO_STATUSES = [
        'PENDING',
        'DELIVERED',
        'RIDER OTW',
        'RETURNING',
        'RESCHEDULED',
        'CX CBR',
        'RIDER CBR',
        'CANCELLED',
        'WRONG SEGMENT CODE',
        'CX RINGING',
        'RIDER RINGING',
        'IN TRANSIT',
        'INCORRECT NUMBER',
        'AUTO DROP CX',
        'AUTO DROP RIDER',
    ];

    /**
     * Offered as a starting point when a workspace first switches auto-tagging
     * on. Only the parcel statuses with an unambiguous RMO twin — the rest are
     * a judgement call the workspace has to make itself.
     */
    public const DEFAULT_MAP = [
        'delivered' => 'DELIVERED',
        'returning' => 'RETURNING',
    ];

    /**
     * Collapse a courier's parcel status to the key shape used by the map:
     * "OUT FOR DELIVERY", "Out For Delivery" and "out_for_delivery" all match.
     */
    public static function normalize(?string $parcelStatus): string
    {
        return str_replace(' ', '_', strtolower(trim((string) $parcelStatus)));
    }

    /**
     * Drop unknown parcel statuses and non-status targets, so a map saved
     * against an older status list can never tag a row with junk.
     *
     * @param  array<string, mixed>|null  $map
     * @return array<string, string>
     */
    public static function sanitizeMap(?array $map): array
    {
        $clean = [];

        foreach ($map ?? [] as $parcelStatus => $rmoStatus) {
            $key = self::normalize((string) $parcelStatus);

            if (! isset(self::PARCEL_STATUSES[$key])) {
                continue;
            }

            if (! is_string($rmoStatus) || ! in_array($rmoStatus, self::RMO_STATUSES, true)) {
                continue;
            }

            $clean[$key] = $rmoStatus;
        }

        return $clean;
    }

    /**
     * The RMO status this parcel status should tag to, or null when the
     * workspace has auto-tagging off or hasn't mapped this parcel status.
     */
    public static function statusFor(Workspace $workspace, ?string $parcelStatus): ?string
    {
        if (! $workspace->rmoAutoTagStatusEnabled()) {
            return null;
        }

        return $workspace->rmoAutoTagStatusMap()[self::normalize($parcelStatus)] ?? null;
    }

    /**
     * Re-tag one workspace's RMO rows on $date against its own map, and report
     * how many rows changed. A no-op when the workspace has auto-tagging off or
     * has mapped nothing.
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

        foreach ($workspace->rmoAutoTagStatusMap() as $parcelStatus => $rmoStatus) {
            $tagged += OrderForDelivery::query()
                ->where('workspace_id', $workspace->id)
                ->whereDate('delivery_date', $date)
                // Match the same normalisation applied to the map keys, so a
                // stored "OUT FOR DELIVERY" still matches "out_for_delivery".
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
