<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Daily advertiser performance, unified across sources.
 *
 * `source` distinguishes the pipeline ('gencys' | 'artemis') and the advertiser
 * is polymorphic — a Gencys Intern or an Artemis User — via the custom
 * advertiser_model / advertiser_id columns. `sales` is the purchase value, so
 * ROAS = sales / ad_spent.
 */
class AdvertiserPerformanceDailyRecord extends Model
{
    public const SOURCE_GENCYS = 'gencys';

    public const SOURCE_ARTEMIS = 'artemis';

    /**
     * Metric columns carried verbatim from the Gencys n8n payload (they share
     * names with this table's columns).
     */
    public const GENCYS_METRIC_COLUMNS = [
        'orders', 'sales', 'roas', 'ad_spent', 'rts_rate',
        'delivered', 'delivered_amount', 'returned', 'returned_amount',
        'date_to_month_sales', 'date_to_month_orders', 'date_to_month_ad_spent', 'date_to_month_roas',
        'date_to_month_sales_order_rts_rate', 'date_to_month_sales_order_delivered',
        'date_to_month_sales_order_returned', 'date_to_month_sales_order_for_return',
        'date_to_month_parcel_status_rts_rate', 'date_to_month_parcel_status_delivered',
        'date_to_month_parcel_status_returned', 'date_to_month_parcel_status_for_return',
        'date_to_month_shipped_out_rts_rate', 'date_to_month_shipped_out_delivered',
        'date_to_month_shipped_out_returned', 'date_to_month_shipped_out_for_return',
    ];

    protected $guarded = [];

    protected $casts = [
        'date' => 'date',
        'orders' => 'integer',
        'sales' => 'decimal:2',
        'ad_spent' => 'decimal:2',
        'roas' => 'decimal:2',
        'delivered' => 'integer',
        'delivered_amount' => 'decimal:2',
        'returned' => 'integer',
        'returned_amount' => 'decimal:2',
        'returning' => 'integer',
        'rts_rate' => 'decimal:2',
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

    /**
     * Upsert one Gencys n8n payload row (source=gencys, advertiser=Intern). Only
     * the recognised metric columns are copied; `date` keys the row.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function upsertGencysDaily(int $workspaceId, int $internId, ?string $internName, array $payload): void
    {
        if (empty($payload['date'])) {
            return;
        }

        static::updateOrCreate(
            [
                'workspace_id' => $workspaceId,
                'source' => self::SOURCE_GENCYS,
                'advertiser_model' => 'intern',
                'advertiser_id' => $internId,
                'date' => $payload['date'],
            ],
            [
                ...array_intersect_key($payload, array_flip(self::GENCYS_METRIC_COLUMNS)),
                'advertiser_name' => $internName,
            ],
        );
    }

    /** The Gencys Intern or Artemis User this row belongs to. */
    public function advertiser(): MorphTo
    {
        return $this->morphTo('advertiser', 'advertiser_model', 'advertiser_id');
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
