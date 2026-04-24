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
        'confirmed_amount',
        'shipped_amount',
        'delivered_amount',
        'entered_returning_amount',
        'returned_amount',
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
