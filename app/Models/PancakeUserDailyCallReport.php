<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PancakeUserDailyCallReport extends Model
{
    protected $table = 'pancake_user_daily_call_reports';

    protected $fillable = [
        'workspace_id',
        'pancake_user_id',
        'shop_id',
        'date',
        'total_called',
        'total_call_time',
        'total_rmo_called',
        'total_rmo_call_time',
        'total_rmo_customer_called',
        'total_rmo_customer_call_time',
        'total_rmo_rider_called',
        'total_rmo_rider_call_time',
        'total_rmo_assigned_count',
        'total_rmo_confirmed_count',
        'total_verification_called',
        'total_verification_call_time',
    ];

    protected $casts = [
        'date' => 'date',
        'pancake_user_id' => 'string',
    ];

    public function scopeForWorkspaceRange($query, $workspaceId, $from, $to)
    {
        return $query->where('workspace_id', $workspaceId)
            ->whereBetween('date', [$from, $to]);
    }
}
