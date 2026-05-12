<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            $table->unsignedInteger('confirmed_orders')->default(0)->after('total_rmo_call_attempts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            $table->dropColumn('confirmed_orders');
        });
    }
};
