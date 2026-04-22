<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('call_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->uuid('user_id');
            $table->string('phone_number');
            $table->string('type');
            $table->unsignedInteger('duration')->default(0);
            $table->date('call_date');
            $table->time('call_time');
            $table->timestamps();

            $table->foreign('workspace_id')->references('id')->on('workspaces')->cascadeOnDelete();

            $table->index(['workspace_id', 'user_id']);
            $table->index('phone_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('call_logs');
    }
};
