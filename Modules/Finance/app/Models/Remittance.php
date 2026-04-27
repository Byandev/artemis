<?php

namespace Modules\Finance\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Remittance extends Model
{
    protected $table = 'finance_remittances';

    protected $fillable = [
        'workspace_id',
        'courier',
        'soa_number',
        'vip_code',
        'client_name',
        'settlement_method',
        'service_management',
        'affiliated_branch',
        'cod_settlement_category',
        'cod_flag',
        'opening_bank',
        'bank_account',
        'payee',
        'billing_date_from',
        'billing_date_to',
        'gross_cod',
        'cod_accumulated_amount',
        'cod_amount_cwt',
        'cod_fee',
        'cod_commission_rate',
        'cod_fee_vat',
        'cod_cwt',
        'total_cod_payable',
        'total_freight_receivable',
        'shipping_fee',
        'shipping_fee_cwt',
        'return_shipping',
        'return_cwt',
        'super_value_added_fee',
        'return_freight_policy_adjustment',
        'cod_amount_adjustment',
        'cod_commission_adjustment',
        'cod_vat_adjustment',
        'cod_cwt_adjustment',
        'total_shipping_fee_adjustment',
        'total_shipping_fee_cwt_adjustment',
        'rts_shipping_fee_adjustment',
        'rts_total_shipping_fee_cwt_adjustment',
        'other_adjustment',
        'discount_amount',
        'total_adjustment',
        'net_amount',
        'previous_period_bill_deduction',
        'amount_after_deduction',
        'current_period_bill_deduction',
        'already_deducted_freight_bill',
        'shipping_fee_difference',
        'courier_creation_time',
        'confirm_status',
        'confirm_time',
        'billing_status',
        'email_sending_status',
        'email_sending_time',
        'status',
        'transaction_id',
        'notes',
    ];

    protected $casts = [
        'billing_date_from' => 'date',
        'billing_date_to' => 'date',
        'gross_cod' => 'decimal:2',
        'cod_accumulated_amount' => 'decimal:2',
        'cod_amount_cwt' => 'decimal:2',
        'cod_fee' => 'decimal:2',
        'cod_commission_rate' => 'decimal:2',
        'cod_fee_vat' => 'decimal:2',
        'cod_cwt' => 'decimal:2',
        'total_cod_payable' => 'decimal:2',
        'total_freight_receivable' => 'decimal:2',
        'shipping_fee' => 'decimal:2',
        'shipping_fee_cwt' => 'decimal:2',
        'return_shipping' => 'decimal:2',
        'return_cwt' => 'decimal:2',
        'super_value_added_fee' => 'decimal:2',
        'return_freight_policy_adjustment' => 'decimal:2',
        'cod_amount_adjustment' => 'decimal:2',
        'cod_commission_adjustment' => 'decimal:2',
        'cod_vat_adjustment' => 'decimal:2',
        'cod_cwt_adjustment' => 'decimal:2',
        'total_shipping_fee_adjustment' => 'decimal:2',
        'total_shipping_fee_cwt_adjustment' => 'decimal:2',
        'rts_shipping_fee_adjustment' => 'decimal:2',
        'rts_total_shipping_fee_cwt_adjustment' => 'decimal:2',
        'other_adjustment' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_adjustment' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'previous_period_bill_deduction' => 'decimal:2',
        'amount_after_deduction' => 'decimal:2',
        'current_period_bill_deduction' => 'decimal:2',
        'shipping_fee_difference' => 'decimal:2',
        'courier_creation_time' => 'datetime',
        'confirm_time' => 'datetime',
        'email_sending_time' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(RemittanceItem::class, 'remittance_id');
    }

    public function getIsReconciledAttribute(): bool
    {
        return $this->transaction_id !== null;
    }
}
