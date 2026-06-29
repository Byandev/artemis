<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchasedOrderItem extends Model
{
    protected $table = 'inventory_purchased_order_items';

    protected $fillable = [
        'inventory_purchased_order_id',
        'inventory_item_id',
        'count',
        'amount',
        'total_amount',
        'remarks',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * Derived monitoring attributes appended to the serialized model.
     *
     * @var list<string>
     */
    protected $appends = [
        'delivered_qty',
        'balance',
        'fulfillment_status',
    ];

    public function purchasedOrder(): BelongsTo
    {
        return $this->belongsTo(PurchasedOrder::class, 'inventory_purchased_order_id');
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(PurchasedOrderItemDelivery::class, 'inventory_purchased_order_item_id');
    }

    /** Nothing delivered yet (deliveries always carry a qty >= 1). */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->whereDoesntHave('deliveries');
    }

    /** Some — but not all — of the ordered quantity has been delivered. */
    public function scopePartiallyDelivered(Builder $query): Builder
    {
        return $query->whereHas('deliveries')
            ->where('count', '>', $this->deliveredQtySubquery());
    }

    /** The full ordered quantity (or more) has been delivered. */
    public function scopeFullyDelivered(Builder $query): Builder
    {
        return $query->whereHas('deliveries')
            ->where('count', '<=', $this->deliveredQtySubquery());
    }

    /** Correlated subquery: total quantity delivered for the current item row. */
    protected function deliveredQtySubquery(): Builder
    {
        return PurchasedOrderItemDelivery::query()
            ->selectRaw('coalesce(sum(qty), 0)')
            ->whereColumn('inventory_purchased_order_item_id', $this->qualifyColumn('id'));
    }

    /**
     * Total quantity delivered so far. Prefers the `delivered_qty` aggregate
     * selected by the monitoring query; falls back to the loaded relation.
     */
    public function getDeliveredQtyAttribute(): int
    {
        if (array_key_exists('delivered_qty', $this->attributes)) {
            return (int) $this->attributes['delivered_qty'];
        }

        return (int) $this->deliveries->sum('qty');
    }

    /** Quantity still owed against the ordered count (never negative). */
    public function getBalanceAttribute(): int
    {
        return max(0, (int) $this->count - $this->delivered_qty);
    }

    /** waiting | partial | delivered */
    public function getFulfillmentStatusAttribute(): string
    {
        $delivered = $this->delivered_qty;

        if ($delivered <= 0) {
            return 'waiting';
        }

        if ($delivered >= (int) $this->count) {
            return 'delivered';
        }

        return 'partial';
    }
}
