<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The page's RTS over the 30 days ending on this one — the rate the ROAS
     * tracker's margin estimate discounts the day's revenue by.
     *
     * Stored rather than joined at read time. Working it out live meant a
     * 30-day self-join materialised once per grain on every page load, which
     * measured at ~285ms a query on a 31-day view of 50 pages and ~830ms on a
     * 90-day one — it grows with days x pages, and the tracker asks four times.
     *
     * Nothing is lost by storing it: the window is blended from the
     * returning_amount / delivered_amount already on these rows, and the builder
     * recomputes a day's window whenever it rebuilds that day. A day older than
     * the builder's trailing window has frozen inputs either way, so the stored
     * figure and a live join would agree.
     *
     * Null when the window holds no delivery activity at all — the tracker
     * falls back to its default rate rather than reading that as a 0% page.
     */
    public function up(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->decimal('rts_rate_30d', 8, 2)->nullable()->after('rts_rate');
        });

        // Backfill the history the builder will never revisit. One pass over the
        // table, each row blended against the 30 days behind it.
        DB::statement(<<<'SQL'
            UPDATE page_daily_records AS target
              JOIN (
                SELECT r.id,
                       SUM(h.returning_amount) /
                       NULLIF(SUM(h.returning_amount) + SUM(h.delivered_amount), 0) * 100 AS rts
                  FROM page_daily_records r
                  JOIN page_daily_records h
                    ON h.workspace_id = r.workspace_id
                   AND h.page_type = r.page_type
                   AND h.page_id = r.page_id
                   AND h.date BETWEEN DATE_SUB(r.date, INTERVAL 29 DAY) AND r.date
                 GROUP BY r.id
              ) AS w ON w.id = target.id
               SET target.rts_rate_30d = w.rts
        SQL);
    }

    public function down(): void
    {
        Schema::table('page_daily_records', function (Blueprint $table) {
            $table->dropColumn('rts_rate_30d');
        });
    }
};
