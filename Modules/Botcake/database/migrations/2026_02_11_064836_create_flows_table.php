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
        Schema::create('botcake_flows', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger('flow_id');
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->boolean('is_removed')->default(false);
            $table->unsignedBigInteger('delivery')->default(0);
            $table->unsignedBigInteger('is_clicked')->default(0);
            $table->unsignedBigInteger('seen')->default(0);
            $table->unsignedBigInteger('sent')->default(0);
            $table->unsignedBigInteger('total_phone_number')->default(0);
            $table->timestamps();
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->string('name');
            $table->unique(['page_id', 'flow_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('botcake_flows');
    }
};
