<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ad budget a team is given for the target's day, next to the sales amount
 * it should return on that spend.
 *
 * A team can now be on a target for its budget alone, so `sales_target` stops
 * being required — "no sales amount set" is a real state, distinct from zero.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales_target_teams', function (Blueprint $table) {
            $table->decimal('ad_budget', 15, 2)->nullable()->after('sales_target');
        });

        Schema::table('sales_target_teams', function (Blueprint $table) {
            $table->decimal('sales_target', 15, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // The column goes back to NOT NULL, so rows that used the new "budget
        // only" state need an amount before the change can apply.
        DB::table('sales_target_teams')->whereNull('sales_target')->update(['sales_target' => 0]);

        Schema::table('sales_target_teams', function (Blueprint $table) {
            $table->decimal('sales_target', 15, 2)->nullable(false)->change();
            $table->dropColumn('ad_budget');
        });
    }
};
