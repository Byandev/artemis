<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            // Caps the absolute change applied by a percentage adjustment
            // (e.g. "increase by 20%, but never add more than ₱500"). Null = no cap.
            $table->decimal('max_adjustment_amount', 20, 4)->nullable()->after('adjustment_value');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_optimization_rules', function (Blueprint $table) {
            $table->dropColumn('max_adjustment_amount');
        });
    }
};
