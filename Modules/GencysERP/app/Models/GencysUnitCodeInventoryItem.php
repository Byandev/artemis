<?php

namespace Modules\GencysERP\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GencysUnitCodeInventoryItem extends Model
{
    protected $table = 'gencys_unit_code_inventory_items';

    protected $fillable = [
        'gencys_unit_code_id',
        'unit_code',
        'inventory_item_code',
        'quantity',
        'price',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
    ];

    public function unitCode(): BelongsTo
    {
        return $this->belongsTo(GencysUnitCode::class, 'gencys_unit_code_id');
    }
}
