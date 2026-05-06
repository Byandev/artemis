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
        Schema::create('botcake_sequences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('sequence_id');
            $table->string('name');
            $table->timestamps();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['page_id', 'sequence_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('botcake_sequences');
    }
};
