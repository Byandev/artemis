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
        Schema::create('botcake_flow_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flow_id')->constrained('botcake_flows')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('is_clicked')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->unique(['flow_id', 'date']);
            $table->index('date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('botcake_flow_daily_stats');
    }
};
