<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN transaction_type ENUM('funds','profit_share','expenses','transfer','remittance','loan','loan_payment','refund','voided','courier_damaged_settlement','capex') NULL");

        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN sub_category ENUM('ad_spent','cogs','subscription','shipping_fee','delivery_fee','operation_expense','salary','transfer_fee','seminar_fee','rent','capex_payment','others') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE finance_transactions SET transaction_type = NULL WHERE transaction_type = 'capex'");
        DB::statement("UPDATE finance_transactions SET sub_category = NULL WHERE sub_category = 'capex_payment'");

        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN transaction_type ENUM('funds','profit_share','expenses','transfer','remittance','loan','loan_payment','refund','voided','courier_damaged_settlement') NULL");

        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN sub_category ENUM('ad_spent','cogs','subscription','shipping_fee','delivery_fee','operation_expense','salary','transfer_fee','seminar_fee','rent','others') NULL");
    }
};
