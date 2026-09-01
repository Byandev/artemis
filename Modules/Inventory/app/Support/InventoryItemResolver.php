<?php

namespace Modules\Inventory\Support;

use Modules\Inventory\Models\InventoryItem;

/**
 * Matches the item names an ERP report comes back with to this workspace's
 * inventory items, creating the ones we have never seen.
 *
 * The ERP reports an item by its name, not by any id of ours, so the join is a
 * string match: first the item's own SKU, then any of its transaction keywords —
 * which exist precisely because the ERP's spelling of an item and ours drift
 * apart. Matching ignores case and collapsed whitespace, since that is the only
 * difference in most of the near-misses.
 *
 * A name that still matches nothing becomes a new item rather than a dropped
 * row: the report is the ERP's own record of stock movement, and silently
 * skipping part of it leaves the ledger short with nothing to show for it. New
 * items are created inactive, so they surface for someone to name properly and
 * attach to a product without being counted as live stock in the meantime.
 *
 * Built per workspace and per callback: the whole item list is read once up
 * front, so resolving a few hundred names costs one query rather than a few
 * hundred.
 */
class InventoryItemResolver
{
    /** Items keyed by every normalised name they answer to. */
    private array $byName = [];

    /** @var array<int, InventoryItem> */
    private array $created = [];

    public function __construct(private readonly int $workspaceId)
    {
        InventoryItem::query()
            ->where('workspace_id', $this->workspaceId)
            ->get(['id', 'workspace_id', 'sku', 'transaction_keywords'])
            ->each(function (InventoryItem $item) {
                // The SKU wins: a keyword is a fallback spelling, and one item's
                // keyword must never shadow another item's actual name.
                $this->remember($item->sku, $item, overwrite: true);

                foreach ($this->keywords($item) as $keyword) {
                    $this->remember($keyword, $item);
                }
            });
    }

    /** The item this ERP name refers to, or null if we don't know it. */
    public function match(?string $name): ?InventoryItem
    {
        $key = self::normalise($name);

        return $key === '' ? null : ($this->byName[$key] ?? null);
    }

    /**
     * The item this ERP name refers to, creating it if we've never seen it.
     * Returns null only for a blank name, which is nothing to create.
     */
    public function resolve(?string $name): ?InventoryItem
    {
        if ($existing = $this->match($name)) {
            return $existing;
        }

        $sku = self::clean($name);

        if ($sku === '') {
            return null;
        }

        // firstOrCreate rather than create: inventory_items is unique on
        // (workspace_id, sku), and two callbacks can land at once.
        $item = InventoryItem::firstOrCreate(
            ['workspace_id' => $this->workspaceId, 'sku' => $sku],
            ['is_active' => false, 'is_parent' => false],
        );

        $this->remember($sku, $item, overwrite: true);

        if ($item->wasRecentlyCreated) {
            $this->created[$item->id] = $item;
        }

        return $item;
    }

    /** Whether this item was created by this callback rather than found. */
    public function wasCreated(InventoryItem $item): bool
    {
        return isset($this->created[$item->id]);
    }

    /** @return array<int, InventoryItem> */
    public function createdItems(): array
    {
        return array_values($this->created);
    }

    private function remember(?string $name, InventoryItem $item, bool $overwrite = false): void
    {
        $key = self::normalise($name);

        if ($key === '' || (! $overwrite && isset($this->byName[$key]))) {
            return;
        }

        $this->byName[$key] = $item;
    }

    /**
     * An item's transaction keywords, stored comma- or newline-separated.
     *
     * @return array<int, string>
     */
    private function keywords(InventoryItem $item): array
    {
        return collect(preg_split('/[,\n]+/', (string) $item->transaction_keywords))
            ->map(fn (string $keyword) => trim($keyword))
            ->filter()
            ->all();
    }

    /** The name as we'd store it: trimmed, with runs of whitespace collapsed. */
    private static function clean(?string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $name));
    }

    /** The name as we compare it: cleaned and case-folded. */
    private static function normalise(?string $name): string
    {
        return mb_strtolower(self::clean($name));
    }
}
