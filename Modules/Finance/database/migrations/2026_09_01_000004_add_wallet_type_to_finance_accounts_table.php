<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a wallet is the holder's main or backup account. Only wallets
     * carry it — company accounts created in Finance → Accounts leave it null,
     * and the field is only ever set from the Go Tyme Balance page.
     */
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->string('wallet_type', 10)->nullable()->after('is_user_wallet');
        });
    }

    public function down(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->dropColumn('wallet_type');
        });
    }
};
