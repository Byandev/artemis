<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RemittanceItem extends Model
{
    protected $table = 'finance_remittance_items';

    protected $fillable = [
        'remittance_id',
        'waybill_number',
        'order_number',
        'shipping_date',
        'sender_city',
        'destination_city',
        'package_billing_weight',
        'item_value',
        'value_added_fee',
        'receivable_freight',
        'total_shipping_cost',
        'cod',
        'cod_commission_rate',
        'cod_commission',
        'cod_commission_vat_fee',
        'shipping_customer_code',
        'signing_time',
    ];

    protected $casts = [
        'shipping_date' => 'datetime',
        'signing_time' => 'datetime',
        'package_billing_weight' => 'decimal:2',
        'item_value' => 'decimal:2',
        'value_added_fee' => 'decimal:2',
        'receivable_freight' => 'decimal:2',
        'total_shipping_cost' => 'decimal:2',
        'cod' => 'decimal:2',
        'cod_commission_rate' => 'decimal:2',
        'cod_commission' => 'decimal:2',
        'cod_commission_vat_fee' => 'decimal:2',
    ];

    public function remittance(): BelongsTo
    {
        return $this->belongsTo(Remittance::class, 'remittance_id');
    }
}
