<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('csr_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->uuid('pancake_user_id');
            $table->date('date');
            $table->time('shift_start');
            $table->time('shift_end');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'pancake_user_id', 'date']);
            $table->index(['pancake_user_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('csr_schedules');
    }
};
