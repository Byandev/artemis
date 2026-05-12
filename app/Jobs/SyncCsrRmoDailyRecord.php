<?php

namespace App\Jobs;

use App\Models\PancakeUserRmoDailyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncCsrRmoDailyRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /**
     * @param  string  $date  Y-m-d
     */
    public function __construct(public string $date) {}

    public function handle(): void
    {
        $date = $this->date;

        // total_called: pancake_order_for_delivery rows assigned to the user where status != 'PENDING'.
        $base = DB::table('pancake_order_for_delivery')
            ->whereNotNull('assignee_id')
            ->where('delivery_date', $date)
            ->groupBy('workspace_id', 'assignee_id')
            ->selectRaw("
                workspace_id,
                assignee_id AS pancake_user_id,
                SUM(CASE WHEN status != 'PENDING' THEN 1 ELSE 0 END) AS total_called
            ");

        // total_rmo_call_attempts / totaaddl_call_time: per (workspace, user), count and sum call_logs
        // whose phone_number matches any customer_phone or rider_phone from that user's deliveries
        // on the date. Each call_log is counted once even if multiple deliveries share the same phone.
        $callsAgg = DB::table('call_logs as cl')
            ->where('cl.call_date', $date)
            ->whereExists(function ($q) use ($date) {
                $q->select(DB::raw(1))
                    ->from('pancake_order_for_delivery as pod')
                    ->whereColumn('pod.workspace_id', 'cl.workspace_id')
                    ->whereColumn('pod.assignee_id', 'cl.user_id')
                    ->where('pod.delivery_date', $date)
                    ->whereRaw('cl.phone_number IN (pod.customer_phone, pod.rider_phone)');
            })
            ->groupBy('cl.workspace_id', 'cl.user_id')
            ->selectRaw('
                cl.workspace_id,
                cl.user_id AS pancake_user_id,
                COUNT(*) AS total_calls,
                COALESCE(SUM(cl.duration), 0) AS total_duration
            ');

        $rows = DB::query()
            ->fromSub($base, 'base')
            ->leftJoinSub($callsAgg, 'calls', function ($join) {
                $join->on('calls.workspace_id', '=', 'base.workspace_id')
                    ->on('calls.pancake_user_id', '=', 'base.pancake_user_id');
            })
            ->selectRaw('
                base.workspace_id,
                base.pancake_user_id,
                base.total_called,
                COALESCE(calls.total_calls, 0)    AS total_rmo_call_attempts,
                COALESCE(calls.total_duration, 0) AS total_call_time
            ')
            ->get();

        foreach ($rows as $row) {
            PancakeUserRmoDailyReport::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'date' => $date,
                ],
                [
                    'total_called' => (int) $row->total_called,
                    'total_call_time' => (int) $row->total_call_time,
                    'total_rmo_call_attempts' => (int) $row->total_rmo_call_attempts,
                ]
            );
        }
    }
}
