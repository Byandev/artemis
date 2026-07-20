<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Daily "Extreme Self-Care" (ESC) records. One row per employee per day.
     * The `employee_id` from the source schema maps to `users.id` here since
     * employees live in the `users` table.
     */
    public function up(): void
    {
        Schema::create('daily_esc_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('record_date');
            $table->text('learning_text')->nullable();
            $table->text('movement_text')->nullable();
            $table->string('movement_image_url')->nullable();
            $table->boolean('meditation_completed')->default(false);
            $table->boolean('is_complete')->default(false);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'record_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_esc_records');
    }
};
