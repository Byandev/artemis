<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Persist the Pancake order source (signed: -1 Facebook, -7 Webcake, page id for
     * page orders) plus its label, so saved orders carry where they came from.
     */
    public function up(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->integer('order_source')->nullable()->after('page_id');
            $table->string('order_source_name')->nullable()->after('order_source');
        });
    }

    public function down(): void
    {
        Schema::table('pancake_orders', function (Blueprint $table) {
            $table->dropColumn(['order_source', 'order_source_name']);
        });
    }
};
