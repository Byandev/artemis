<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\OrderForDelivery;

class CallLog extends Model
{
    use HasFactory;

    /**
     * Stands in for a number the syncing app could not read.
     *
     * A handset withholds the number on a private call, and some call-log
     * entries carry none at all. The call still happened and still counts, so
     * it is stored under this placeholder rather than dropped — phone_number is
     * part of the upsert key, and a null there would match nothing, turning
     * every re-sync of the same call into another row.
     */
    public const UNKNOWN_PHONE = '<unknown>';

    protected $guarded = [];

    protected $casts = [
        'call_date' => 'date',
    ];

    /**
     * The Pancake order the call was about, when it could be matched to one.
     *
     * No foreign key backs this — pancake_orders is synced from a third party,
     * so the row may be gone even though the id is still on the call.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    /**
     * The delivery row the call matched.
     *
     * Narrower than order(): the same order loaded for delivery on two days has
     * two of these, and this is the one the call was actually placed against.
     * Unconstrained for the same reason as order() — the row may be gone.
     */
    public function orderForDelivery(): BelongsTo
    {
        return $this->belongsTo(OrderForDelivery::class, 'order_for_delivery_id');
    }
}
