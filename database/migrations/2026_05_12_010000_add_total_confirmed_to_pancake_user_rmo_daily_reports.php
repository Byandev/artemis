<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('pancake_user_rmo_daily_reports', 'total_confirmed')) {
                $table->unsignedInteger('total_confirmed')->default(0)->after('total_rmo_call_attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('pancake_user_rmo_daily_reports', 'total_confirmed')) {
                $table->dropColumn('total_confirmed');
            }
        });
    }
};
