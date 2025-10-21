<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $guarded = [];

    public function shippingAddress(): \Illuminate\Database\Eloquent\Relations\HasOne|Order
    {
        return $this->hasOne(ShippingAddress::class);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }
}
