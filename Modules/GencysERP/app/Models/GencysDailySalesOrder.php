<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GencysDailySalesOrder extends Model
{
    protected $table = 'gencys_daily_sales_orders';

    protected $fillable = [
        'workspace_id',
        'order_no',
        'order_date',
        'csr',
        'verifier_name',
        'upsell_by',
        'contact',
        'order_details',
        'total_qty',
        'page',
        'platform',
        'tracking_number',
        'parcel_status',
        'order_status',
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
