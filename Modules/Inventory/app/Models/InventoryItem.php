<?php

namespace Modules\Inventory\Models;

use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryItem extends Model
{
    protected $table = 'inventory_items';

    protected $fillable = [
        'workspace_id',
        'product_id',
        'sku',
        'is_active',
        'sales_keywords',
        'transaction_keywords',
        'lead_time',
        'unfulfilled_count',
        'three_days_average',
        'remaining_qty',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Sales keywords stored as a comma-separated string, exposed as a clean array.
     *
     * @return string[]
     */
    public function salesKeywordsList(): array
    {
        return collect(preg_split('/[,\n]+/', (string) $this->sales_keywords))
            ->map(fn ($keyword) => trim($keyword))
            ->filter()
            ->values()
            ->all();
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** Inventory transactions (stock movements) */
    public function transactions(): HasMany
    {
        return $this->hasMany(InventoryTransaction::class);
    }

    /**
     * Recompute each transaction's actual stock (remaining_qty) from its audit anchors.
     *
     * The external system's running balance (inventory_remaining_stock) is trusted for
     * the movement — the ups and downs — but not the absolute level. A physically audited
     * row (is_audited) fixes the true level; from there forward,
     *
     *     actual = external + offset,   offset = audited_count − external_at_audit
     *
     * until the next audit resets the offset. Rows before the first audit fall back to the
     * external value (offset 0). The item's own remaining_qty is refreshed from the most
     * recent transaction so the inventory list shows the corrected current stock.
     */
    public function recalculateActualStock(): void
    {
        $offset = 0;

        $transactions = $this->transactions()
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        foreach ($transactions as $transaction) {
            if ($transaction->is_audited) {
                // The audited row is the anchor: it keeps its counted value and sets the
                // offset every later row rides on.
                $offset = (int) round((float) $transaction->remaining_qty - (float) $transaction->inventory_remaining_stock);

                continue;
            }

            $actual = (int) round((float) $transaction->inventory_remaining_stock + $offset);

            if ((int) $transaction->remaining_qty !== $actual) {
                $transaction->update(['remaining_qty' => $actual]);
            }
        }

        $latest = $transactions->last();

        if ($latest && (int) $this->remaining_qty !== (int) $latest->remaining_qty) {
            $this->update(['remaining_qty' => $latest->remaining_qty]);
        }
    }

    /** All purchased order items */
    public function purchasedOrderItems(): HasMany
    {
        return $this->hasMany(PurchasedOrderItem::class);
    }

    /** Purchased order items where the parent order status = 6 (Waiting For Delivery) */
    public function waitingForDeliveryItems(): HasMany
    {
        return $this->hasMany(PurchasedOrderItem::class)
            ->whereHas('purchasedOrder', fn ($q) => $q->where('status', 6));
    }
}
