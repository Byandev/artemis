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
        Schema::create('pancake_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('pancake_product_id');
            $table->integer('retail_price');
            $table->integer('retail_price_after_discount');
            $table->string('display_id');
            $table->timestamps();

            $table->foreign('pancake_product_id')->references('id')
                ->on('pancake_products')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_variants');
    }
};
