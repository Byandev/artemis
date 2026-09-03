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
        // The parcel counts behind `returning` / `delivered`, which are money.
        'returning_count',
        'delivered_count',
        'rts_rate',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'decimal:2',
        'rts_rate' => 'decimal:2',
        'pancake_user_id' => 'string',
    ];

    public function scopeForWorkspaceRange($query, $workspaceId, $from, $to)
    {
        return $query->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$from, $to]);
    }
}
