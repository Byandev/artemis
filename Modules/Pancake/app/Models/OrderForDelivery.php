<?php

namespace Modules\Pancake\Models;

use App\Models\CallLog;
use App\Models\Page;
use App\Models\Shop;
use App\Models\User as AppUser;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        return $this->belongsTo(AppUser::class, 'assignee_user_id');
    }

    public function pancakeAssignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function customerCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'customer_phone')
            ->whereColumn('call_logs.user_id', 'pancake_order_for_delivery.assignee_user_id')
            ->whereColumn('call_logs.workspace_id', 'pancake_order_for_delivery.workspace_id')
            ->whereColumn('call_logs.call_date', 'pancake_order_for_delivery.delivery_date');
    }

    public function riderCallLogs(): HasMany
    {
        return $this->hasMany(CallLog::class, 'phone_number', 'rider_phone')
            ->whereColumn('call_logs.user_id', 'pancake_order_for_delivery.assignee_user_id')
            ->whereColumn('call_logs.workspace_id', 'pancake_order_for_delivery.workspace_id')
            ->whereColumn('call_logs.call_date', 'pancake_order_for_delivery.delivery_date');
    }
}
