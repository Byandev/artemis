<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A generic, source-agnostic unit code (e.g. a bundle/SKU grouping) and its
 * total amount. Items are linked by (workspace_id, unit_code) — see
 * InventoryUnitCodeItem — so the storage isn't tied to any one ERP.
 */
class InventoryUnitCode extends Model
{
    use ScopesToVisibleTeams;

    protected $table = 'inventory_unit_codes';

    protected $fillable = [
        'workspace_id',
        'unit_code',
        'sku',
        'total_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    /**
     * Team visibility runs through the unit code's items: each item's code maps
     * to an inventory item by SKU (inventory_unit_code_items.item_code =
     * inventory_items.sku), and that item reaches the team via its product's
     * shops. A unit code is visible if any of its items resolves to the team.
     */
    protected function visibilityTeamRelation(): string
    {
        return 'items.inventoryItem.product.shops.teams';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Items belonging to this unit code. Linked on the unit_code string; callers
     * scope by workspace_id (unit_code is unique per workspace) when loading.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryUnitCodeItem::class, 'unit_code', 'unit_code');
    }
}
