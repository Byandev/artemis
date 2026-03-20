<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->index(
                ['confirmed_by', 'confirmed_at'],
                'pancake_orders_confirmed_by_confirmed_at_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropIndex('pancake_orders_confirmed_by_confirmed_at_idx');
        });
    }
};
