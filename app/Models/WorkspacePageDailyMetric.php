<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspacePageDailyMetric extends Model
{
    protected $table = 'workspace_page_daily_metrics';

    protected $fillable = [
        'workspace_id',
        'page_id',
        'date',
        'confirmed_count',
        'shipped_count',
        'delivered_count',
        'entered_returning_count',
        'returned_count',
        'new_customer_count',
        'confirmed_amount',
        'shipped_amount',
        'delivered_amount',
        'entered_returning_amount',
        'returned_amount',
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
        'sum_delivery_attempts_delivered',
        'count_delivery_attempts_delivered',
        'sum_delivery_attempts_returned',
        'count_delivery_attempts_returned',
    ];

    protected $casts = [
        'date' => 'date',
        'confirmed_amount' => 'decimal:2',
        'shipped_amount' => 'decimal:2',
        'delivered_amount' => 'decimal:2',
        'entered_returning_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
    ];
}
