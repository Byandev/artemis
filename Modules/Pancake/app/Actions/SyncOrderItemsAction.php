<?php

namespace Modules\Pancake\Actions;

use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderItem;
use Modules\Pancake\Support\OrderCurrency;

/**
 * Writes the order's lines from the pancake payload, including what the goods on
 * each line cost.
 *
 * Cost comes from `variation_info.last_imported_price` — the variation's most
 * recent import price, which is a price for one unit — so the line's `cogs` is
 * that times its quantity. Like every other amount in the payload it is scaled
 * by the order's currency code, so it is read through OrderCurrency exactly as
 * `final_amount` is on the order itself.
 *
 * A line whose variation carries no import price is left with the cost it
 * already had rather than being blanked: an order that has been costed by hand
 * or by an earlier sync must survive a payload that happens not to mention a
 * price.
 */
class SyncOrderItemsAction
{
    public function execute(Order $savedOrder, array $order): void
    {
        if (empty($order['items'])) {
            return;
        }

        $currency = $order['order_currency'] ?? null;

        foreach ($order['items'] as $item) {
            OrderItem::updateOrCreate(
                [
                    'order_id' => $savedOrder->id,
                    'pancake_order_id' => $order['id'],
                    'pancake_id' => $item['id'],
                    'pancake_product_id' => $item['product_id'],
                    'pancake_variant_id' => $item['variation_id'],
                ],
                [
                    'quantity' => $item['quantity'],
                    'name' => $item['variation_info']['display_id'],
                    ...$this->cogs($item, $currency),
                ]
            );
        }
    }

    /**
     * The line's cost of goods — the import price times the quantity — as a
     * fragment to spread into the update. Empty when the payload names no import
     * price, so the column is not touched at all.
     *
     * The quantity is taken as written. A line for no units costs nothing, even
     * though the statement's unit count reads such a line as one
     * (`GREATEST(COALESCE(quantity, 1), 1)`).
     *
     * @return array{cogs?: float}
     */
    private function cogs(array $item, ?string $currency): array
    {
        $unit = OrderCurrency::amount($item['variation_info']['last_imported_price'] ?? null, $currency);

        if ($unit === null) {
            return [];
        }

        return ['cogs' => round($unit * (int) $item['quantity'], 2)];
    }
}
