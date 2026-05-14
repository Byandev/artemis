<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PancakeUserRmoDailyReport extends Model
{
    protected $table = 'pancake_user_rmo_daily_reports';

    protected $fillable = [
        'workspace_id',
        'pancake_user_id',
        'date',
        'total_called',
        'total_call_time',
        'total_rmo_call_attempts',
        'total_confirmed',
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
