<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GencysDailySalesOrder extends Model
{
    protected $table = 'gencys_daily_sales_orders';

    // `id` holds Gencys' own order id (sent as "id" in the payload), so it is
    // assigned explicitly rather than auto-incremented.
    public $incrementing = false;

    protected $fillable = [
        'id',
        'workspace_id',
        'order_no',
        'order_date',
        'csr',
        'verifier_name',
        'upsell_by',
        'customer_name',
        'address',
        'province',
        'city',
        'brgy',
        'contact',
        'order_details',
        'total_qty',
        'price_final',
        'price_initial',
        'shipping_fee',
        'page',
        'platform',
        'tracking_number',
        'courier',
        'parcel_status',
        'order_status',
        'mop',
        'encoded_date',
        'parcel_updated_date',
        'shipped_out_date',
        'date_added',
        'price_upsell',
        'intern_brands_name',
        'total_cog',
    ];

    protected $casts = [
        'order_date' => 'datetime',
        'encoded_date' => 'datetime',
        'parcel_updated_date' => 'datetime',
        'shipped_out_date' => 'date:Y-m-d',
        'date_added' => 'datetime',
        'total_qty' => 'integer',
        'price_final' => 'decimal:2',
        'price_initial' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'price_upsell' => 'decimal:2',
        'total_cog' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GencysDailySalesOrderItem::class, 'order_id');
    }
}
