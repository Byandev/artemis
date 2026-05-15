<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('csr_analytics', function (Blueprint $table) {
            $table->id();
            $table->string('csr');
            $table->integer('orders')->default(0);
            $table->decimal('sales', 15, 2)->default(0);
            $table->decimal('delivered', 15, 2)->default(0);
            $table->decimal('returning', 15, 2)->default(0);
            $table->decimal('rts_rate', 5, 2)->default(0);
            $table->integer('rmo_confirmed')->default(0);
            $table->integer('rmo_assigned')->default(0);
            $table->integer('rmo_called')->default(0);
            $table->string('total_call_time')->nullable(); // Using string to handle "46:18:09"
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csr_analytics');
    }
};
