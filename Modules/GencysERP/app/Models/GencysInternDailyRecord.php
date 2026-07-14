<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GencysInternDailyRecord extends Model
{
    protected $table = 'gencys_intern_daily_records';

    protected $fillable = [
        'workspace_id',
        'gencys_intern_id',
        'record_date',
        'orders',
        'sales',
        'roas',
        'ad_spent',
        'rts_rate',
        'delivered',
        'delivered_amount',
        'returned',
        'returned_amount',
        'date_to_month_sales',
        'date_to_month_orders',
        'date_to_month_ad_spent',
        'date_to_month_roas',
        'date_to_month_sales_order_rts_rate',
        'date_to_month_sales_order_delivered',
        'date_to_month_sales_order_returned',
        'date_to_month_sales_order_for_return',
        'date_to_month_parcel_status_rts_rate',
        'date_to_month_parcel_status_delivered',
        'date_to_month_parcel_status_returned',
        'date_to_month_parcel_status_for_return',
        'date_to_month_shipped_out_rts_rate',
        'date_to_month_shipped_out_delivered',
        'date_to_month_shipped_out_returned',
        'date_to_month_shipped_out_for_return',
    ];

    protected $casts = [
        'record_date' => 'date:Y-m-d',
        'orders' => 'integer',
        'sales' => 'decimal:2',
        'roas' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'rts_rate' => 'decimal:2',
        'delivered' => 'integer',
        'delivered_amount' => 'decimal:2',
        'returned' => 'integer',
        'returned_amount' => 'decimal:2',
        'date_to_month_sales' => 'decimal:2',
        'date_to_month_orders' => 'integer',
        'date_to_month_ad_spent' => 'decimal:2',
        'date_to_month_roas' => 'decimal:2',
        'date_to_month_sales_order_rts_rate' => 'decimal:2',
        'date_to_month_sales_order_delivered' => 'integer',
        'date_to_month_sales_order_returned' => 'integer',
        'date_to_month_sales_order_for_return' => 'integer',
        'date_to_month_parcel_status_rts_rate' => 'decimal:2',
        'date_to_month_parcel_status_delivered' => 'integer',
        'date_to_month_parcel_status_returned' => 'integer',
        'date_to_month_parcel_status_for_return' => 'integer',
        'date_to_month_shipped_out_rts_rate' => 'decimal:2',
        'date_to_month_shipped_out_delivered' => 'integer',
        'date_to_month_shipped_out_returned' => 'integer',
        'date_to_month_shipped_out_for_return' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function intern(): BelongsTo
    {
        return $this->belongsTo(Intern::class, 'gencys_intern_id');
    }
}
