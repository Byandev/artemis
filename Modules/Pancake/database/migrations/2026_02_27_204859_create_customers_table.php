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
        Schema::create('pancake_customers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('shop_id');
            $table->uuid('customer_id');
            $table->string('name');
            $table->string('fb_id')->unique();
            $table->unsignedInteger('returned_order_count')->default(0);
            $table->unsignedInteger('success_order_count')->default(0);
            $table->string('gender')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->unsignedInteger('purchased_amount')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_customers');
    }
};
