<?php

namespace Modules\GencysERP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GencysDailySalesOrderItem extends Model
{
    protected $table = 'gencys_daily_sales_order_items';

    protected $fillable = [
        'order_id',
        'quantity',
        'sku',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(GencysDailySalesOrder::class, 'order_id');
    }
}
