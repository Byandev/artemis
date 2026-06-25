<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\InventoryTransaction;

class SyncErpTransactionHistory implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<int, array<string, mixed>>  $rows  Raw ERP transaction rows.
     */
    public function __construct(
        public int $workspaceId,
        public array $rows,
    ) {
        $this->onQueue('erp');
    }

    public function handle(): void
    {
        $keywordToItemId = $this->keywordMap($this->workspaceId);

        // Per item, track the most recent transaction's remaining qty — that is the
        // item's current stock, written to the inventory item after the rows are saved.
        $latestStockByItem = [];

        // Write in chunks so a large ERP batch isn't one giant transaction.
        collect($this->rows)->chunk(200)->each(function ($chunk) use ($keywordToItemId, &$latestStockByItem) {
            DB::transaction(function () use ($chunk, $keywordToItemId, &$latestStockByItem) {
                foreach ($chunk as $row) {
                    $itemId = $this->resolveItemId((string) ($row['Items'] ?? ''), $keywordToItemId);

                    if (! $itemId) {
                        continue;
                    }

                    $date = $this->parseDate($row['Transaction Date'] ?? null);
                    $remainingQty = (int) ($row['Remaining Qty'] ?? 0);

                    $transaction = InventoryTransaction::firstOrNew([
                        'workspace_id' => $this->workspaceId,
                        'inventory_item_id' => $itemId,
                        'ref_no' => (string) ($row['No'] ?? ''),
                    ]);

                    $transaction->fill([
                        'date' => $date,
                        'po_qty_in' => (int) ($row['qty_in'] ?? 0),
                        'po_qty_out' => (int) ($row['qty_out'] ?? 0),
                        'rts_goods_in' => (int) ($row['rts_goods_in'] ?? 0),
                        'rts_goods_out' => (int) ($row['rts_goods_out'] ?? 0),
                        'rts_bad' => (int) ($row['In - Damage (RTS)'] ?? 0),
                        'lost' => (int) ($row['lost'] ?? 0),
                        'inventory_remaining_stock' => $remainingQty,
                    ]);

                    // Manual Remaining Qty defaults to the ERP stock the first time the
                    // row is imported; once it exists, manual edits are preserved.
                    if (! $transaction->exists) {
                        $transaction->remaining_qty = $remainingQty;
                    }

                    $transaction->save();

                    // Keep the remaining qty of the latest-dated row per item.
                    if ($date !== null
                        && (! isset($latestStockByItem[$itemId])
                            || $date >= $latestStockByItem[$itemId]['date'])) {
                        $latestStockByItem[$itemId] = [
                            'date' => $date,
                            'remaining_qty' => $remainingQty,
                        ];
                    }
                }
            });
        });

        // Save each item's current stock from its most recent transaction's remaining qty.
        foreach ($latestStockByItem as $itemId => $stock) {
            InventoryItem::where('id', $itemId)->update([
                'remaining_qty' => $stock['remaining_qty'],
            ]);
        }
    }

    /**
     * Map each inventory item's transaction keywords to its id, normalized for matching.
     *
     * @return array<string, int>
     */
    private function keywordMap(int $workspaceId): array
    {
        $map = [];

        InventoryItem::where('workspace_id', $workspaceId)
            ->whereNotNull('transaction_keywords')
            ->where('transaction_keywords', '!=', '')
            ->get(['id', 'transaction_keywords'])
            ->each(function ($item) use (&$map) {
                foreach (preg_split('/[,\n]+/', (string) $item->transaction_keywords) as $keyword) {
                    $normalized = $this->normalize($keyword);

                    if ($normalized !== '') {
                        $map[$normalized] = $item->id;
                    }
                }
            });

        return $map;
    }

    /** Resolve an ERP "Items" name to an inventory item id via its transaction keywords. */
    private function resolveItemId(string $itemName, array $keywordToItemId): ?int
    {
        $normalized = $this->normalize($itemName);

        if ($normalized === '') {
            return null;
        }

        // Exact match first, then fall back to a containment match either direction so
        // a keyword like "cooling patch" still matches "anti-stroke cooling patch".
        if (isset($keywordToItemId[$normalized])) {
            return $keywordToItemId[$normalized];
        }

        foreach ($keywordToItemId as $keyword => $itemId) {
            if (Str::contains($normalized, $keyword) || Str::contains($keyword, $normalized)) {
                return $itemId;
            }
        }

        return null;
    }

    /** Lowercase + collapse whitespace so ERP names and stored keywords compare cleanly. */
    private function normalize(string $value): string
    {
        return trim(Str::lower(preg_replace('/\s+/', ' ', $value)));
    }

    /** Parse the ERP's "June 24, 2026" (or d/m/Y) date string into Y-m-d, or null. */
    private function parseDate(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        foreach (['F j, Y', 'd/m/Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                // Try the next format.
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
