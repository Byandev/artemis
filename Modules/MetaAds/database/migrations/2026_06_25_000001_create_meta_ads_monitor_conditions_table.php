<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('meta_ads_monitor_conditions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_ads_monitor_status_rule_id');

            // Any insights column or computed metric (roas, cpa, ctr, cpm, …).
            $table->string('metric');
            $table->enum('operator', ['>', '<', '>=', '<=', '=']);
            $table->decimal('value', 20, 4);
            // Window relative to the entity's first spend day.
            $table->enum('window', ['first_3_days', 'first_7_days', 'lifetime']);

            $table->timestamps();

            // Explicit short name — the auto-generated one exceeds MySQL's
            // 64-character identifier limit.
            $table->foreign('meta_ads_monitor_status_rule_id', 'maomc_rule_id_foreign')
                ->references('id')
                ->on('meta_ads_monitor_status_rules')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_monitor_conditions');
    }
};
