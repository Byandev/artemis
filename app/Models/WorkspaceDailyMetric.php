<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceDailyMetric extends Model
{
    protected $table = 'workspace_daily_metrics';

    protected $fillable = [
        'workspace_id',
        'date',
        'page_id',
        'confirmed_count',
        'shipped_count',
        'first_delivery_attempt_count',
        'delivered_count',
        'delivered_clean_count',
        'returning_count',
        'entered_returning_count',
        'returned_count',
        'for_delivery_count',
        'total_sales',
        'delivered_amount',
        'returning_amount',
        'returned_amount',
        'sum_delivery_attempts_delivered',
        'sum_delivery_attempts_returned',
        'sum_customer_rts_rate_delivered',
        'count_customer_rts_rate_delivered',
        'sum_customer_rts_rate_returned',
        'count_customer_rts_rate_returned',
        'sum_days_confirmed_to_shipped',
        'count_confirmed_to_shipped',
        'sum_days_confirmed_to_first_attempt',
        'count_confirmed_to_first_attempt',
        'sum_days_confirmed_to_delivered',
        'count_confirmed_to_delivered',
        'sum_days_shipped_to_first_attempt',
        'count_shipped_to_first_attempt',
        'sum_days_shipped_to_delivered',
        'count_shipped_to_delivered',
        'sum_days_returning_to_returned',
        'count_returning_to_returned',
        'tracked_orders_count',
        'sms_sent_count',
        'chat_sent_count',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'decimal:2',
        'delivered_amount' => 'decimal:2',
        'returning_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'sum_customer_rts_rate_delivered' => 'decimal:4',
        'sum_customer_rts_rate_returned' => 'decimal:4',
    ];

    public function scopeForWorkspaceRange($query, int $workspaceId, string $from, string $to)
    {
        return $query->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$from, $to]);
    }
}
