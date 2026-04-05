<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id',
        'date',
        'ref_no',
        'po_qty_in',
        'po_qty_out',
        'rts_goods_out',
        'rts_goods_in',
        'rts_bad',
        'remaining_qty',
    ];
}
