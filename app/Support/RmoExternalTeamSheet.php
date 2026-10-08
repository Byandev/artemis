<?php

namespace App\Support;

use App\Models\Workspace;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Applies the external RMO team's Google Sheet to a day's RMO rows.
 *
 * n8n reads the sheet tab for the day and posts its rows here. Every row the
 * sheet lists is flagged `rmo_by_external_team` — that's what the "External
 * Team" filter on the RMO page keys on — and the rows whose sheet status is
 * one we trust are auto-tagged to the matching RMO status. Every other sheet
 * status (Ringing, Cannot Be Reached, ...) is the external team's call log,
 * not an outcome, so the RMO status is left alone.
 *
 * Rows are matched on the waybill: the sheet's TRACKING NUMBER against the
 * parent order's `tracking_code`, within the workspace and delivery date.
 */
class RmoExternalTeamSheet
{
    /**
     * Sheet status (normalised) => RMO status. The sheet says "Returned" but
     * RMO has no such status — "RETURNING" is what auto-tag uses for the same
     * parcel state.
     */
    public const STATUS_MAP = [
        'confirmed' => 'RIDER OTW',
        'delivered' => 'DELIVERED',
        'returned' => 'RETURNING',
    ];

    /**
     * What each sheet status tags among the CX / rider statuses until the
     * workspace picks otherwise in Settings — the default statuses by name.
     */
    public const DEFAULT_SUB_STATUS_MAP = [
        'confirmed' => ['rider', 'RIDER OTW'],
        'delivered' => ['rider', 'DELIVERED'],
        'returned' => ['rider', 'RETURNING'],
    ];

    /**
     * Sheet status => "cx:{id}", "rider:{id}" or '' (tag nothing), for every
     * sheet status in STATUS_MAP. A saved choice wins; one never saved falls
     * back to the default status of that name, if the workspace still has it.
     * A choice whose status has since been deleted resolves to ''.
     *
     * @return array<string, string>
     */
    public static function subStatusMap(Workspace $workspace): array
    {
        $saved = $workspace->loadMissing('rmoSetting')->rmoSetting?->external_team_status_map ?? [];
        $ids = [
            'cx' => $workspace->rmoCxStatuses()->pluck('id', 'name'),
            'rider' => $workspace->rmoRiderStatuses()->pluck('id', 'name'),
        ];

        $map = [];
        foreach (array_keys(self::STATUS_MAP) as $sheetStatus) {
            if (array_key_exists($sheetStatus, $saved)) {
                [$type, $id] = array_pad(explode(':', (string) $saved[$sheetStatus], 2), 2, null);
                $map[$sheetStatus] = isset($ids[$type]) && $ids[$type]->contains((int) $id) ? "{$type}:{$id}" : '';

                continue;
            }

            [$type, $name] = self::DEFAULT_SUB_STATUS_MAP[$sheetStatus];
            $id = $ids[$type]->get($name);
            $map[$sheetStatus] = $id ? "{$type}:{$id}" : '';
        }

        return $map;
    }

    /**
     * The spreadsheet id out of a Google Sheets link
     * (https://docs.google.com/spreadsheets/d/{id}/edit…), or null when the
     * link isn't one.
     */
    public static function sheetId(?string $url): ?string
    {
        return preg_match('~docs\.google\.com/spreadsheets/d/([A-Za-z0-9_-]+)~', (string) $url, $m)
            ? $m[1]
            : null;
    }

    public static function statusFor(?string $sheetStatus): ?string
    {
        return self::STATUS_MAP[strtolower(trim((string) $sheetStatus))] ?? null;
    }

    /**
     * @param  array<int, array{tracking_number?: string|null, status?: string|null}>  $rows
     * @return array{received: int, matched: int, flagged: int, tagged: int, unmatched: array<int, string>}
     */
    public static function apply(Workspace $workspace, string $date, array $rows): array
    {
        // Tracking number => sheet status. A waybill listed twice keeps its
        // last status, the same as reading the sheet top to bottom.
        $statuses = collect($rows)
            ->mapWithKeys(fn ($row) => [
                strtoupper(trim((string) ($row['tracking_number'] ?? ''))) => $row['status'] ?? null,
            ])
            ->forget('');

        $deliveries = OrderForDelivery::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $date)
            ->whereHas('order', fn ($q) => $q->whereIn('tracking_code', $statuses->keys()))
            ->with('order:id,tracking_code')
            ->get();

        // Sheet status => [column, id] for the CX / rider status it tags.
        $subStatuses = collect(self::subStatusMap($workspace))
            ->filter()
            ->map(function (string $value) {
                [$type, $id] = explode(':', $value, 2);

                return [$type === 'cx' ? 'cx_status_id' : 'rider_status_id', (int) $id];
            });

        $flagged = 0;
        $tagged = 0;
        $matched = [];

        foreach ($deliveries as $delivery) {
            $code = strtoupper(trim((string) $delivery->order?->tracking_code));
            $matched[$code] = true;

            $delivery->rmo_by_external_team = true;

            $sheetStatus = $statuses->get($code);
            $status = self::statusFor($sheetStatus);
            if ($status !== null) {
                $delivery->status = $status;
            }

            // Alongside the main status, not instead of it: the public RMO page
            // still shows the main one.
            if ($subStatus = $subStatuses->get(strtolower(trim((string) $sheetStatus)))) {
                [$column, $id] = $subStatus;
                $delivery->{$column} = $id;
            }

            if ($delivery->isDirty('rmo_by_external_team')) {
                $flagged++;
            }

            if ($delivery->isDirty(['status', 'cx_status_id', 'rider_status_id'])) {
                $tagged++;
            }

            $delivery->save();
        }

        return [
            'received' => $statuses->count(),
            'matched' => count($matched),
            'flagged' => $flagged,
            'tagged' => $tagged,
            'unmatched' => $statuses->keys()->reject(fn ($code) => isset($matched[$code]))->values()->all(),
        ];
    }
}
