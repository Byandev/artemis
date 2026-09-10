<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far the verification calls got, not just how many were placed.
     *
     * The RMO side of the table already carries its `real` cut; without the
     * same column here the two kinds of call cannot be drawn on one axis —
     * calls placed against conversations had would be counting one thing for
     * RMO and another for verification.
     *
     * Backdated rows stay 0 until the rollup is re-run for their day, which is
     * what the sync button on the analytics page is for.
     */
    public function up(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            $table->unsignedInteger('total_verification_real_called')
                ->default(0)
                ->after('total_verification_call_time');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            $table->dropColumn('total_verification_real_called');
        });
    }
};
