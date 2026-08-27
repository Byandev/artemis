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
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\SyncCallbackFields;

/**
 * Receives the daily sales tracker rows that the n8n flow scrapes from Gencys
 * ERP and posts back. The payload is the list n8n sends:
 *
 *   [ { "workspace_id": 1, "api_key": "art_…", "webhook_url": "…",
 *       "sync_run_id": 42,
 *       "orders": [ { "Order Date": "…", "CSR": "…", … }, … ] } ]
 *
 * Each entry is authenticated by its own `api_key` (n8n puts it in the body),
 * and the orders are upserted keyed on Gencys' own order `id`, which is stored
 * as the row's primary key. `sync_run_id` is the run the trigger command opened
 * for this workspace/date, echoed back so we can attribute the rows to it.
 *
 * A date's rows are too big for one post, so n8n sends them a thousand at a time
 * and this endpoint does NOT close the run — it accumulates the counts and pushes
 * the run's callback deadline out. n8n closes the run with a single call to
 * /api/v1/public/gencys/sync-runs/finish once it has sent the last chunk. Without
 * that call the run times out (and retries) roughly one timeout after the last
 * chunk landed.
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

            // Rows this entry actually wrote — reported back on its sync run.
            $entrySaved = 0;

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
                $entrySaved++;

                // Replace the parsed line items so re-syncs stay idempotent.
                $record->items()->delete();
                $items = $this->parseOrderItems($attributes['order_details']);
                if (! empty($items)) {
                    $record->items()->createMany($items);
                }
            }

            // Feed the run rather than close it. This date's rows arrive a
            // thousand at a time, so closing here would finish the run on the
            // first chunk and let the batch release the ERP while n8n was still
            // posting the rest. The counts accumulate, the callback deadline is
            // pushed out, and the run stays pending until n8n posts to
            // /api/v1/public/gencys/sync-runs/finish.
            $runId = SyncCallbackFields::runId($entry, $request);

            // Rows that arrive without a run id are still saved, but nothing is
            // credited for them: the run they belong to stays pending until it
            // times out and gets retried, re-fetching data that is already in.
            // Silent when it happens, expensive afterwards — so say so.
            if (! $runId) {
                Log::warning('Gencys daily sales tracker rows arrived with no sync_run_id', [
                    'workspace_id' => $workspace->id,
                    'rows' => count($rows),
                    'hint' => 'The n8n flow must echo sync_run_id back on each chunk, and POST /api/v1/public/gencys/sync-runs/finish when it is done.',
                ]);
            }

            GencysSyncRun::heartbeatById(
                $workspace->id,
                $runId,
                count($rows),
                $entrySaved,
                SyncCallbackFields::executionId($entry, $request),
            );
        }

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ], empty($errors) ? 200 : 207);
    }

    /**
     * Split the raw "Order" string into line items. The string is a comma-separated
     * list, and each item is split on its first "x" into a quantity and an sku, e.g.
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
    private function parseOrderItems(?string $order): array
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
