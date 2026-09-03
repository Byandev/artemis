<?php

namespace Modules\GencysERP\Support;

use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\Pancake\Models\OrderForDelivery;

/**
 * Copies the Gencys upsell figures onto an RMO row (pancake_order_for_delivery)
 * as the row is written, so the RMO page can show them historically.
 *
 * Called from the parcel sync, which runs on every fetch-orders pass. That
 * repetition is what makes a separate catch-up sweep unnecessary: an RMO row
 * created before its Gencys order landed is simply stamped on a later pass,
 * because the sync re-touches the same row for as long as the parcel is out.
 *
 * The two systems are matched on the only key they share — the waybill
 * (`pancake_orders.tracking_code` = `gencys_orders.tracking_number`, unique per
 * workspace) — via {@see OrderForDelivery::gencysOrder()}.
 *
 * Each column fills independently, and a column that already holds a value is
 * never rewritten. Both halves matter:
 *
 *  • Independently, because the three values don't arrive together. An order
 *    typically lands with its details while the upsell is still unencoded, so a
 *    rule of "stamp only rows that are entirely blank" would write the details,
 *    mark the row done, and never pick up the upsell added hours later.
 *  • Never rewritten, because the point of copying rather than joining is that a
 *    past RMO row keeps what it showed at the time, even after Gencys re-upserts
 *    or edits the source order.
 */
class RmoUpsellStamper
{
    /** The RMO columns copied from Gencys. */
    private const COLUMNS = ['upsell_date', 'upsell_price', 'order_details'];

    /**
     * Fill whatever this row is still missing from its Gencys order.
     *
     * Costs one indexed lookup, and only for a row that's actually missing
     * something — a fully stamped row returns without touching the database.
     *
     * @return bool whether anything was written
     */
    public function stampRow(OrderForDelivery $row): bool
    {
        if ($this->nullColumns($row) === []) {
            return false;
        }

        $row->loadMissing('order');

        // The relation is not workspace-scoped by design — see the docblock on
        // OrderForDelivery::gencysOrder(). Waybills are only unique per workspace.
        $gencys = GencysDailySalesOrder::where('tracking_number', $row->order->tracking_code)
            ->first();

        if (! $gencys) {
            return false;
        }

        $fill = array_intersect_key($this->valuesFrom($gencys), array_flip($this->nullColumns($row)));

        // Nothing Gencys can answer for yet — leave the row as it is so a later
        // fetch-orders pass, once Gencys has filled the order in, can pick it up.
        if ($fill === []) {
            return false;
        }

        return $row->forceFill($fill)->save();
    }

    /**
     * The Gencys side of the mapping. Blank strings are dropped alongside nulls
     * so an empty source value doesn't count as stamped.
     *
     * @return array<string, mixed>
     */
    private function valuesFrom(GencysDailySalesOrder $gencys): array
    {
        return array_filter([
            'upsell_date' => $gencys->date_added?->toDateString(),
            'upsell_price' => $gencys->price_upsell,
            'order_details' => $gencys->order_details,
        ], fn ($value) => $value !== null && $value !== '');
    }

    /**
     * Which of the copied columns this row is still missing.
     *
     * @return array<int, string>
     */
    private function nullColumns(OrderForDelivery $row): array
    {
        return array_values(array_filter(
            self::COLUMNS,
            fn ($column) => $row->{$column} === null || $row->{$column} === '',
        ));
    }
}
