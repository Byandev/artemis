<?php

namespace Modules\Pancake\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReturnedOrder extends Model
{
    protected $table = 'pancake_returned_orders';

    protected $guarded = [];

    protected $casts = [
        'scanned_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}