<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The two calls made about a test once its numbers are in.
     *
     * Both are nullable and start empty: a test that is still running has not
     * been decided yet, and "no decision" is a real state worth telling apart
     * from any of the choices. Neither is derived from the metrics — a person
     * makes the call — so nothing recalculates these on sync.
     */
    public function up(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            // What the intern decided to do with the creative.
            $table->enum('intern_decision', ['scale', 'split_50_50', 'killed'])
                ->nullable()
                ->after('paused_at');

            // Where the money for it stands.
            $table->enum('finance_status', ['for_collection', 'pending', 'collected'])
                ->nullable()
                ->after('intern_decision');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_testing_items', function (Blueprint $table) {
            $table->dropColumn(['intern_decision', 'finance_status']);
        });
    }
};
