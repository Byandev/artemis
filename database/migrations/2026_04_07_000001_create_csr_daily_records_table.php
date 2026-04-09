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
        Schema::create('csr_daily_records', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('csr_id');
            $table->date('date');
            $table->string('type')->nullable();
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->unsignedInteger('returning')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->decimal('rts_rate', 8, 2)->default(0);
            $table->unsignedInteger('rmo_called')->default(0);
            $table->timestamps();

            $table->foreign('csr_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->index(['csr_id', 'date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('csr_daily_records');
    }
};
