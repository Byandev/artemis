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
        'sales_keywords',
        'transaction_keywords',
        'lead_time',
        'unfulfilled_count',
        'three_days_average',
        'remaining_qty',
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
