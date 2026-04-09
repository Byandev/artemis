<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CsrDailyRecord extends Model
{
    protected $fillable = [
        'workspace_id',
        'csr_id',
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
    ];
}
