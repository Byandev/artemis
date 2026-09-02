<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks the one entry per day the Go Tyme Balance grid writes when a cell is
     * typed: a correction for the difference between what the ledger says the
     * day closed at and the figure that was entered. Flagged rather than
     * guessed at by description, so re-typing a cell rewrites its own
     * correction instead of stacking a second one.
     */
    public function up(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->boolean('is_balance_adjustment')->default(false)->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('finance_transactions', function (Blueprint $table) {
            $table->dropColumn('is_balance_adjustment');
        });
    }
};
