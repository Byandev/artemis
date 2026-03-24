<?php

namespace Modules\Pancake\Models;

use App\Models\Page;
use Illuminate\Database\Eloquent\Model;

class OrderForDelivery extends Model
{
    /**
     * The attributes that are mass assignable.
     */
    protected $guarded = [];

    protected $table = 'pancake_order_for_delivery';

    public function order(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function page(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function conferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'conferrer_id');
    }
}
