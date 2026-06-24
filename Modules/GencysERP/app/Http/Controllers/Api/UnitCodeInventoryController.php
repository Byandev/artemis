<?php

namespace Modules\GencysERP\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkspaceApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Modules\GencysERP\Models\GencysUnitCode;

/**
 * Receives the inventory items that the n8n flow scrapes from Gencys ERP for a
 * single unit code and posts back. The payload is the list n8n sends:
 *
 *   [ { "api_key": "art_…", "row_id": 411,
 *       "items": [ { "item": "Pikutin Sputing 2.0", "qty": "1", "cog": "180" }, … ] } ]
 *
 * `row_id` is Gencys' own unit code id (matched against the row_id column) so
 * items map to the right unit code. `items` accepts code strings or objects
 * (item/itemCode, qty, cog).
 */
class UnitCodeInventoryController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Inspect exactly what n8n sends. Remove once the flow is verified.
        Log::info('Gencys unit code inventory request', [
            'method' => $request->method(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'body' => $request->all(),
        ]);

        $payload = $request->all();

        // Accept either a single entry object or n8n's list of entries.
        $entries = array_is_list($payload) ? $payload : [$payload];

        $updated = 0;
        $itemsSaved = 0;
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

            // Resolve the unit code by Gencys' own id, stored in the row_id column.
            $rowId = $this->intOrNull($entry['row_id'] ?? null);

            $unitCode = $rowId === null ? null : GencysUnitCode::query()
                ->where('workspace_id', $workspace->id)
                ->where('row_id', $rowId)
                ->first();

            if (! $unitCode) {
                $errors[] = "entry {$i}: unit code not found for this workspace (row_id {$rowId})";

                continue;
            }

            $rows = $this->parseItems(
                $unitCode->unit_code,
                Arr::get($entry, 'items', Arr::get($entry, 'Items', [])),
            );

            // Replace the unit code's items so re-syncs stay idempotent.
            $unitCode->items()->delete();

            if (empty($rows)) {
                $skipped++;

                continue;
            }

            $unitCode->items()->createMany($rows);
            $updated++;
            $itemsSaved += count($rows);
        }

        return response()->json([
            'unit_codes_updated' => $updated,
            'items_saved' => $itemsSaved,
            'skipped' => $skipped,
            'errors' => $errors,
        ], empty($errors) ? 200 : 207);
    }

    /**
     * Normalize the inventory item rows. Each line's `unit_code` mirrors the
     * parent. Two shapes are accepted:
     *
     *   - a plain string item code: "Pikutin Sputing 2.0"
     *   - an object as n8n sends it:
     *       { "item": "Pikutin Sputing 2.0", "itemCode": "…", "qty": "1", "cog": "180" }
     *
     * The item code falls back across `inventory_item_code` / `itemCode` / `item`
     * (itemCode is often null, so the `item` name is used); quantity reads
     * `quantity`/`qty`, and price reads `price`/`cog`.
     *
     * @param  mixed  $items
     * @return array<int, array{unit_code: ?string, inventory_item_code: string, quantity: ?int, price: ?float}>
     */
    private function parseItems(?string $unitCode, $items): array
    {
        if (! is_array($items)) {
            return [];
        }

        $rows = [];

        foreach ($items as $item) {
            $code = is_array($item)
                ? $this->str(
                    $item['inventory_item_code']
                    ?? $item['itemCode']
                    ?? $item['Inventory Item Code']
                    ?? $item['item']
                    ?? null
                )
                : $this->str($item);

            if ($code === null) {
                continue;
            }

            $rows[] = [
                'unit_code' => $unitCode,
                'inventory_item_code' => $code,
                'quantity' => is_array($item) ? $this->intOrNull($item['quantity'] ?? $item['qty'] ?? $item['Quantity'] ?? null) : null,
                'price' => is_array($item) ? $this->decimalOrNull($item['price'] ?? $item['cog'] ?? $item['Price'] ?? null) : null,
            ];
        }

        return $rows;
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
}
