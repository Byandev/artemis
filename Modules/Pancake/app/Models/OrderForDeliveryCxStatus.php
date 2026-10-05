<?php

namespace Modules\Pancake\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A workspace-defined cx status an RMO order can be tagged with. */
class OrderForDeliveryCxStatus extends Model
{
    protected $table = 'pancake_order_for_delivery_cx_statuses';

    protected $fillable = ['workspace_id', 'name'];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
