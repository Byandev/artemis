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
        Schema::create('pancake_phone_number_reports', function (Blueprint $table) {
            $table->id();
            $table->string('phone_number')->unique();
            $table->integer('order_fail')->default(0);
            $table->integer('order_success')->default(0);
            $table->integer('warning')->default(0);
            $table->timestamps();

            $table->index('phone_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pancake_phone_number_reports');
    }
};
