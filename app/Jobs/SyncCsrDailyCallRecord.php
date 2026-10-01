<?php

namespace App\Jobs;

use App\Models\PancakeUserDailyCallReport;
use App\Support\CallLogPersona;
use App\Support\RmoDailyStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

/**
 * A day of a CSR's calls and the deliveries behind them, one row per shop.
 */
class SyncCsrDailyCallRecord implements ShouldQueue
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
        $key = fn ($row) => $row->workspace_id.'|'.$row->pancake_user_id.'|'.$row->shop_id;

        $calls = $this->callTotals()->keyBy($key);
        $deliveries = $this->deliveryTotals()->keyBy($key);

        // A CSR turns up in either set — given deliveries they never rang about,
        // or ringing about one that was somebody else's — so a row is written
        // for whatever the two add up to between them.
        foreach ($calls->keys()->merge($deliveries->keys())->unique() as $id) {
            $call = $calls->get($id);
            $delivery = $deliveries->get($id);
            $row = $call ?? $delivery;

            PancakeUserDailyCallReport::updateOrCreate(
                [
                    'workspace_id' => $row->workspace_id,
                    'pancake_user_id' => $row->pancake_user_id,
                    'shop_id' => $row->shop_id,
                    'date' => $this->date,
                ],
                [
                    'total_called' => (int) ($call?->total_called ?? 0),
                    'total_call_time' => (int) ($call?->total_call_time ?? 0),

                    'total_rmo_called' => (int) ($call?->total_rmo_called ?? 0),
                    'total_rmo_call_time' => (int) ($call?->total_rmo_call_time ?? 0),
                    'total_rmo_orders' => (int) ($call?->total_rmo_orders ?? 0),
                    'total_rmo_connected_called' => (int) ($call?->total_rmo_connected_called ?? 0),
                    'total_rmo_real_called' => (int) ($call?->total_rmo_real_called ?? 0),
                    'longest_rmo_call_time' => (int) ($call?->longest_rmo_call_time ?? 0),
                    'total_rmo_customer_called' => (int) ($call?->total_rmo_customer_called ?? 0),
                    'total_rmo_customer_call_time' => (int) ($call?->total_rmo_customer_call_time ?? 0),
                    'total_rmo_rider_called' => (int) ($call?->total_rmo_rider_called ?? 0),
                    'total_rmo_rider_call_time' => (int) ($call?->total_rmo_rider_call_time ?? 0),

                    'total_verification_called' => (int) ($call?->total_verification_called ?? 0),
                    'total_verification_call_time' => (int) ($call?->total_verification_call_time ?? 0),
                    'total_verification_real_called' => (int) ($call?->total_verification_real_called ?? 0),
                    'total_verified_orders' => (int) ($call?->total_verified_orders ?? 0),

                    'total_rmo_assigned_count' => (int) ($delivery?->total_rmo_assigned_count ?? 0),
                    'total_rmo_confirmed_count' => (int) ($delivery?->total_rmo_confirmed_count ?? 0),
                ]
            );
        }
    }

    /**
     * What the CSR was handed that day, per shop.
     *
     * Assigned and confirmed are two columns on one delivery, hence the union.
     * Every assigned delivery counts, PENDING or not — the work given, not the
     * work got through.
     */
    private function deliveryTotals()
    {
        $work = $this->deliveriesAs('assignee_id', 'assigned')
            ->unionAll($this->deliveriesAs('conferrer_id', 'confirmed'));

        return DB::query()
            ->fromSub($work, 'w')
            ->groupBy('workspace_id', 'pancake_user_id', 'shop_id')
            ->selectRaw("
                workspace_id,
                pancake_user_id,
                shop_id,
                SUM(CASE WHEN role = 'assigned' THEN 1 ELSE 0 END) AS total_rmo_assigned_count,
                SUM(CASE WHEN role = 'confirmed' THEN 1 ELSE 0 END) AS total_rmo_confirmed_count
            ")
            ->get();
    }

    /**
     * The day's calls, counted and timed per CSR and shop.
     *
     * A call is RMO work when it carries a delivery, and order verification
     * when it does not. The order beside it is what places it in a shop, so a
     * call matched to neither is not counted here at all — there is no row to
     * put it in.
     *
     * Both halves are counted twice over, and deliberately: once by call and
     * once by the thing the calls were about. RMO rings the same parcel more
     * than once by design — customer, then rider, then customer again — so
     * `total_rmo_called` is the ringing and `total_rmo_orders` the deliveries
     * it got through. NULL is not counted by COUNT(DISTINCT), so that column
     * needs no CASE: a verification call has no delivery id to count.
     *
     * The verification half splits the same way:
     * `total_verification_called` is the calls placed, `total_verified_orders`
     * the distinct orders behind them. An order rung three times is three and
     * one. Read against the orders that needed verifying, only the second is a
     * coverage figure — the first passes 100% on repeat calls alone. The
     * distinct is taken within the row, so an order chased across two days is
     * one on each and two in a range that sums them.
     */
    private function callTotals()
    {
        return DB::table('call_logs as cl')
            ->when($this->workspaceId, fn ($q, $id) => $q->where('cl.workspace_id', $id))
            ->where('cl.call_date', $this->date)
            // The order is only here to name the shop; whether the call was RMO
            // work is the delivery id's business, below.
            ->whereNotNull('cl.order_id')
            ->join('pancake_orders as po', 'po.id', '=', 'cl.order_id')
            ->groupBy('cl.workspace_id', 'cl.user_id', 'po.shop_id')
            ->selectRaw('
                cl.workspace_id,
                cl.user_id AS pancake_user_id,
                po.shop_id,

                COUNT(*) AS total_called,
                SUM(cl.duration) AS total_call_time,

                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL THEN 1 ELSE 0 END) AS total_rmo_called,
                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL THEN cl.duration ELSE 0 END) AS total_rmo_call_time,
                COUNT(DISTINCT cl.order_for_delivery_id) AS total_rmo_orders,

                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.duration > 0 THEN 1 ELSE 0 END) AS total_rmo_connected_called,
                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.duration >= '.RmoDailyStats::CONNECTED_CALL_MIN_SECONDS.' THEN 1 ELSE 0 END) AS total_rmo_real_called,
                COALESCE(MAX(CASE WHEN cl.order_for_delivery_id IS NOT NULL THEN cl.duration END), 0) AS longest_rmo_call_time,

                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.persona = ? THEN 1 ELSE 0 END) AS total_rmo_customer_called,
                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.persona = ? THEN cl.duration ELSE 0 END) AS total_rmo_customer_call_time,
                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.persona = ? THEN 1 ELSE 0 END) AS total_rmo_rider_called,
                SUM(CASE WHEN cl.order_for_delivery_id IS NOT NULL AND cl.persona = ? THEN cl.duration ELSE 0 END) AS total_rmo_rider_call_time,

                SUM(CASE WHEN cl.order_for_delivery_id IS NULL THEN 1 ELSE 0 END) AS total_verification_called,
                SUM(CASE WHEN cl.order_for_delivery_id IS NULL THEN cl.duration ELSE 0 END) AS total_verification_call_time,
                SUM(CASE WHEN cl.order_for_delivery_id IS NULL AND cl.duration >= 3 THEN 1 ELSE 0 END) AS total_verification_real_called,
                COUNT(DISTINCT CASE WHEN cl.order_for_delivery_id IS NULL THEN cl.order_id END) AS total_verified_orders
            ', [
                CallLogPersona::CUSTOMER,
                CallLogPersona::CUSTOMER,
                CallLogPersona::RIDER,
                CallLogPersona::RIDER,
            ])
            ->get();
    }

    /** The day's deliveries as one role's rows — assigned to, or confirmed by. */
    private function deliveriesAs(string $csrColumn, string $role): Builder
    {
        return DB::table('pancake_order_for_delivery')
            ->when($this->workspaceId, fn ($q, $id) => $q->where('workspace_id', $id))
            ->where('delivery_date', $this->date)
            ->whereNotNull($csrColumn)
            ->selectRaw("workspace_id, {$csrColumn} AS pancake_user_id, shop_id, '{$role}' AS role");
    }
}
