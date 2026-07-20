<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scheduled_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sim_id')->constrained()->cascadeOnDelete();
            $table->string('to_number');
            $table->text('message');
            $table->timestamp('scheduled_at');
            $table->string('status')->default('pending');
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('sms_message_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scheduled_messages');
    }
};
