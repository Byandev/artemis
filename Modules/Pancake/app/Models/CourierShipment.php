<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourierShipment extends Model
{
    protected $table = 'courier_shipments';

    protected $guarded = [];

    protected $casts = [
        'cod' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'receivable_freight' => 'decimal:2',
        'total_shipping_cost' => 'decimal:2',
        'item_value' => 'decimal:2',
        'valuation_fee' => 'decimal:2',
        'item_weight' => 'decimal:3',
        'preferred_pickup_date' => 'date',
        'print_number' => 'integer',
        'number_of_items' => 'integer',
    ];

    public function pancakeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'pancake_order_id');
    }
}
