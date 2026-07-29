<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\GencysDailySalesOrder;
use Modules\GencysERP\Support\OrderItemParser;

/**
 * Receives the daily sales tracker rows that the n8n flow scrapes from Gencys
 * ERP and posts back. The payload is the list n8n sends:
 *
 *   [ { "workspace_id": 1, "api_key": "art_…", "webhook_url": "…",
 *       "orders": [ { "Order Date": "…", "CSR": "…", … }, … ] } ]
 *
 * Each entry is authenticated by its own `api_key` (n8n puts it in the body),
 * and the orders are upserted keyed on Gencys' own order `id`, which is stored
 * as the row's primary key.
 */
class DailySalesTrackerController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Inspect exactly what n8n sends. Remove once the flow is verified.
        Log::info('Gencys daily sales tracker request', [
            'method' => $request->method(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'headers' => $request->headers->all(),
            'body' => $request->all(),
            'raw' => $request->getContent(),
        ]);

        $payload = $request->all();

        // Accept either a single entry object or n8n's list of entries.
        $entries = array_is_list($payload) ? $payload : [$payload];

        $created = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($entries as $i => $entry) {
            $rawKey = $entry['api_key'] ?? null;
            $apiKey = $rawKey ? WorkspaceApiKey::findByRawKey($rawKey) : null;

            if (! $apiKey) {
                $errors[] = "entry {$i}: missing or invalid api_key";

                continue;
            }

            $apiKey->update(['last_used_at' => now()]);
            $workspace = $apiKey->workspace;

            // n8n sends the rows under "purchase_orders"; older payloads used "orders".
            $rows = Arr::get($entry, 'purchase_orders', Arr::get($entry, 'orders', []));

            foreach ($rows as $order) {
                // Gencys' own order id ("id" in the payload). It's the stable
                // upsert key — assigned at order creation and never changes.
                $orderId = $this->intOrNull($order['id'] ?? null);
                $tracking = $this->str($order['Tracking Number'] ?? null);

                $attributes = [
                    'order_no' => $this->str($order['No'] ?? null),
                    'order_date' => $this->dateTime($order['Order Date'] ?? null),
                    'csr' => $this->str($order['CSR'] ?? null),
                    'verifier_name' => $this->str($order['Verifier Name'] ?? null),
                    'upsell_by' => $this->str($order['Upsell By'] ?? null),
                    'customer_name' => $this->str($order['Customer Name'] ?? null),
                    'address' => $this->str($order['Address'] ?? null),
                    'province' => $this->str($order['Province'] ?? null),
                    'city' => $this->str($order['City'] ?? null),
                    'brgy' => $this->str($order['Brgy'] ?? null),
                    'contact' => $this->str($order['Contact'] ?? null),
                    'order_details' => $this->str($order['Order'] ?? null),
                    'total_qty' => $this->intOrNull($order['Total Qty'] ?? null),
                    'price_final' => $this->decimalOrNull($order['Price (Final)'] ?? null),
                    'price_initial' => $this->decimalOrNull($order['Price (Initial)'] ?? null),
                    'shipping_fee' => $this->decimalOrNull($order['Shipping Fee'] ?? null),
                    'page' => $this->str($order['Page'] ?? null),
                    'platform' => $this->str($order['Platform'] ?? null),
                    'tracking_number' => $tracking,
                    'courier' => $this->str($order['Courier'] ?? null),
                    'parcel_status' => $this->str($order['Parcel Status'] ?? null),
                    'order_status' => $this->str($order['Order Status'] ?? null),
                    'mop' => $this->str($order['MOP'] ?? null),
                    'encoded_date' => $this->dateTime($order['Encoded Date'] ?? null),
                    'parcel_updated_date' => $this->dateTime($order['Parcel Updated Date'] ?? null),
                    'shipped_out_date' => $this->dateTime($order['Shipped Out Date'] ?? null),
                    'date_added' => $this->dateTime($order['Date Added'] ?? null),
                    'price_upsell' => $this->decimalOrNull($order['Price (Upsell)'] ?? null),
                    'intern_brands_name' => $this->str($order['Intern & Brands Name'] ?? null),
                    'total_cog' => $this->decimalOrNull($order['Total COG'] ?? null),
                ];

                // Upsert on the Gencys order id. Rows without one have no stable
                // key, so they're skipped rather than inserted with a bogus id.
                if ($orderId === null) {
                    $skipped++;

                    continue;
                }

                $record = GencysDailySalesOrder::updateOrCreate(
                    ['id' => $orderId],
                    ['workspace_id' => $workspace->id] + $attributes,
                );
                $record->wasRecentlyCreated ? $created++ : $updated++;

                // Replace the parsed line items so re-syncs stay idempotent.
                $record->items()->delete();
                $items = OrderItemParser::parse($attributes['order_details']);
                if (! empty($items)) {
                    $record->items()->createMany($items);
                }
            }
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ], empty($errors) ? 200 : 207);
    }

    /** Trim to a non-empty string, or null. */
    private function str(?string $value): ?string
    {
        $value = is_string($value) ? trim($value) : $value;

        return ($value === null || $value === '') ? null : $value;
    }

    /** Parse "1" → 1, "" → null. */
    private function intOrNull(mixed $value): ?int
    {
        return ($value === null || $value === '') ? null : (int) $value;
    }

    /** Parse "42.00" → 42.00, "" → null. */
    private function decimalOrNull(mixed $value): ?float
    {
        return ($value === null || $value === '') ? null : (float) $value;
    }

    /**
     * Parse the mixed date formats Gencys sends: "d/m/Y H:i:s" (Order Date),
     * "Y-m-d H:i:s" (Encoded/Parcel/Date Added), and "Y-m-d" (Shipped Out).
     */
    private function dateTime(?string $value): ?Carbon
    {
        $value = is_string($value) ? trim($value) : null;

        if (! $value) {
            return null;
        }

        foreach (['d/m/Y H:i:s', 'd/m/Y', 'Y-m-d H:i:s', 'Y-m-d'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value);
            } catch (\Throwable) {
                // Try the next known format.
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
