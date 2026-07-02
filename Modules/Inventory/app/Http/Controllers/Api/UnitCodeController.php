<?php

namespace Modules\Inventory\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Inventory\Models\InventoryUnitCode;
use Modules\Inventory\Models\InventoryUnitCodeItem;

/**
 * Receives the unit codes an external ERP sync (via n8n) scrapes and posts back.
 * The request is authenticated by the workspace API key in the header (the
 * `api.key` middleware) — that alone identifies the workspace — so the body is
 * just the scraped list:
 *
 *   [
 *     {
 *       "row_id": "421",                       // source row id (informational)
 *       "sku": "BD0046-…",
 *       "unit_code": "Pikutin 4pcs Padrino …", // unique per workspace — upsert key
 *       "total_amount": "3,552.00",
 *       "items": [
 *         { "sku": "Pikutin Padrino 1.0", "quantity": 4 },
 *         …
 *       ]
 *     },
 *     …
 *   ]
 *
 * A wrapped `{ "unit_codes": [...] }` / `{ "data": [...] }` body is also
 * tolerated. Rows are stored in the generic, source-agnostic
 * inventory_unit_codes / inventory_unit_code_items tables: each unit code is
 * upserted on (workspace_id, unit_code) and its items are replaced wholesale.
 */
class UnitCodeController extends Controller
{
    public function bulkSync(Request $request): JsonResponse
    {
        $workspace = $request->attributes->get('workspace');

        // n8n posts the flat list; tolerate a wrapped { unit_codes|data: [...] }.
        $payload = $request->all();
        $rows = $request->array('data', []);

        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($rows, $workspace, &$created, &$updated, &$skipped) {
            foreach ($rows as $row) {
                // The unit code (unique per workspace) is the stable upsert key.
                $unitCodeValue = $this->str($row['Code'] ?? $row['unit_code'] ?? $row['unitCode'] ?? null);

                if ($unitCodeValue === null) {
                    $skipped++;

                    continue;
                }

                $record = InventoryUnitCode::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'unit_code' => $unitCodeValue],
                    [
                        'sku' => $this->str($row['SKU'] ?? $row['sku'] ?? null),
                        'total_amount' => $this->decimalOrNull($row['Total Amount'] ?? $row['total_amount'] ?? null),
                    ],
                );
                $record->wasRecentlyCreated ? $created++ : $updated++;

                $this->syncItems($workspace->id, $unitCodeValue, $row['items'] ?? []);
            }
        });

        return response()->json([
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Replace a unit code's items with the set in the payload. The payload item's
     * `sku` is the item code; rows are linked by (workspace_id, unit_code).
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    private function syncItems(int $workspaceId, string $unitCode, mixed $items): void
    {
        InventoryUnitCodeItem::where('workspace_id', $workspaceId)
            ->where('unit_code', $unitCode)
            ->delete();

        $rows = collect(is_array($items) ? $items : [])
            ->map(fn ($item) => [
                'workspace_id' => $workspaceId,
                'unit_code' => $unitCode,
                'item_code' => $this->str($item['sku'] ?? $item['item_code'] ?? $item['inventory_item_code'] ?? null),
                'quantity' => $this->intOrNull($item['quantity'] ?? null),
            ])
            ->filter(fn ($item) => $item['item_code'] !== null)
            ->values()
            ->all();

        if (! empty($rows)) {
            InventoryUnitCodeItem::insert($rows);
        }
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

    /** Parse "42.00" → 42.00, "1,888.00" → 1888.00, "" → null. */
    private function decimalOrNull(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        // Strip thousand separators / currency symbols so "1,888.00" doesn't get
        // truncated to 1.0 by PHP's float cast (which stops at the first comma).
        $clean = is_string($value) ? preg_replace('/[^0-9.\-]/', '', $value) : $value;

        return ($clean === '' || $clean === null) ? null : (float) $clean;
    }
}
