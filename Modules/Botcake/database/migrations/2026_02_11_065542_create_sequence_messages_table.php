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
        Schema::create('botcake_sequence_messages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedBigInteger('sequence_id');
            $table->unsignedBigInteger('message_id')->nullable();
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('is_clicked')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();

            $table->unique(['sequence_id', 'message_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('botcake_sequence_messages');
    }
};
