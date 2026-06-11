<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_budget_snapshots', function (Blueprint $table) {
            // Polymorphic so we can snapshot ad_set OR campaign (CBO) budgets
            // without splitting the table later.
            $table->string('entity_type', 16);
            $table->unsignedBigInteger('entity_id');
            $table->date('date');

            $table->decimal('daily_budget', 20, 2)->nullable();
            $table->decimal('lifetime_budget', 20, 2)->nullable();
            $table->string('bid_strategy')->nullable();
            $table->string('status', 32)->nullable();
            $table->string('effective_status', 32)->nullable();

            $table->timestamps();

            $table->primary(['entity_type', 'entity_id', 'date']);
            $table->index(['entity_id', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_budget_snapshots');
    }
};
