<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN transaction_type ENUM('funds','profit_share','expenses','transfer','remittance','loan','loan_payment','refund','voided','courier_damaged_settlement') NULL");
    }

    public function down(): void
    {
        DB::statement("UPDATE finance_transactions SET transaction_type = NULL WHERE transaction_type IN ('loan','loan_payment','refund','voided','courier_damaged_settlement')");
        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN transaction_type ENUM('funds','profit_share','expenses','transfer','remittance') NULL");
    }
};
