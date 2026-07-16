<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_ad_spend_goal_milestones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')
                ->constrained('team_ad_spend_goals')
                ->cascadeOnDelete();

            // A daily-spend threshold below the goal's target — a stepping stone
            // toward it (e.g. 300000 under a 500000/day goal).
            $table->decimal('amount', 15, 2);
            $table->string('label')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ad_spend_goal_milestones');
    }
};
