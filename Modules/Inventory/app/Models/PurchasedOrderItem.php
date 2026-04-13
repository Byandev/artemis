<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasedOrderItem extends Model
{
    protected $table = 'inventory_purchased_order_items';

    protected $fillable = [
        'inventory_purchased_order_id',
        'inventory_item_id',
        'count',
        'amount',
        'total_amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function purchasedOrder(): BelongsTo
    {
        return $this->belongsTo(PurchasedOrder::class, 'inventory_purchased_order_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
