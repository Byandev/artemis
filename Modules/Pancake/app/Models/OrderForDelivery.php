<?php

namespace Modules\Pancake\Models;

use App\Models\CallLog;
use App\Models\Page;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Modules\GencysERP\Models\GencysDailySalesOrder;

class OrderForDelivery extends Model
{
    protected $guarded = [];

    protected $table = 'pancake_order_for_delivery';

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function conferrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'conferrer_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * The Gencys order behind this delivery, matched through the parent order on
     * the only key the two systems share: the waybill.
     *
     * NOTE: this is deliberately not workspace-scoped. `gencys_orders` is unique
     * on (workspace_id, tracking_number), not on tracking_number alone, so every
     * caller must add `->where('gencys_orders.workspace_id', ...)` itself — the
     * filter can't live in the relation because the parent table isn't joined
     * when Eloquent eager-loads a has-one-through.
     */
    public function gencysOrder(): HasOneThrough
    {
        return $this->hasOneThrough(
            GencysDailySalesOrder::class,
            Order::class,
            'id',              // pancake_orders.id matches...
            'tracking_number', // gencys_orders.tracking_number matches...
            'order_id',        // ...this row's order_id
            'tracking_code',   // ...that order's tracking_code
        );
    }

    public function customerCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'customer_phone')
            ->whereColumn('call_logs.user_id', 'pancake_order_for_delivery.assignee_id')
            ->whereColumn('call_logs.workspace_id', 'pancake_order_for_delivery.workspace_id')
            ->whereColumn('call_logs.call_date', 'pancake_order_for_delivery.delivery_date');
    }

    public function riderCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'rider_phone')
            ->whereColumn('call_logs.user_id', 'pancake_order_for_delivery.assignee_id')
            ->whereColumn('call_logs.workspace_id', 'pancake_order_for_delivery.workspace_id')
            ->whereColumn('call_logs.call_date', 'pancake_order_for_delivery.delivery_date');
    }
}
