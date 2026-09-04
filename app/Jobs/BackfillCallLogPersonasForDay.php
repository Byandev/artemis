<?php

namespace App\Jobs;

use App\Support\CallLogPersona;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Stamps one workspace's calls on one day with who was on the other end.
 *
 * Three UPDATEs, in this order — each one only touches rows the ones before it
 * left alone, because every statement carries `cl.persona IS NULL`:
 *
 *   rider         the number is the rider_phone on a delivery loaded that day
 *   customer      the number is the customer_phone on one
 *   verification  the number is on the shipping address of an order confirmed
 *                 that day, which is what an order-verification call looks like
 *
 * The order is what decides a number that is both a rider's and a customer's
 * that day: rider claims it first, and the customer statement then passes over
 * the row.
 *
 * A day at a time so a backfill reaching back months is not one long
 * transaction, and so a failed day retries on its own. Re-running a finished day
 * is free — `persona IS NULL` leaves nothing to match.
 */
class BackfillCallLogPersonasForDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @param  string  $rule  all, delivery, or verification
     * @param  bool  $dryRun  Count what would be stamped and roll it back. Only
     *                        ever set when the command runs the job itself — a
     *                        queued dry run would have nowhere to report to.
     */
    public function __construct(
        public int $workspaceId,
        public string $date,
        public string $rule = 'all',
        public bool $dryRun = false,
    ) {}

    /**
     * @return array<string, int> rider, customer, verification
     */
    public function handle(): array
    {
        if ($this->dryRun) {
            // The statements are sequential — each one's reach depends on what
            // the one before it claimed — so counting them separately would mean
            // reimplementing that. Running them for real and throwing the work
            // away gives the true numbers and costs one transaction.
            try {
                DB::transaction(function () {
                    throw new RollbackDryRun($this->run());
                });
            } catch (RollbackDryRun $rollback) {
                return $rollback->counts;
            }
        }

        $counts = $this->run();

        if (array_sum($counts) > 0) {
            // The command that queued this is long gone by the time it runs, so
            // the log is where a queued backfill's numbers show up.
            Log::info('Backfilled call log personas', [
                'workspace_id' => $this->workspaceId,
                'date' => $this->date,
                'rule' => $this->rule,
                ...$counts,
            ]);
        }

        return $counts;
    }

    /**
     * @return array<string, int>
     */
    private function run(): array
    {
        $counts = ['rider' => 0, 'customer' => 0, 'verification' => 0];

        if ($this->rule !== 'verification') {
            $counts['rider'] = $this->matchDelivery('rider_phone', CallLogPersona::RIDER);
            $counts['customer'] = $this->matchDelivery('customer_phone', CallLogPersona::CUSTOMER);
        }

        if ($this->rule !== 'delivery') {
            $counts['verification'] = $this->matchVerification();
        }

        return $counts;
    }

    /**
     * Calls whose number is on a delivery loaded for that day.
     *
     * pod.workspace_id is joined on as well as filtered on the calls: without it
     * a number reused in another workspace matches that workspace's delivery and
     * stamps its order id onto this workspace's call.
     */
    private function matchDelivery(string $column, string $persona): int
    {
        return $this->unstamped()
            ->join('pancake_order_for_delivery as pod', function (JoinClause $join) use ($column) {
                $join->on("pod.{$column}", '=', 'cl.phone_number')
                    ->on('pod.workspace_id', '=', 'cl.workspace_id')
                    ->whereRaw('DATE(pod.delivery_date) = DATE(cl.call_date)');
            })
            ->update([
                'cl.persona' => $persona,
                'cl.order_id' => DB::raw('pod.order_id'),
                'cl.order_for_delivery_id' => DB::raw('pod.id'),
            ]);
    }

    /**
     * Calls whose number is on an order this workspace confirmed that day.
     *
     * No order_for_delivery_id: an order confirmed today may not be loaded for
     * delivery for days yet, so there is no delivery row to point at.
     */
    private function matchVerification(): int
    {
        return $this->unstamped()
            ->join('shipping_addresses as sa', 'sa.phone_number', '=', 'cl.phone_number')
            ->join('pancake_orders as po', function (JoinClause $join) {
                $join->on('po.id', '=', 'sa.order_id')
                    ->on('po.workspace_id', '=', 'cl.workspace_id')
                    ->whereRaw('DATE(po.confirmed_at) = DATE(cl.call_date)');
            })
            ->update([
                'cl.persona' => CallLogPersona::VERIFICATION,
                'cl.order_id' => DB::raw('po.id'),
            ]);
    }

    /**
     * The day's calls that nothing has claimed yet.
     *
     * Every statement starts here, which is what makes their order the rule:
     * whatever the one before it stamped is no longer null, so it is passed over.
     */
    private function unstamped(): Builder
    {
        return DB::table('call_logs as cl')
            ->where('cl.workspace_id', $this->workspaceId)
            ->whereDate('cl.call_date', $this->date)
            ->whereNull('cl.persona');
    }
}
