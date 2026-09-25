<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Products\Models\Product;

class InventoryItem extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'inventory_items';

    protected $fillable = [
        'workspace_id',
        'product_id',
        'parent_id',
        'is_parent',
        'sku',
        'is_active',
        'lead_time',
        'days_of_coverage',
        'unfulfilled_count',
        'three_days_average',
        'remaining_qty',
        // The item's id in Gencys ERP, stamped by the ERP sync so a later run
        // matches the same record even if its SKU is renamed there.
        'reference_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_parent' => 'boolean',
    ];

    /**
     * Team visibility flows through the item's product and that product's shops:
     * a user sees an item if they share a team with a shop selling its product.
     * Items with no product (e.g. Gencys-synced) have no team and are hidden from
     * team-scoped users (fail-closed).
     */
    protected function visibilityTeamRelation(): string
    {
        return 'product.shops.teams';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * The placeholder parent item this SKU is grouped under (null when the item
     * is standalone or is itself a parent). See is_parent.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'parent_id');
    }

    /** The child SKU variants grouped under this parent item. */
    public function children(): HasMany
    {
        return $this->hasMany(InventoryItem::class, 'parent_id');
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
