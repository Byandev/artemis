<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Works out who a call reached, and about which order.
 *
 * The syncing app posts a phone number and a timestamp, nothing more, and that
 * stays true — the sync payload is not changing. So the match is made here,
 * against the deliveries loaded for that same day: a number on a delivery's
 * customer_phone was a customer call, one on rider_phone was a rider call, and
 * the order id and the delivery's own id come off whichever delivery matched.
 * A number on neither was not part of an RMO delivery at all.
 *
 * Such a number can still be a verification call — a CSR ringing a customer on
 * the day their order came in or was confirmed, before it is ever loaded for
 * delivery. That match is resolveVerification(), tried at sync time on whatever
 * the delivery rule could not place: it reads pancake_orders, which lands on its
 * own schedule, so a call synced minutes after it was placed can still find
 * nothing there yet. An order arriving afterwards claims the calls that were
 * waiting for it from its own side, in LinkVerificationCallLogsAction.
 */
class CallLogPersona
{
    public const CUSTOMER = 'customer';

    public const RIDER = 'rider';

    /**
     * A call placed to a customer on the day their order came in or was confirmed.
     *
     * Kept to 12 characters on purpose: call_logs.persona is a string(16).
     */
    public const VERIFICATION = 'verification';

    /**
     * A phone number reduced to the one spelling everything can be compared on.
     *
     * shipping_addresses stores whatever Pancake was given — 09171234567,
     * +639171234567 and 9171234567 all appear for the same subscriber — and the
     * phone a handset reports is no tidier. Last ten digits with a leading zero
     * is the same rule SyncPhoneNumberReportsAction normalizes to, so numbers
     * from either side of the match land on the same key.
     *
     * Returns null for anything too short to be a number rather than a
     * zero-padded fragment that could collide with a real one.
     */
    public static function normalize(?string $phone): ?string
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        return strlen($digits) >= 10 ? '0'.substr($digits, -10) : null;
    }

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
     * Both rules run, deliveries first: a number on that day's deliveries is
     * RMO work — customer or rider — and only what is left over is offered to
     * the verification rule, against the orders that same day took in or
     * confirmed.
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

            $matches = self::resolve((int) $workspaceId, $date, $phones);

            // Only the numbers no delivery accounted for are worth the second
            // query, and a delivery match is never overwritten by one: a call to
            // a customer whose order was confirmed and dispatched the same day
            // is about the delivery in front of it.
            $leftover = array_values(array_diff(
                array_unique(array_filter($phones)),
                array_keys($matches),
            ));

            $resolved[$key] = $leftover === []
                ? $matches
                : $matches + self::resolveVerification((int) $workspaceId, $date, $leftover);
        }

        return array_map(function (array $row) use ($resolved) {
            $key = $row['workspace_id'].'|'.$row['call_date'];
            $match = $resolved[$key][$row['phone_number']] ?? null;

            // Left null when neither rule matched. Often that is only a matter of
            // timing — the delivery or the order lands later in the day, and an
            // order landing later claims its own calls back.
            $row['persona'] = $match['persona'] ?? null;
            $row['order_id'] = $match['order_id'] ?? null;
            $row['order_for_delivery_id'] = $match['order_for_delivery_id'] ?? null;

            return $row;
        }, $rows);
    }

    /**
     * Verification matches for each phone number, keyed by the number as given.
     *
     * A call counts as verification when the number is the shipping-address
     * phone on an order this workspace either took in or confirmed that same
     * day. Both stamps count because the ringing happens either side of the
     * confirmation: a CSR rings to confirm an order that has just come in, and
     * an order that is never confirmed at all has no confirmation day to be
     * rung on — read on confirmed_at alone, those calls are matched to nothing
     * and counted nowhere. shipping_addresses is the source because it is the
     * one pancake_order_for_delivery.customer_phone is itself populated from,
     * so a customer matched here is the same customer the delivery rule would
     * have matched.
     *
     * Ties — the same number on two of that day's orders — go to the earliest
     * stamp that put one of them in the day. Nothing in the data says which of
     * the two a call was about; earliest is deterministic rather than right.
     *
     * @param  list<string>  $phoneNumbers
     * @return array<string, array{persona: string, order_id: int, order_for_delivery_id: null}>
     */
    public static function resolveVerification(int $workspaceId, string $date, array $phoneNumbers): array
    {
        // One key can carry several raw spellings, and every one of them has to
        // come back in the result — the caller looks matches up by the string it
        // passed in, not by the normalized form.
        $byKey = [];

        foreach (array_unique(array_filter($phoneNumbers)) as $phone) {
            if ($key = self::normalize($phone)) {
                $byKey[$key][] = $phone;
            }
        }

        if ($byKey === []) {
            return [];
        }

        $day = Carbon::parse($date)->startOfDay();

        // Unioned rather than OR-ed across the two stamps: each half is then a
        // range on an index of its own — idx_orders_workspace_confirmed_status
        // and idx_orders_workspace_inserted — where an OR over both columns
        // leaves the optimizer free to scan instead, and this query is on the
        // sync path. An order both taken in and confirmed that day comes back
        // on both halves; it is the same order id either way, and the ordering
        // settles it on the earlier of its two stamps.
        $orders = DB::query()
            ->fromSub(
                self::ordersStampedOn($workspaceId, 'confirmed_at', $day)
                    ->unionAll(self::ordersStampedOn($workspaceId, 'inserted_at', $day)),
                'o',
            )
            ->orderBy('o.matched_at')
            ->orderBy('o.id')
            ->get();

        $resolved = [];

        foreach ($orders as $order) {
            $key = self::normalize($order->phone_number);

            if ($key === null || ! isset($byKey[$key])) {
                continue;
            }

            foreach ($byKey[$key] as $raw) {
                if (isset($resolved[$raw])) {
                    // Ordered by the stamp that put each order in the day, so
                    // the first one seen is the earliest and keeps the row.
                    continue;
                }

                $resolved[$raw] = [
                    'persona' => self::VERIFICATION,
                    'order_id' => (int) $order->id,
                    'order_for_delivery_id' => null,
                ];
            }
        }

        return $resolved;
    }

    /**
     * The day's orders on one stamp, with the number to match them on.
     *
     * A half-open range rather than whereDate: DATE(confirmed_at) hides the
     * column from idx_orders_workspace_confirmed_status, and DATE(inserted_at)
     * does the same to idx_orders_workspace_inserted. `matched_at` comes back
     * with the row because it is what the caller orders ties on, and which of
     * the two stamps it holds depends on the half it came from.
     */
    private static function ordersStampedOn(int $workspaceId, string $column, Carbon $day): Builder
    {
        return DB::table('pancake_orders as o')
            ->join('shipping_addresses as sa', 'sa.order_id', '=', 'o.id')
            ->where('o.workspace_id', $workspaceId)
            ->where("o.{$column}", '>=', $day)
            ->where("o.{$column}", '<', $day->copy()->addDay())
            ->whereNotNull('sa.phone_number')
            ->select(['o.id', 'sa.phone_number'])
            ->selectRaw("o.{$column} as matched_at");
    }
}
