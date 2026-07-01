<?php

namespace Modules\Inventory\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryItemDiscrepancy extends Model
{
    protected $table = 'inventory_item_discrepancies';

    protected $fillable = [
        'workspace_id',
        'inventory_item_id',
        'date',
        'counted_qty',
        'discrepancy',
    ];

    protected $casts = [
        'date' => 'date',
        'counted_qty' => 'integer',
        'discrepancy' => 'integer',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }
}
