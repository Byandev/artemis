<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory, ScopesToVisibleTeams;

    protected $table = 'inventory_transactions';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id',
        'inventory_item_id',
        'date',
        'ref_no',
        'number',
        'po_qty_in',
        'po_qty_out',
        'rts_goods_out',
        'rts_goods_in',
        'rts_bad',
        'lost',
        'remaining_qty',
        'inventory_remaining_stock',
    ];

    /** Team visibility flows through the transaction's inventory item. */
    protected function visibilityTeamRelation(): string
    {
        return 'inventoryItem.product.shops.teams';
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
