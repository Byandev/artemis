<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            // When two rules match the same campaign / ad set in one run, the
            // higher priority wins the change. Ties fall back to the older rule.
            $table->unsignedInteger('priority')->default(0)->after('execution_mode');
            $table->index(['workspace_id', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'priority']);
            $table->dropColumn('priority');
        });
    }
};
