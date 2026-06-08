<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            // 'automatic' applies changes on the next run; 'approval' only
            // proposes them for a human to confirm. Defaults to the safer option.
            $table->enum('execution_mode', ['automatic', 'approval'])
                ->default('approval')
                ->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->dropColumn('execution_mode');
        });
    }
};
