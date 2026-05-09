<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            if (! Schema::hasColumn('pancake_user_rmo_daily_reports', 'total_rmo_call_attempts')) {
                $table->unsignedInteger('total_rmo_call_attempts')->default(0)->after('total_call_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            if (Schema::hasColumn('pancake_user_rmo_daily_reports', 'total_rmo_call_attempts')) {
                $table->dropColumn('total_rmo_call_attempts');
            }
        });
    }
};
