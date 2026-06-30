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
     * Recompute each transaction's running stock (remaining_qty) as a ledger.
     *
     * A physically audited row (is_audited) sets the starting balance — its counted
     * remaining_qty is taken as-is. From there every later row rolls the balance forward
     * by its own net movement (goods in − out − bad − lost; see
     * InventoryTransaction::netMovement), until the next audit re-anchors it. Before the
     * first audit there's no counted level, so the earliest row seeds from the external
     * stock (inventory_remaining_stock) when present. The item's own remaining_qty is
     * refreshed from the most recent transaction so the inventory list shows current stock.
     */
    public function recalculateActualStock(): void
    {
        $transactions = $this->transactions()
            ->orderBy('date')
            ->orderBy('id')
            ->get();

        $running = null;

        foreach ($transactions as $transaction) {
            if ($transaction->is_audited) {
                // The counted level is the new starting balance; trust it as-is.
                $running = (int) $transaction->remaining_qty;

                continue;
            }

            if ($running === null) {
                // No audit yet: seed from the external stock if we have one.
                $running = $transaction->inventory_remaining_stock !== null
                    ? (int) round((float) $transaction->inventory_remaining_stock)
                    : $transaction->netMovement();
            } else {
                $running += $transaction->netMovement();
            }

            if ((int) $transaction->remaining_qty !== $running) {
                $transaction->update(['remaining_qty' => $running]);
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
