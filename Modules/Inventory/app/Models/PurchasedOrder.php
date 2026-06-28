<?php

namespace Modules\Inventory\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasedOrder extends Model
{
    protected $table = 'inventory_purchased_orders';

    protected $fillable = [
        'workspace_id',
        'issue_date',
        'delivery_no',
        'expected_delivery_date',
        'cust_po_no',
        'control_no',
        'delivery_fee',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'issue_date' => 'date:Y-m-d',
        'expected_delivery_date' => 'date:Y-m-d',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => 'integer',
    ];

    /**
     * Derived monitoring attributes appended to the serialized model. They read
     * the order's items, so eager-load `items.deliveries` before serializing.
     *
     * @var list<string>
     */
    protected $appends = [
        'fulfillment_status',
        'delivery_timeliness',
    ];

    public const STATUSES = [
        1 => 'For Approval',
        2 => 'Approved',
        3 => 'To Pay',
        4 => 'Paid',
        5 => 'For Purchase',
        6 => 'Waiting For Delivery',
        7 => 'Delivered',
        8 => 'Cancelled',
    ];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchasedOrderItem::class, 'inventory_purchased_order_id');
    }

    /** Aggregate fulfilment across the order's items: waiting | partial | delivered. */
    public function getFulfillmentStatusAttribute(): string
    {
        if (! $this->relationLoaded('items')) {
            return 'waiting';
        }

        $items = $this->items;

        if ($items->isEmpty() || $items->sum->delivered_qty <= 0) {
            return 'waiting';
        }

        return $items->every(fn ($item) => $item->balance <= 0) ? 'delivered' : 'partial';
    }

    /**
     * Schedule status against the order's expected delivery date: ontime | delayed | null.
     * Null only when no expected date is set; otherwise driven solely by that date —
     * anything not fully delivered once the expected date has passed is delayed.
     */
    public function getDeliveryTimelinessAttribute(): ?string
    {
        $expected = $this->expected_delivery_date;

        if (! $expected) {
            return null;
        }

        return now()->startOfDay()->gt($expected) ? 'delayed' : 'ontime';
    }
}
