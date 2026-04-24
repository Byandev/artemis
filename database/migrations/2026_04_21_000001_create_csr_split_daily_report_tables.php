<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('pancake_user_daily_reports');

        Schema::create('pancake_user_pos_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->uuid('pancake_user_id');
            $table->foreign('pancake_user_id')->references('id')->on('pancake_users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->unsignedInteger('returning')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->decimal('rts_rate', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'pancake_user_id', 'date'], 'pupos_workspace_user_date_unique');
            $table->index(['workspace_id', 'date'], 'pupos_workspace_date_index');
        });

        Schema::create('pancake_user_erp_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->uuid('pancake_user_id');
            $table->foreign('pancake_user_id')->references('id')->on('pancake_users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_orders')->default(0);
            $table->decimal('total_sales', 15, 2)->default(0);
            $table->unsignedInteger('returning')->default(0);
            $table->unsignedInteger('delivered')->default(0);
            $table->decimal('rts_rate', 8, 2)->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'pancake_user_id', 'date'], 'puerp_workspace_user_date_unique');
            $table->index(['workspace_id', 'date'], 'puerp_workspace_date_index');
        });

        Schema::create('pancake_user_rmo_daily_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->uuid('pancake_user_id');
            $table->foreign('pancake_user_id')->references('id')->on('pancake_users')->cascadeOnDelete();
            $table->date('date');
            $table->unsignedInteger('total_called')->default(0);
            $table->unsignedInteger('total_call_time')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'pancake_user_id', 'date'], 'purmo_workspace_user_date_unique');
            $table->index(['workspace_id', 'date'], 'purmo_workspace_date_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pancake_user_rmo_daily_reports');
        Schema::dropIfExists('pancake_user_erp_daily_reports');
        Schema::dropIfExists('pancake_user_pos_daily_reports');
    }
};
