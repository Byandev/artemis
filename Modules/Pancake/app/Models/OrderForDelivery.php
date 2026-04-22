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
        return $this->hasMany(CallLog::class, 'phone_number', 'customer_phone')
            ->whereColumn('call_logs.user_id', 'pancake_order_for_delivery.assignee_id')
            ->whereColumn('call_logs.workspace_id', 'pancake_order_for_delivery.workspace_id')
            ->whereColumn('call_logs.call_date', 'pancake_order_for_delivery.delivery_date');
    }

    public function riderCallLogs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'rider_phone')
            ->whereColumn('call_logs.user_id', 'pancake_order_for_delivery.assignee_id')
            ->whereColumn('call_logs.workspace_id', 'pancake_order_for_delivery.workspace_id')
            ->whereColumn('call_logs.call_date', 'pancake_order_for_delivery.delivery_date');
    }
}
