<?php

namespace Modules\Pancake\Models;

use App\Models\CallLog;
use App\Models\Page;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;

class OrderForDelivery extends Model
{
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

    public function shop(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function conferrer(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'conferrer_id');
    }

    public function assignee(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function customerCallLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'customer_number');
    }

    public function riderCallLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'rider_phone');
    }
}
