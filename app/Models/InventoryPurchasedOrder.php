<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InventoryPurchasedOrder extends Model
{
    protected $table = 'inventory_purchased_orders';

    protected $fillable = [
        'user_id',
        'issue_date',
        'delivery_no',
        'cust_po_no',
        'control_no',
        'item',
        'cog_amount',
        'delivery_fee',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'cog_amount' => 'float',
        'delivery_fee' => 'float',
        'total_amount' => 'float',
        'status' => 'integer',
    ];
}
