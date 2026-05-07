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
        Schema::create('botcake_sequence_message_daily_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_message_id')->constrained('botcake_sequence_messages')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->unique(['sequence_message_id', 'date'], 'botcake_seq_msg_daily_unique');
            $table->index('date', 'botcake_seq_msg_daily_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('botcake_sequence_message_daily_stats');
    }
};
