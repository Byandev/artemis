<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Accounts created from the Sales & Marketing "Go Tyme Balance" page are
     * per-user wallets rather than company accounts. They live in the same
     * table — same balances, same transactions — but are flagged so the
     * Finance → Accounts listing can leave them out.
     */
    public function up(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->boolean('is_user_wallet')->default(false)->after('is_active')->index();
        });
    }

    public function down(): void
    {
        Schema::table('finance_accounts', function (Blueprint $table) {
            $table->dropIndex(['is_user_wallet']);
            $table->dropColumn('is_user_wallet');
        });
    }
};
