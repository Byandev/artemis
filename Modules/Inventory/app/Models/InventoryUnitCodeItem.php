<?php

namespace Modules\Inventory\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A line item of an InventoryUnitCode. Linked to its parent by
 * (workspace_id, unit_code) rather than a foreign id so the row is
 * self-describing and the storage stays generic.
 */
class InventoryUnitCodeItem extends Model
{
    protected $table = 'inventory_unit_code_items';

    protected $fillable = [
        'workspace_id',
        'unit_code',
        'item_code',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function unitCode(): BelongsTo
    {
        return $this->belongsTo(InventoryUnitCode::class, 'unit_code', 'unit_code');
    }

    /**
     * The inventory item this line refers to, matched by SKU
     * (item_code = inventory_items.sku). Used to reach the item's product/shop/team
     * for visibility scoping. Not a hard foreign key — codes without a matching SKU
     * simply resolve to none.
     */
    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class, 'item_code', 'sku');
    }
}
