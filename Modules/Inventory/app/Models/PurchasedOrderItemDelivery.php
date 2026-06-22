<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchasedOrderItemDelivery extends Model
{
    protected $table = 'inventory_purchased_order_item_deliveries';

    protected $fillable = [
        'inventory_purchased_order_item_id',
        'delivery_date',
        'delivery_no',
        'qty',
    ];

    protected $casts = [
        'delivery_date' => 'date:Y-m-d',
        'qty' => 'integer',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(PurchasedOrderItem::class, 'inventory_purchased_order_item_id');
    }
}
