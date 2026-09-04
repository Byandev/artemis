<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Works out who a call reached, and about which order.
 *
 * The syncing app posts a phone number and a timestamp, nothing more, and that
 * stays true — the sync payload is not changing. So the match is made here,
 * against the deliveries loaded for that same day: a number on a delivery's
 * customer_phone was a customer call, one on rider_phone was a rider call, and
 * the order id and the delivery's own id come off whichever delivery matched.
 * A number on neither was not part of an RMO delivery at all, and is left
 * unstamped.
 */
class CallLogPersona
{
    public const CUSTOMER = 'customer';

    public const RIDER = 'rider';

    /**
     * Persona, order id and delivery id for each phone number, keyed by number.
     *
     * One query per workspace/day rather than one per call — a sync posts
     * hundreds of rows at a time, and most of them share a handful of numbers.
     *
     * A number that appears as both a customer and a rider that day resolves to
     * customer: the customer is who the RMO call is about, and the rider's own
     * number is the one more likely to be reused across orders.
     *
     * @param  list<string>  $phoneNumbers
     * @return array<string, array{persona: string, order_id: int|null, order_for_delivery_id: int|null}>
     */
    public static function resolve(int $workspaceId, string $date, array $phoneNumbers): array
    {
        $phoneNumbers = array_values(array_unique(array_filter($phoneNumbers)));

        if ($phoneNumbers === []) {
            return [];
        }

        $deliveries = DB::table('pancake_order_for_delivery')
            ->where('workspace_id', $workspaceId)
            ->whereDate('delivery_date', $date)
            ->where(function ($query) use ($phoneNumbers) {
                $query->whereIn('customer_phone', $phoneNumbers)
                    ->orWhereIn('rider_phone', $phoneNumbers);
            })
            ->get(['id', 'order_id', 'customer_phone', 'rider_phone']);

        $resolved = [];

        // Riders first, so a number that is also a customer number overwrites
        // the rider entry rather than the other way round.
        foreach ($deliveries as $delivery) {
            if ($delivery->rider_phone && in_array($delivery->rider_phone, $phoneNumbers, true)) {
                $resolved[$delivery->rider_phone] ??= [
                    'persona' => self::RIDER,
                    'order_id' => $delivery->order_id,
                    'order_for_delivery_id' => $delivery->id,
                ];
            }
        }

        foreach ($deliveries as $delivery) {
            if ($delivery->customer_phone && in_array($delivery->customer_phone, $phoneNumbers, true)) {
                $resolved[$delivery->customer_phone] = [
                    'persona' => self::CUSTOMER,
                    'order_id' => $delivery->order_id,
                    'order_for_delivery_id' => $delivery->id,
                ];
            }
        }

        return $resolved;
    }

    /**
     * Stamp persona/order_id/order_for_delivery_id onto rows headed for call_logs.
     *
     * Rows are grouped by date first: a single sync can span midnight, and a
     * number's persona is only meaningful against the day it was called on.
     *
     * @param  list<array<string, mixed>>  $rows  each with workspace_id, phone_number, call_date
     * @return list<array<string, mixed>>
     */
    public static function stamp(array $rows): array
    {
        $lookups = [];

        foreach ($rows as $row) {
            $lookups[$row['workspace_id'].'|'.$row['call_date']][] = $row['phone_number'];
        }

        $resolved = [];

        foreach ($lookups as $key => $phones) {
            [$workspaceId, $date] = explode('|', $key, 2);
            $resolved[$key] = self::resolve((int) $workspaceId, $date, $phones);
        }

        return array_map(function (array $row) use ($resolved) {
            $key = $row['workspace_id'].'|'.$row['call_date'];
            $match = $resolved[$key][$row['phone_number']] ?? null;

            // Left null when nothing matched: that is what an order-verification
            // call looks like, not a gap to be guessed at.
            $row['persona'] = $match['persona'] ?? null;
            $row['order_id'] = $match['order_id'] ?? null;
            $row['order_for_delivery_id'] = $match['order_for_delivery_id'] ?? null;

            return $row;
        }, $rows);
    }
}
