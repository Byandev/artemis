<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PancakeUserDailyReport extends Model
{
    protected $table = 'pancake_user_daily_reports';

    protected $fillable = [
        'workspace_id',
        'pancake_user_id',
        'date',
        'type',
        'total_orders',
        'total_sales',
        'returning',
        'delivered',
        'rts_rate',
        'rmo_called',
    ];

    protected $casts = [
        'date' => 'date',
        'total_sales' => 'decimal:2',
        'rts_rate' => 'decimal:2',
        'pancake_user_id' => 'string',
    ];
}
