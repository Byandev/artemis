<?php

namespace Modules\Pancake\Models;

use App\Models\Order;
use App\Models\Shop;
use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** An order the RTS Scanner app scanned as returned. */
class ScannedReturnedOrder extends Model
{
    protected $guarded = [];

    protected $casts = [
        'synced_to_pancake' => 'boolean',
        'scanned_at' => 'datetime',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
