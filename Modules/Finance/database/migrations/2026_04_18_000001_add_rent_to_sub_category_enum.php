<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN sub_category ENUM('ad_spent','cogs','subscription','shipping_fee','operation_expense','salary','transfer_fee','seminar_fee','rent','others') NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE finance_transactions MODIFY COLUMN sub_category ENUM('ad_spent','cogs','subscription','shipping_fee','operation_expense','salary','transfer_fee','seminar_fee','others') NULL");
    }
};
