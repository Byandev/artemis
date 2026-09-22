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

    protected $guarded = [];

    protected $casts = [
        'call_date' => 'date',
    ];

    /** The handset's word for a call the other end declined before it connected. */
    public const TYPE_REJECTED = 'rejected';

    /**
     * The duration to store for a call of this type.
     *
     * A rejected call never connected, so there is no talk time in it. The
     * handset reports one anyway — Android hands back the seconds the phone
     * spent ringing — and stored as it comes, that time is summed into every
     * talk-time total and pushes the call past the three seconds RmoDailyStats
     * counts as connected. Zero is what it was worth.
     *
     * Matched case-insensitively: the app posts REJECTED, the page filters read
     * rejected.
     */
    public static function durationFor(?string $type, int $duration): int
    {
        return strtolower((string) $type) === self::TYPE_REJECTED ? 0 : $duration;
    }

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
