<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_optimization_rule_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_ads_optimization_rule_id');

            // Any column from meta_ads_insights or a computed metric (e.g. roas, cpa, ctr, cpm)
            $table->string('metric');
            $table->enum('operator', ['>', '<', '>=', '<=', '=']);
            $table->decimal('value', 20, 4);
            $table->enum('time_window', ['today', 'last_3_days', 'last_7_days', 'last_14_days', 'last_30_days', 'lifetime']);

            $table->timestamps();

            $table->foreign('meta_ads_optimization_rule_id')
                ->references('id')
                ->on('meta_ads_optimization_rules')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_rule_conditions');
    }
};
