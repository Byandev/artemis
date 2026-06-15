<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            // Floors the absolute change applied by a percentage adjustment
            // (e.g. "increase by 10%, but always add at least ₱100"). Null = no
            // floor. The companion to max_adjustment_amount.
            $table->decimal('min_adjustment_amount', 20, 4)->nullable()->after('max_adjustment_amount');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->dropColumn('min_adjustment_amount');
        });
    }
};
