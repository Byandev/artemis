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
        Schema::table('orders', function (Blueprint $table) {
            $table->integer('customer_succeed_order_count')->default(0);
            $table->integer('customer_returned_order_count')->default(0);
            $table->unsignedBigInteger('assignee_id')->nullable();
            $table->unsignedBigInteger('conferrer_id')->nullable();
            $table->unsignedBigInteger('last_editor_id')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            //
        });
    }
};
