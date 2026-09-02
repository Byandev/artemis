<?php

use Illuminate\Database\Migrations\Migration;
use Modules\Finance\Models\Account;

return new class extends Migration
{
    /**
     * Wallet running balances were accumulated from zero rather than from the
     * account's opening balance, so any wallet opened with a figure was out by
     * exactly that amount from its first entry onwards. Re-flow them.
     *
     * Only wallets: company accounts carry a running balance entered by hand on
     * the Finance transaction form, which this must not overwrite.
     */
    public function up(): void
    {
        Account::query()
            ->wallets()
            ->whereHas('transactions')
            ->each(fn (Account $account) => $account->resequenceRunningBalances());
    }

    public function down(): void
    {
        // Deliberately empty: the corrected figures are the right ones.
    }
};
