<?php

namespace App\Jobs;

use App\Models\PancakeUserPosDailyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class SyncCsrDailyRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /**
     * @param  string  $date  Y-m-d
     * @param  int|null  $workspaceId  Limit the rebuild to one workspace; null covers every one.
     */
    public function __construct(public string $date, public string $type = 'POS', public ?int $workspaceId = null) {}

    public function handle(): void
    {
        $date = $this->date;
        $type = $this->type;
        $start = $date.' 00:00:00';
        $end = $date.' 23:59:59';

        $rows = DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
            ->when($this->workspaceId, fn ($q, $id) => $q->where('po.workspace_id', $id))
            ->where(function ($q) use ($start, $end) {
                $q->where(function ($q2) use ($start, $end) {
                    $q2->where('po.status', 3)
                        ->whereBetween('po.delivered_at', [$start, $end]);
                })
                    ->orWhere(function ($q2) use ($start, $end) {
                        $q2->whereIn('po.status', [4, 5])
                            ->whereBetween('po.returning_at', [$start, $end]);
                    })
                    ->orWhereBetween('po.confirmed_at', [$start, $end]);
            })
            ->groupBy('po.workspace_id', 'pu.id')
            ->selectRaw('
                po.workspace_id as workspace_id,
                pu.id as pancake_user_id,

                SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_orders,
                SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) as total_sales,

                SUM(CASE WHEN po.status IN (4,5) AND po.returning_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as returning_count,
                SUM(CASE WHEN po.status IN (4,5) AND po.returning_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) as returning,

                SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) as delivered
            ', [
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
                $start, $end,
            ])
            ->get();

        foreach ($rows as $row) {
            PancakeUserPosDailyReport::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'date' => $date,
                ],
                [
                    'total_orders' => (int) $row->total_orders,
                    'total_sales' => (float) $row->total_sales,
                    'returning' => (float) $row->returning,
                    'delivered' => (float) $row->delivered,
                    // The parcels behind those two amounts: CSR analytics ranks
                    // on the rate but qualifies on the count.
                    'returning_count' => (int) $row->returning_count,
                    'delivered_count' => (int) $row->delivered_count,
                ]
            );
        }
    }
}
