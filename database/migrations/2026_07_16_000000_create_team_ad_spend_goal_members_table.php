<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_ad_spend_goal_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id')
                ->constrained('team_ad_spend_goals')
                ->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // This member's slice of the team's daily target. The members'
            // targets are expected to sum to at least the goal's daily target.
            $table->decimal('daily_target', 15, 2);

            $table->timestamps();

            $table->unique(['goal_id', 'user_id'], 'tasgm_goal_user_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_ad_spend_goal_members');
    }
};
