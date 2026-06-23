<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('workspace_id');
            $table->string('status', 16)->default('running');
            // The phase currently executing (null once the run finishes ok).
            $table->string('current_step', 32)->nullable();
            // Ordered plan of phases for this run: [{key, label}, ...].
            $table->json('steps')->nullable();
            $table->unsignedInteger('total_rules')->default(0);
            $table->unsignedInteger('total_accounts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index('started_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_runs');
    }
};
