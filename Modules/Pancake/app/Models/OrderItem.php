<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $table = 'pancake_order_items';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
