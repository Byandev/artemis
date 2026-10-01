<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('meta_ads_budget_snapshots', function (Blueprint $table) {
            // How the row was obtained. `capture` rows were observed live by
            // metaads:capture-budgets and are ground truth; `backfill` rows were
            // reconstructed after the fact from Meta's activity log and are only
            // as good as that log. Backfill never overwrites a capture row.
            $table->string('source', 16)->default('capture')->after('effective_status');
        });
    }

    public function down(): void
    {
        Schema::table('meta_ads_budget_snapshots', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
