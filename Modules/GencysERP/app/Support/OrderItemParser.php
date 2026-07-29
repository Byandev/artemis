<?php

namespace Modules\GencysERP\Support;

/**
 * Splits the raw "Order" string Gencys sends into line items. Shared by the n8n
 * ingest endpoint and the (non-production) manual test-order form so both parse
 * the same way.
 */
class OrderItemParser
{
    /**
     * The string is a comma-separated list, and each item is split on its first
     * "x" into a quantity and an sku, e.g.
     *
     *   "1x2X MAGNERVE,1x1X HIKARIJOINT THERAPY"
     *     => [ ['quantity' => 1, 'sku' => '2X MAGNERVE'],
     *          ['quantity' => 1, 'sku' => '1X HIKARIJOINT THERAPY'] ]
     *
     * We split on the FIRST "x" only because skus themselves often contain "X"
     * (e.g. "2X MAGNERVE"). Items that don't match keep a null quantity.
     *
     * @return array<int, array{quantity: ?int, sku: ?string}>
     */
    public static function parse(?string $order): array
    {
        $order = is_string($order) ? trim($order) : null;

        if (! $order) {
            return [];
        }

        $items = [];

        foreach (explode(',', $order) as $piece) {
            $piece = trim($piece);

            if ($piece === '') {
                continue;
            }

            if (preg_match('/^(\d+)\s*x\s*(.+)$/i', $piece, $matches)) {
                $items[] = [
                    'quantity' => (int) $matches[1],
                    'sku' => trim($matches[2]),
                ];
            } else {
                $items[] = ['quantity' => null, 'sku' => $piece];
            }
        }

        return $items;
    }
}