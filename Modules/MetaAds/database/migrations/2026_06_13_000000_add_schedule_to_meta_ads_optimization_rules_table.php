<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->enum('frequency', ['hourly', 'every_3_hours', 'every_6_hours', 'every_12_hours', 'daily'])
                ->default('daily')
                ->after('priority');
            // Hour of day (0–23) to run when frequency is "daily".
            $table->unsignedTinyInteger('run_at_hour')->nullable()->after('frequency');
            // Last time this rule was evaluated by the scheduler.
            $table->timestamp('last_evaluated_at')->nullable()->after('run_at_hour');
        });

        // Preserve the previous "daily at 03:00" cadence for existing rules.
        DB::table('meta_ads_optimization_rules')->update([
            'frequency' => 'daily',
            'run_at_hour' => 3,
        ]);
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->dropColumn(['frequency', 'run_at_hour', 'last_evaluated_at']);
        });
    }
};
