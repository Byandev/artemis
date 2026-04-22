<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkspaceCustomerFact extends Model
{
    protected $table = 'workspace_customer_facts';

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'first_confirmed_at',
        'last_confirmed_at',
        'first_confirmed_page_id',
        'total_confirmed_orders',
        'total_delivered_orders',
        'total_confirmed_spend',
        'total_delivered_spend',
        'customer_created_at',
    ];

    protected $casts = [
        'first_confirmed_at' => 'datetime',
        'last_confirmed_at' => 'datetime',
        'customer_created_at' => 'datetime',
        'total_confirmed_spend' => 'decimal:2',
        'total_delivered_spend' => 'decimal:2',
        'customer_id' => 'string',
    ];
}
