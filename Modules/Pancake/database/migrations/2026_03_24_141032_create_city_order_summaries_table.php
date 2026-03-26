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
        Schema::create('city_order_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('district_id');
            $table->string('province_id');
            $table->string('name');
            $table->string('province_name');
            $table->integer('delivered')->default(0);
            $table->integer('returned')->default(0);
            $table->decimal('rts_rate')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('city_order_summaries');
    }
};
