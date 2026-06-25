<?php

namespace Modules\GencysERP\Models;

use App\Models\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GencysUnitCode extends Model
{
    protected $table = 'gencys_unit_codes';

    protected $fillable = [
        'workspace_id',
        'row_id',
        'sku',
        'unit_code',
        'total_amount',
    ];

    protected $casts = [
        'row_id' => 'integer',
        'total_amount' => 'decimal:2',
    ];

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(GencysUnitCodeInventoryItem::class, 'gencys_unit_code_id');
    }
}
