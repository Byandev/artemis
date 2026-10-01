<?php

namespace App\Jobs;

use App\Models\PancakeUserRmoDailyReport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * A day of per-CSR RMO figures, one row per shop they worked.
 */
class SyncCsrRmoDailyRecord implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    /**
     * @param  string  $date  Y-m-d
     * @param  int|null  $workspaceId  Limit the rebuild to one workspace; null covers every one.
     */
    public function __construct(public string $date, public ?int $workspaceId = null) {}

    public function handle(): void
    {
        $rows = DB::query()
            ->fromSub($this->deliveryWork(), 'base')
            ->leftJoinSub($this->callTotals(), 'calls', function ($join) {
                $join->on('calls.workspace_id', '=', 'base.workspace_id')
                    ->on('calls.pancake_user_id', '=', 'base.pancake_user_id')
                    ->on('calls.shop_id', '=', 'base.shop_id');
            })
            ->selectRaw('
                base.workspace_id,
                base.pancake_user_id,
                base.shop_id,
                base.total_called,
                base.total_confirmed,
                COALESCE(calls.total_calls, 0)    AS total_rmo_call_attempts,
                COALESCE(calls.total_duration, 0) AS total_call_time
            ')
            ->get();

        foreach ($rows as $row) {
            PancakeUserRmoDailyReport::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'shop_id' => $row->shop_id,
                    'date' => $this->date,
                ],
                [
                    'total_called' => (int) $row->total_called,
                    'total_call_time' => (int) $row->total_call_time,
                    'total_rmo_call_attempts' => (int) $row->total_rmo_call_attempts,
                    'total_confirmed' => (int) $row->total_confirmed,
                ]
            );
        }
    }

    /**
     * What each CSR was given that day, per shop — and the rows to write.
     *
     * A delivery counts twice over: once for whoever it was assigned to, once
     * for whoever confirmed it. Unioning the two roles gives both figures and
     * the (workspace, CSR, shop) key set in one pass — including a CSR who only
     * confirmed in a shop, or was only assigned in it.
     */
    private function deliveryWork(): Builder
    {
        $work = $this->deliveriesFor('assignee_id', 'assigned')
            ->unionAll($this->deliveriesFor('conferrer_id', 'confirmed'));

        return DB::query()
            ->fromSub($work, 'w')
            ->groupBy('workspace_id', 'pancake_user_id', 'shop_id')
            ->selectRaw("
                workspace_id,
                pancake_user_id,
                shop_id,
                SUM(CASE WHEN role = 'assigned' AND status != 'PENDING' THEN 1 ELSE 0 END) AS total_called,
                SUM(CASE WHEN role = 'confirmed' THEN 1 ELSE 0 END) AS total_confirmed
            ");
    }

    /**
     * The day's calls, counted and summed against the shop they belong to.
     *
     * A call carries the order it was about — stamped at ingest — and an order
     * sits in one shop, so a call lands in exactly one row without any
     * de-duplicating. A call with no order was not about a delivery at all.
     */
    private function callTotals(): Builder
    {
        return DB::table('call_logs as cl')
            ->when($this->workspaceId, fn ($q, $id) => $q->where('cl.workspace_id', $id))
            ->where('cl.call_date', $this->date)
            ->whereNotNull('cl.order_id')
            ->join('pancake_orders as po', 'po.id', '=', 'cl.order_id')
            ->groupBy('cl.workspace_id', 'cl.user_id', 'po.shop_id')
            ->selectRaw('
                cl.workspace_id,
                cl.user_id AS pancake_user_id,
                po.shop_id,
                COUNT(*) AS total_calls,
                COALESCE(SUM(cl.duration), 0) AS total_duration
            ');
    }

    /** The day's deliveries as one role's rows — assigned to, or confirmed by. */
    private function deliveriesFor(string $csrColumn, string $role): Builder
    {
        return $this->deliveries()
            ->whereNotNull($csrColumn)
            ->selectRaw("workspace_id, {$csrColumn} AS pancake_user_id, shop_id, status, '{$role}' AS role");
    }

    /**
     * The day's deliveries, narrowed to one workspace when the run is scoped.
     *
     * Narrowed at the source rather than at the end: these are derived tables
     * MySQL materialises whole, which is what makes a scoped rebuild cheap.
     */
    private function deliveries(): Builder
    {
        return DB::table('pancake_order_for_delivery')
            ->when($this->workspaceId, fn ($q, $id) => $q->where('workspace_id', $id))
            ->where('delivery_date', $this->date);
    }
}
