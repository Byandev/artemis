<?php

namespace Modules\Inventory\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A generic, source-agnostic unit code (e.g. a bundle/SKU grouping) and its
 * total amount. Items are linked by (workspace_id, unit_code) — see
 * InventoryUnitCodeItem — so the storage isn't tied to any one ERP.
 */
class InventoryUnitCode extends Model
{
    protected $table = 'inventory_unit_codes';

    protected $fillable = [
        'workspace_id',
        'unit_code',
        'sku',
        'total_amount',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /**
     * Items belonging to this unit code. Linked on the unit_code string; callers
     * scope by workspace_id (unit_code is unique per workspace) when loading.
     */
    public function items(): HasMany
    {
        return $this->hasMany(InventoryUnitCodeItem::class, 'unit_code', 'unit_code');
    }
}
