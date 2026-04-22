<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PancakeUserPosDailyReport extends Model
{
    protected $table = 'pancake_user_pos_daily_reports';

    protected $fillable = [
        'workspace_id',
        'pancake_user_id',
        'date',
        'total_orders',
        'total_sales',
        'returning',
        'delivered',
        'confirmed_count',
        'delivered_count',
        'returning_count',
        'returned_count',
        'delivered_amount',
        'returning_amount',
        'returned_amount',
        'sum_delivery_attempts_delivered',
        'sum_delivery_attempts_returned',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'decimal:2',
        'delivered' => 'decimal:2',
        'returning' => 'decimal:2',
        'delivered_amount' => 'decimal:2',
        'returning_amount' => 'decimal:2',
        'returned_amount' => 'decimal:2',
        'pancake_user_id' => 'string',
    ];

    public function scopeForWorkspaceRange($query, $workspaceId, $from, $to)
    {
        return $query->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$from, $to]);
    }
}
