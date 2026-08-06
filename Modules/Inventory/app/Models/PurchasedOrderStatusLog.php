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
