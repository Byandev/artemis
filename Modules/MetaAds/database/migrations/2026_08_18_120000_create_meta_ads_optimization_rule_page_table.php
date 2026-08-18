<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Scopes a rule to specific Facebook pages. Empty means every page — the
     * rule applies to all targets in its ad accounts, which is the behaviour
     * rules had before this table existed.
     *
     * Ad sets carry meta_page_id directly; campaigns reach a page through their
     * ad sets. A Pancake page's primary key IS the FB page id, so page_id joins
     * straight to `pages`.
     */
    public function up(): void
    {
        Schema::create('meta_ads_optimization_rule_page', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('meta_ads_optimization_rule_id');
            $table->unsignedBigInteger('page_id');

            // Explicit short names — the auto-generated ones exceed MySQL's
            // 64-character identifier limit.
            $table->unique(['meta_ads_optimization_rule_id', 'page_id'], 'maorp_rule_page_unique');
            $table->index('page_id', 'maorp_page_id_index');

            $table->foreign('meta_ads_optimization_rule_id', 'maorp_rule_id_foreign')
                ->references('id')
                ->on('meta_ads_optimization_rules')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meta_ads_optimization_rule_page');
    }
};
