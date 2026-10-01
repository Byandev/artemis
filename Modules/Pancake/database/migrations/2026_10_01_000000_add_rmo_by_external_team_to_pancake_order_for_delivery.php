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
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->boolean('rmo_by_external_team')->default(false)->after('order_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pancake_order_for_delivery', function (Blueprint $table) {
            $table->dropColumn('rmo_by_external_team');
        });
    }
};
