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
        'cust_po_no',
        'control_no',
        'delivery_fee',
        'total_amount',
        'status',
    ];

    protected $casts = [
        'issue_date'   => 'date:Y-m-d',
        'delivery_fee' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status'       => 'integer',
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
}
