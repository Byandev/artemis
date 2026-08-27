<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Pancake\Models\Order;

class CallLog extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected $casts = [
        'call_date' => 'date',
    ];

    /**
     * The Pancake order the call was about, when it could be matched to one.
     *
     * No foreign key backs this — pancake_orders is synced from a third party,
     * so the row may be gone even though the id is still on the call.
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }
}
