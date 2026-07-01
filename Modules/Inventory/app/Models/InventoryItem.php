<?php

namespace Modules\Inventory\Models;

use App\Models\Product;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

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

    /** Manual physical-count adjustments (discrepancy ledger) */
    public function discrepancies(): HasMany
    {
        return $this->hasMany(InventoryItemDiscrepancy::class);
    }

    /**
     * The most recent physical-count adjustment. Its signed discrepancy is layered
     * onto the transaction ledger to produce the item's displayed remaining stock —
     * see InventoryItemController::buildQuery() for the read-side of this.
     */
    public function latestDiscrepancy(): HasOne
    {
        return $this->hasOne(InventoryItemDiscrepancy::class)
            ->latest('date')
            ->latest('id');
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
