<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];

    protected $table = 'pancake_order_items';

    protected $casts = [
        // What this line's goods cost — all of its `quantity` units together,
        // not the cost of one. Null means no cost has been recorded.
        'cogs' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
