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
 * Receives the unit codes that the n8n flow scrapes from Gencys ERP and posts
 * back. The payload is the list n8n sends:
 *
 *   [ { "workspace_id": 1, "api_key": "art_…", "webhook_url": "…",
 *       "unit_codes": [
 *         { "row_id": "411", "SKU": "BD0046-…", "Code": "Pikutin Perfume …",
 *           "Total Amount": "888.00" },
 *         … ] } ]
 *
 * Each entry is authenticated by its own `api_key` (n8n puts it in the body).
 * Unit codes are upserted on Gencys' own id (sent as "row_id"/"id"), stored in
 * the row_id column. Inventory items are NOT saved here — the dedicated
 * unit-code-inventory fetch populates those.
 */
class UnitCodeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        // Inspect exactly what n8n sends. Remove once the flow is verified.
        Log::info('Gencys unit code request', [
            'method' => $request->method(),
            'ip' => $request->ip(),
            'content_type' => $request->header('Content-Type'),
            'body' => $request->all(),
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

            // n8n sends the rows under "unit_codes"; tolerate "data" too.
            $rows = Arr::get($entry, 'unit_codes', Arr::get($entry, 'data', []));

            foreach ($rows as $row) {
                // Gencys' own unit code id ("row_id"/"id" in the payload) is the
                // stable upsert key, stored in the row_id column.
                $rowId = $this->intOrNull($row['row_id'] ?? $row['id'] ?? null);

                if ($rowId === null) {
                    $skipped++;

                    continue;
                }

                $record = GencysUnitCode::updateOrCreate(
                    ['workspace_id' => $workspace->id, 'row_id' => $rowId],
                    [
                        'sku' => $this->str($row['SKU'] ?? $row['sku'] ?? null),
                        'unit_code' => $this->str($row['Code'] ?? $row['unit_code'] ?? $row['unitCode'] ?? null),
                        'total_amount' => $this->decimalOrNull($row['Total Amount'] ?? $row['total_amount'] ?? null),
                    ],
                );
                $record->wasRecentlyCreated ? $created++ : $updated++;

                // Inventory items are populated by the dedicated unit-code-inventory
                // fetch (gencys-erp:trigger-fetch-unit-code-inventory), not here.
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
}
