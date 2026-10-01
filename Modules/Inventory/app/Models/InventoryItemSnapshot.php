<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Products\Models\Product;

/**
 * A frozen daily copy of an inventory item — every stored column plus the computed
 * metrics as they read on `snapshot_date`. Written once a day by
 * `inventory:snapshot-items` and read back when the items list is filtered to a date.
 */
class InventoryItemSnapshot extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'inventory_item_snapshots';

    protected $guarded = [];

    protected $casts = [
        'snapshot_date' => 'date:Y-m-d',
        'discrepancy_date' => 'date:Y-m-d',
        'product_winning_date' => 'date:Y-m-d',
        'item_created_at' => 'datetime',
        'is_active' => 'boolean',
        'is_parent' => 'boolean',
    ];

    /**
     * Team scoping walks the same path as the live item — the product's shop's
     * teams — so a snapshot is visible to exactly the people the item itself is.
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

    /** Sibling snapshot rows grouped under the same parent, on the same date. */
    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'inventory_item_id')
            ->where('snapshot_date', $this->snapshot_date);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'inventory_item_id');
    }
}
