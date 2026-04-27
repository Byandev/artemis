<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_remittances', function (Blueprint $table) {
            // Client info
            $table->string('vip_code')->nullable()->after('soa_number');
            $table->string('client_name')->nullable()->after('vip_code');
            $table->string('settlement_method')->nullable()->after('client_name');
            $table->string('service_management')->nullable()->after('settlement_method');
            $table->string('affiliated_branch')->nullable()->after('service_management');

            // COD details
            $table->string('cod_settlement_category')->nullable()->after('affiliated_branch');
            $table->string('cod_flag')->nullable()->after('cod_settlement_category');
            $table->decimal('cod_accumulated_amount', 14, 2)->default(0)->after('gross_cod');
            $table->decimal('cod_amount_cwt', 12, 2)->default(0)->after('cod_accumulated_amount');
            $table->decimal('cod_commission_rate', 6, 2)->default(0)->after('cod_fee');
            $table->decimal('cod_cwt', 12, 2)->default(0)->after('cod_fee_vat');
            $table->decimal('total_cod_payable', 12, 2)->default(0)->after('cod_cwt');

            // Bank info
            $table->string('opening_bank')->nullable()->after('cod_flag');
            $table->string('bank_account')->nullable()->after('opening_bank');
            $table->string('payee')->nullable()->after('bank_account');

            // Freight
            $table->decimal('total_freight_receivable', 12, 2)->default(0)->after('total_cod_payable');
            $table->decimal('shipping_fee_cwt', 12, 2)->default(0)->after('shipping_fee');
            $table->decimal('return_cwt', 12, 2)->default(0)->after('return_shipping');
            $table->decimal('super_value_added_fee', 12, 2)->default(0)->after('return_cwt');

            // Adjustments
            $table->decimal('return_freight_policy_adjustment', 12, 2)->default(0)->after('super_value_added_fee');
            $table->decimal('cod_amount_adjustment', 12, 2)->default(0)->after('return_freight_policy_adjustment');
            $table->decimal('cod_commission_adjustment', 12, 2)->default(0)->after('cod_amount_adjustment');
            $table->decimal('cod_vat_adjustment', 12, 2)->default(0)->after('cod_commission_adjustment');
            $table->decimal('cod_cwt_adjustment', 12, 2)->default(0)->after('cod_vat_adjustment');
            $table->decimal('total_shipping_fee_adjustment', 12, 2)->default(0)->after('cod_cwt_adjustment');
            $table->decimal('total_shipping_fee_cwt_adjustment', 12, 2)->default(0)->after('total_shipping_fee_adjustment');
            $table->decimal('rts_shipping_fee_adjustment', 12, 2)->default(0)->after('total_shipping_fee_cwt_adjustment');
            $table->decimal('rts_total_shipping_fee_cwt_adjustment', 12, 2)->default(0)->after('rts_shipping_fee_adjustment');
            $table->decimal('other_adjustment', 12, 2)->default(0)->after('rts_total_shipping_fee_cwt_adjustment');
            $table->decimal('discount_amount', 12, 2)->default(0)->after('other_adjustment');
            $table->decimal('total_adjustment', 12, 2)->default(0)->after('discount_amount');

            // Deductions
            $table->decimal('previous_period_bill_deduction', 12, 2)->default(0)->after('net_amount');
            $table->decimal('amount_after_deduction', 12, 2)->default(0)->after('previous_period_bill_deduction');
            $table->decimal('current_period_bill_deduction', 12, 2)->default(0)->after('amount_after_deduction');
            $table->string('already_deducted_freight_bill')->nullable()->after('current_period_bill_deduction');
            $table->decimal('shipping_fee_difference', 12, 2)->default(0)->after('already_deducted_freight_bill');

            // Timestamps & status from courier
            $table->dateTime('courier_creation_time')->nullable()->after('shipping_fee_difference');
            $table->string('confirm_status')->nullable()->after('courier_creation_time');
            $table->dateTime('confirm_time')->nullable()->after('confirm_status');
            $table->string('billing_status')->nullable()->after('confirm_time');
            $table->string('email_sending_status')->nullable()->after('billing_status');
            $table->dateTime('email_sending_time')->nullable()->after('email_sending_status');
        });
    }

    public function down(): void
    {
        Schema::table('finance_remittances', function (Blueprint $table) {
            $table->dropColumn([
                'vip_code', 'client_name', 'settlement_method', 'service_management', 'affiliated_branch',
                'cod_settlement_category', 'cod_flag', 'cod_accumulated_amount', 'cod_amount_cwt',
                'cod_commission_rate', 'cod_cwt', 'total_cod_payable',
                'opening_bank', 'bank_account', 'payee',
                'total_freight_receivable', 'shipping_fee_cwt', 'return_cwt', 'super_value_added_fee',
                'return_freight_policy_adjustment', 'cod_amount_adjustment', 'cod_commission_adjustment',
                'cod_vat_adjustment', 'cod_cwt_adjustment', 'total_shipping_fee_adjustment',
                'total_shipping_fee_cwt_adjustment', 'rts_shipping_fee_adjustment',
                'rts_total_shipping_fee_cwt_adjustment', 'other_adjustment', 'discount_amount', 'total_adjustment',
                'previous_period_bill_deduction', 'amount_after_deduction', 'current_period_bill_deduction',
                'already_deducted_freight_bill', 'shipping_fee_difference',
                'courier_creation_time', 'confirm_status', 'confirm_time',
                'billing_status', 'email_sending_status', 'email_sending_time',
            ]);
        });
    }
};
