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

        // total_called: pancake_order_for_delivery rows where status != 'PENDING'
        //   (matches the SyncCsrDailyRecord definition).
        // total_rmo_call_attempts: count of matching call_logs entries — same
        //   matching predicate as before, but counts every call instead of
        //   collapsing to 0/1 per delivery row.
        $rows = DB::table('pancake_order_for_delivery AS ofd')
            ->whereNotNull('ofd.assignee_id')
            ->where('ofd.delivery_date', $date)
            ->groupBy('ofd.workspace_id', 'ofd.assignee_id')
            ->selectRaw("
                ofd.workspace_id,
                ofd.assignee_id AS pancake_user_id,
                SUM(CASE WHEN ofd.status != 'PENDING' THEN 1 ELSE 0 END) AS total_called,
                COALESCE(SUM(
                    (SELECT COUNT(*)
                     FROM call_logs cl
                     WHERE cl.workspace_id = ofd.workspace_id
                       AND cl.user_id = ofd.assignee_id
                       AND cl.call_date = ofd.delivery_date
                       AND cl.phone_number IN (ofd.rider_phone, ofd.customer_phone))
                ), 0) AS total_rmo_call_attempts,
                COALESCE(SUM(ofd.customer_call_duration), 0) + COALESCE(SUM(ofd.rider_call_duration), 0) AS total_call_time
            ")
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
