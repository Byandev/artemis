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
            // `id` holds the external Botcake sequence id directly — not auto-incremented.
            $table->unsignedBigInteger('id')->primary();
            $table->unsignedBigInteger('page_id');
            $table->string('name');
            $table->timestamps();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->unique(['page_id', 'id']);
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
