<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id',
        'inventory_item_id',
        'date',
        'ref_no',
        'po_qty_in',
        'po_qty_out',
        'rts_goods_out',
        'rts_goods_in',
        'rts_bad',
        'lost',
        'remaining_qty',
        'is_audited',
        'inventory_remaining_stock',
    ];

    protected $casts = [
        'is_audited' => 'boolean',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
