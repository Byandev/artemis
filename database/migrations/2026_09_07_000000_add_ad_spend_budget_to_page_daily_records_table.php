<?php

use App\Models\Page;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            // The page's planned daily ad spend, snapshotted next to the actual
            // one so the tracker can say "under" or "over" without joining the
            // budget table back in at read time — and so a budget raised next
            // week doesn't rewrite what last week was measured against.
            //
            // Null, not zero: a page with no budget on record is not a page
            // budgeted at nothing, and can be neither over nor under it.
            $table->decimal('ad_spend_budget', 15, 2)->nullable()->after('ad_spent');
        });

        // Backfill the history the builder will never revisit — it only rebuilds
        // a trailing few days — so the column isn't blank for every past date.
        //
        // Same rule the builder uses going forward: the page's latest budget
        // recorded on or before the row's date, since a budget carries forward
        // until it is changed. Only Artemis Pancake pages, whose page_id is what
        // page_daily_budget_records points at.
        DB::table('page_daily_records')
            ->where('page_type', (new Page)->getMorphClass())
            ->update([
                'ad_spend_budget' => DB::raw(<<<'SQL'
                    (SELECT b.budget
                       FROM page_daily_budget_records b
                      WHERE b.workspace_id = page_daily_records.workspace_id
                        AND b.page_id = page_daily_records.page_id
                        AND b.date <= page_daily_records.date
                   ORDER BY b.date DESC
                      LIMIT 1)
                SQL),
            ]);
    }

    public function down(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->dropColumn('ad_spend_budget');
        });
    }
};
