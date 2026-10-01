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
     * Besides the counts, the result lists the matched rows by tracking number
     * with the RMO status they ended on, so n8n can mark them in the sheet.
     *
     * @param  array<int, array{tracking_number?: string|null, status?: string|null}>  $rows
     * @return array{received: int, matched: int, flagged: int, tagged: int, synced: array<int, array{tracking_number: string, rmo_status: string, tagged: bool}>, unmatched: array<int, string>}
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

        $flagged = 0;
        $tagged = 0;
        $matched = [];
        $synced = [];

        foreach ($deliveries as $delivery) {
            $code = strtoupper(trim((string) $delivery->order?->tracking_code));
            $matched[$code] = true;

            $delivery->rmo_by_external_team = true;

            $status = self::statusFor($statuses->get($code));
            if ($status !== null) {
                $delivery->status = $status;
            }

            if ($delivery->isDirty('rmo_by_external_team')) {
                $flagged++;
            }

            $statusChanged = $delivery->isDirty('status');
            if ($statusChanged) {
                $tagged++;
            }

            $delivery->save();

            $synced[] = [
                'tracking_number' => $code,
                'rmo_status' => $delivery->status,
                'tagged' => $statusChanged,
            ];
        }

        return [
            'received' => $statuses->count(),
            'matched' => count($matched),
            'flagged' => $flagged,
            'tagged' => $tagged,
            'synced' => $synced,
            'unmatched' => $statuses->keys()->reject(fn ($code) => isset($matched[$code]))->values()->all(),
        ];
    }
}
