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
        Schema::create('rider_delivery_summary', function (Blueprint $table) {
            $table->id();
            $table->string('rider_name')->unique();
            $table->string('rider_phone')->nullable();
            $table->integer('delivery_success')->default(0);
            $table->integer('delivery_fail')->default(0);
            $table->decimal('rts_rate', 8, 4)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rider_delivery_summary');
    }
};
