<?php

namespace App\Console\Commands;

use App\Models\CsrDailyRecord;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncCsrDailyRecords extends Command
{
    protected $signature = 'sync:csr-daily-records
                            {--date= : Target date in Y-m-d format (defaults to yesterday)}
                            {--type=POS : Record type label}';

    protected $description = 'Aggregate per-CSR order metrics for a given date and upsert into csr_daily_records.';

    public function handle(): int
    {
        $date = $this->option('date')
            ? CarbonImmutable::parse($this->option('date'))->toDateString()
            : CarbonImmutable::yesterday()->toDateString();

        $type = (string) $this->option('type');

        $start = $date . ' 00:00:00';
        $end   = $date . ' 23:59:59';

        $this->info("Syncing CSR daily records for {$date}...");

        $rows = DB::table('pancake_orders as po')
            ->join('pancake_users as pu', 'pu.id', '=', 'po.confirmed_by')
            ->whereNotNull('pu.user_id')
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
            ->groupBy('po.workspace_id', 'pu.user_id')
            ->selectRaw('
                po.workspace_id as workspace_id,
                pu.user_id as csr_id,

                -- orders & sales
                SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as total_orders,
                SUM(CASE WHEN po.confirmed_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) as total_sales,

                -- returning (RTS)
                SUM(CASE WHEN po.status IN (4,5) AND po.returning_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as returning_count,
                SUM(CASE WHEN po.status IN (4,5) AND po.returning_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) as returning,

                -- delivered
                SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as delivered_count,
                SUM(CASE WHEN po.status = 3 AND po.delivered_at BETWEEN ? AND ? THEN po.final_amount ELSE 0 END) as delivered
            ', [
                // confirmed
                $start, $end,
                $start, $end,

                // returning
                $start, $end,
                $start, $end,

                // delivered
                $start, $end,
                $start, $end,
            ])
            ->get();

        // RMO called counts per CSR (user_id) from pancake_order_for_delivery,
        // joined to pancake_users via assignee_id (= pancake_users.id).
        $rmoCalledRows = DB::table('pancake_order_for_delivery as pofd')
            ->join('pancake_users as pu', 'pu.id', '=', 'pofd.assignee_id')
            ->whereNotNull('pu.user_id')
            ->where('pofd.delivery_date', $date)
            ->where('pofd.status', '!=', 'PENDING')
            ->groupBy('pofd.workspace_id', 'pu.user_id')
            ->selectRaw('pofd.workspace_id, pu.user_id as csr_id, COUNT(*) as rmo_called')
            ->get();

        $rmoCalled = [];
        foreach ($rmoCalledRows as $r) {
            $rmoCalled[$r->workspace_id][$r->csr_id] = (int) $r->rmo_called;
        }

        $count = 0;

        foreach ($rows as $row) {
            // ✅ Correct RTS (based on order count)
            $total = $row->delivered_count + $row->returning_count;

            $rtsRate = $total > 0
                ? round(($row->returning_count / $total) * 100, 2)
                : 0;

            CsrDailyRecord::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'csr_id'       => $row->csr_id,
                    'date'         => $date,
                    'type'         => $type,
                ],
                [
                    'total_orders' => (int) $row->total_orders,
                    'total_sales'  => (float) $row->total_sales,
                    'returning'    => (float) $row->returning,
                    'delivered'    => (float) $row->delivered,
                    'rts_rate'     => $rtsRate,
                    'rmo_called'   => (int) ($rmoCalled[$row->workspace_id][$row->csr_id] ?? 0),
                ]
            );

            $count++;
        }

        $this->info("Synced {$count} CSR daily record(s) for {$date}.");

        return self::SUCCESS;
    }
}
