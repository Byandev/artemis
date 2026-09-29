<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the funds are to be released, and — for online banking and e-wallets
     * — the account to send them to. Requests saved before this have no method
     * until their next edit.
     */
    public function up(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            $table->string('payment_method')->nullable()->after('liquidation_deadline');
            $table->string('bank_name')->nullable()->after('payment_method');
            $table->string('account_name')->nullable()->after('bank_name');
            $table->string('account_number')->nullable()->after('account_name');
        });
    }

    public function down(): void
    {
        Schema::table('finance_fund_requests', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'bank_name', 'account_name', 'account_number']);
        });
    }
};
