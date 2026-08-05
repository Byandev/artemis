<?php

namespace Modules\Inventory\Models;

use App\Models\Concerns\ScopesToVisibleTeams;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasedOrder extends Model
{
    use ScopesToVisibleTeams;

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

    /**
     * The procurement workflow stages. Mirrored on the frontend by
     * resources/js/constants/purchased-order-statuses.ts — keep the two in sync
     * (PurchasedOrderStatusParityTest enforces it).
     */
    public const STATUSES = [
        1 => 'For Approval',
        2 => 'Approved',
        3 => 'To Pay',
        4 => 'Paid',
        5 => 'For Purchase',
        6 => 'Waiting For Delivery',
        7 => 'Delivered',
        8 => 'Cancelled',
        9 => 'Manually Closed',
    ];

    public const DELIVERED = 7;

    public const CANCELLED = 8;

    /**
     * Manually closed at our end even though the source hasn't marked it
     * Delivered/Cancelled — a deliberate override to stop the order counting as
     * owed stock. Terminal, like Delivered/Cancelled.
     */
    public const MANUALLY_CLOSED = 9;

    /**
     * Terminal statuses — the order no longer owes stock, so its outstanding
     * quantities drop out of incoming-stock and reorder maths.
     * Exact complement of AWAITING_DELIVERY_STATUSES.
     */
    public const CLOSED_STATUSES = [self::DELIVERED, self::CANCELLED, self::MANUALLY_CLOSED];

    /**
     * Statuses that leave an order still owing stock — everything not yet
     * Delivered or Cancelled, including the pre-approval stages. Widened from
     * [4, 5, 6] in d03c4bd2 so an order counts toward incoming stock from the
     * moment it is raised, not only once paid.
     */
    public const AWAITING_DELIVERY_STATUSES = [1, 2, 3, 4, 5, 6];

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? 'Unknown';
    }

    /**
     * Team visibility fans out through the order's line items: the order is
     * visible if any of its items' inventory items reach the user's team.
     */
    protected function visibilityTeamRelation(): string
    {
        return 'items.inventoryItem.product.shops.teams';
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
     * Null only when no expected date is set. An order that has been fully delivered
     * is settled and never goes on to become delayed; anything still owing stock is
     * delayed once the expected date has passed.
     *
     * Note this measures the order against its deadline as of today, not when each
     * delivery actually landed — a fully-delivered order reads "ontime" even if the
     * final delivery was late.
     */
    public function getDeliveryTimelinessAttribute(): ?string
    {
        $expected = $this->expected_delivery_date;

        if (! $expected) {
            return null;
        }

        if ($this->fulfillment_status === 'delivered') {
            return 'ontime';
        }

        return now()->startOfDay()->gt($expected) ? 'delayed' : 'ontime';
    }

    /** Total quantity still owed across the order's line items. Requires `items.deliveries`. */
    public function outstandingBalance(): int
    {
        $this->loadMissing('items.deliveries');

        return (int) $this->items->sum(fn (PurchasedOrderItem $item) => $item->balance);
    }
}
