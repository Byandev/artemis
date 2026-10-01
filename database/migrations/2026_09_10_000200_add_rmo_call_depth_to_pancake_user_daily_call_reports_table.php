<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How far the RMO calls got, on a table that may already have it.
     *
     * All three columns are in the create migration, so a database built from
     * scratch has them and this does nothing. It is here for the environments
     * whose table was created before they were added to that file — the columns
     * are guarded one at a time rather than as a set, because such a table can
     * be missing any of them.
     *
     * `after` names the column the create migration puts each one behind, and
     * a missing predecessor is added earlier in the same statement, so the run
     * order holds whichever of the three are actually missing.
     *
     * No down: dropping a column the create migration also defines would leave
     * the table short on a rollback that never dropped it, and the columns
     * carry figures the analytics page reads.
     */
    private const COLUMNS = [
        // Reached at all — a call the other end joined.
        'total_rmo_connected_called' => 'total_rmo_call_time',
        // The subset that lasted past the connected threshold: a conversation
        // rather than a hello and a hang-up.
        'total_rmo_real_called' => 'total_rmo_connected_called',
        // The single longest RMO call — a max, so it survives being summed over
        // a range by taking the max of the maxes.
        'longest_rmo_call_time' => 'total_rmo_real_called',
    ];

    public function up(): void
    {
        Schema::table('pancake_user_daily_call_reports', function (Blueprint $table) {
            foreach (self::COLUMNS as $column => $after) {
                if (! Schema::hasColumn('pancake_user_daily_call_reports', $column)) {
                    $table->unsignedInteger($column)->default(0)->after($after);
                }
            }
        });
    }

    public function down(): void {}
};
