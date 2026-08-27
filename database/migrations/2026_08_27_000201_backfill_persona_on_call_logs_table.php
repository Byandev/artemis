<?php

use App\Support\CallLogPersona;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Stamp persona/order_id onto the call logs already in the table.
     *
     * Without this the breakdown would report every historical call as
     * "order verification" — the bucket for calls that matched no delivery —
     * until each one happened to be re-synced. Matching is the same rule new
     * calls go through, so old and new rows are classified alike.
     *
     * Walked one workspace-day at a time: that is the unit persona is resolved
     * against, and it keeps the working set small on a table that grows without
     * bound.
     */
    public function up(): void
    {
        DB::table('call_logs')
            ->select('workspace_id', 'call_date')
            ->whereNull('persona')
            ->groupBy('workspace_id', 'call_date')
            ->orderBy('workspace_id')
            ->orderBy('call_date')
            ->cursor()
            ->each(function ($day) {
                $date = (string) $day->call_date;

                $phones = DB::table('call_logs')
                    ->where('workspace_id', $day->workspace_id)
                    ->whereDate('call_date', $date)
                    ->whereNull('persona')
                    ->pluck('phone_number')
                    ->all();

                $resolved = CallLogPersona::resolve($day->workspace_id, $date, $phones);

                foreach ($resolved as $phone => $match) {
                    DB::table('call_logs')
                        ->where('workspace_id', $day->workspace_id)
                        ->whereDate('call_date', $date)
                        ->where('phone_number', $phone)
                        ->whereNull('persona')
                        ->update([
                            'persona' => $match['persona'],
                            'order_id' => $match['order_id'],
                        ]);
                }
            });
    }

    public function down(): void
    {
        // Nothing to undo: the columns themselves are dropped by the migration
        // that added them, and there is no earlier value to restore.
    }
};
