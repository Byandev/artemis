<?php

namespace Modules\Inventory\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryTransaction extends Model
{
    use HasFactory;

    protected $table = 'inventory_transactions';

    protected $dates = ['deleted_at'];

    protected $fillable = [
        'workspace_id',
        'inventory_item_id',
        'date',
        'ref_no',
        'po_qty_in',
        'po_qty_out',
        'rts_goods_out',
        'rts_goods_in',
        'rts_bad',
        'lost',
        'remaining_qty',
        'is_audited',
        'inventory_remaining_stock',
    ];

    protected $casts = [
        'is_audited' => 'boolean',
    ];

    public function inventoryItem(): BelongsTo
    {
        return $this->belongsTo(InventoryItem::class);
    }

    /**
     * Net effect of this transaction on stock: goods in minus goods out, bad and
     * lost. This is the single source of truth for the movement sign convention —
     * both the ERP sync and recalculateActualStock() roll the running balance
     * forward with it.
     */
    public function netMovement(): int
    {
        return self::netMovementFromRow($this->getAttributes());
    }

    /**
     * Same movement formula computed from a raw row array (the ERP sync payload),
     * so the running balance can be derived before the model is persisted.
     *
     * @param  array<string, mixed>  $row
     */
    public static function netMovementFromRow(array $row): int
    {
        return (int) ($row['po_qty_in'] ?? 0)
            + (int) ($row['rts_goods_in'] ?? 0)
            - (int) ($row['po_qty_out'] ?? 0)
            - (int) ($row['rts_goods_out'] ?? 0)
            - (int) ($row['rts_bad'] ?? 0)
            - (int) ($row['lost'] ?? 0);
    }
}
