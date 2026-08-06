<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One stage transition in the ERP's audit trail for a purchase order. Written
 * only by the sync (App\Http\Controllers\PublicApi\PurchaseOrderController),
 * which replaces an order's whole trail each time it runs.
 */
class PurchasedOrderStatusLog extends Model
{
    protected $table = 'inventory_purchased_order_status_logs';

    protected $fillable = [
        'inventory_purchased_order_id',
        'status',
        'by',
        'detail',
        'logged_at',
    ];

    protected $casts = [
        'logged_at' => 'datetime',
    ];

    /**
     * The ERP's label for the stage where payment is recorded. Compared
     * case-insensitively — the label is stable but the free-text `detail`
     * beside it is not ("Paid", "paid-50%", "PAID- 50%").
     */
    public const PAID = 'paid';

    /**
     * Keep the order's denormalised `paid_at` in step with its trail, whatever
     * writes the trail.
     *
     * The sync used to derive paid_at inline, which meant any other writer left
     * it null — 33 Paid entries once produced zero paid dates. Hanging it off
     * the log model instead makes the column a property of the data rather than
     * of one code path. Bulk writers that bypass events (see the sync's
     * insert()) must call PurchasedOrder::recalculatePaidAt() themselves.
     */
    protected static function booted(): void
    {
        $refresh = fn (self $log) => $log->purchasedOrder?->recalculatePaidAt();

        static::created($refresh);
        static::updated($refresh);
        static::deleted($refresh);
    }

    public function purchasedOrder(): BelongsTo
    {
        return $this->belongsTo(PurchasedOrder::class, 'inventory_purchased_order_id');
    }

    /** The entries recording payment, whatever case the ERP sent them in. */
    public function scopePaid(Builder $query): Builder
    {
        return $query->whereRaw('LOWER(TRIM(status)) = ?', [self::PAID]);
    }
}
