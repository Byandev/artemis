<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            $table->unsignedTinyInteger('status')->default(1)->after('total_amount');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_purchased_orders', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
