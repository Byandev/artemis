<?php

namespace App\Jobs;

use App\Support\CallLogPersona;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-runs the persona match over one workspace's calls on one day.
 *
 * A day at a time because both rules are already scoped to one workspace and one
 * date, because it is the shape of call_logs_ws_date_persona_idx, and because a
 * backfill reaching back months is otherwise one long lock over the whole table.
 * Split this way a failed day retries on its own without redoing the rest, and
 * the days spread across workers.
 *
 * Three rules, applied in this order — the order App\Support\CallLogPersona
 * applies them at sync time, so a backfilled row ends up looking like a synced
 * one:
 *
 *   customer      — the number is customer_phone on a delivery loaded that day.
 *   rider         — the number is rider_phone on a delivery loaded that day.
 *   verification  — the number is the shipping-address phone on an order the
 *                   workspace took in, or confirmed, that same day.
 *
 * Each statement only touches rows still carrying no persona, so the earlier
 * rules win: a delivery match outranks a verification one (a call to a customer
 * whose order was confirmed and dispatched the same day is about the delivery in
 * front of it), and customer outranks rider for a number that is both.
 *
 * Re-running a day is safe. Only rows with no persona are selected, so a second
 * pass over a finished day matches nothing and writes nothing.
 */
class BackfillCallLogPersonasForDay implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public int $tries = 3;

    /**
     * @param  string  $date  Y-m-d
     * @param  string  $rule  all, delivery, or verification
     * @param  bool  $dryRun  Stamp the day, count it, and roll it back. Only ever
     *                        set when the command runs the job itself — a queued
     *                        dry run would have nowhere to report to.
     */
    public function __construct(
        public int $workspaceId,
        public string $date,
        public string $rule = 'all',
        public bool $dryRun = false,
    ) {}

    /**
     * The three statements share a transaction so a day is stamped all at once
     * or not at all — and so a dry run can get its numbers from the real
     * updates, sequenced exactly as a live run would sequence them, and then
     * throw them away. Counting instead would overstate the total: a call the
     * delivery rule claims would be counted a second time by the verification
     * rule, which in a live run never sees it.
     *
     * @return array{customer: int, rider: int, verification: int}
     */
    public function handle(): array
    {
        $counts = ['customer' => 0, 'rider' => 0, 'verification' => 0];

        DB::beginTransaction();

        try {
            if ($this->rule !== 'verification') {
                $counts['customer'] = $this->stampDelivery('customer_phone', CallLogPersona::CUSTOMER);
                $counts['rider'] = $this->stampDelivery('rider_phone', CallLogPersona::RIDER);
            }

            if ($this->rule !== 'delivery') {
                $counts['verification'] = $this->stampVerification();
            }

            $this->dryRun ? DB::rollBack() : DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        if (! $this->dryRun) {
            // The command that dispatched this is long gone by the time it runs,
            // so the log is the only place the numbers show up for a queued
            // backfill. Written for every day, including the ones that stamped
            // nothing — otherwise there is no telling a day that matched nothing
            // from one that never ran.
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
     * Calls matching a delivery loaded that day, on the given phone column.
     *
     * Matched on the number as stored, which is what the sync does too:
     * pancake_order_for_delivery.customer_phone is populated from the same
     * shipping address the call is placed to, so the two spellings already
     * agree. Only the verification rule, which reaches further, has to
     * normalize.
     *
     * The deliveries go through a derived table rather than straight into the
     * join so ROW_NUMBER can pick one: the same number can appear on two
     * deliveries loaded the same day, and a bare UPDATE ... JOIN would take
     * whichever MySQL happened to reach first, differently on each run. Lowest
     * delivery id is arbitrary too — nothing in the data says which of the two
     * the call was about — but it is at least the same answer every time.
     */
    private function stampDelivery(string $column, string $persona): int
    {
        return DB::affectingStatement(<<<SQL
            UPDATE call_logs cl
            JOIN (
                SELECT pod.{$column} AS phone,
                       pod.order_id AS order_id,
                       pod.id AS delivery_id,
                       ROW_NUMBER() OVER (PARTITION BY pod.{$column} ORDER BY pod.id) AS rn
                  FROM pancake_order_for_delivery pod
                 WHERE pod.workspace_id = ?
                   AND pod.delivery_date = ?
                   AND pod.{$column} IS NOT NULL
                   AND pod.{$column} <> ''
            ) d ON d.phone = cl.phone_number AND d.rn = 1
               SET cl.persona = '{$persona}',
                   cl.order_id = d.order_id,
                   cl.order_for_delivery_id = d.delivery_id,
                   cl.updated_at = ?
             WHERE cl.workspace_id = ?
               AND cl.call_date = ?
               AND cl.persona IS NULL
        SQL, [$this->workspaceId, $this->date, now(), $this->workspaceId, $this->date]);
    }

    /**
     * Calls matching an order the workspace took in, or confirmed, that day.
     *
     * Both stamps, because CallLogPersona::resolveVerification reads both: an
     * order is rung either side of its confirmation, and one never confirmed at
     * all has only the day it came in.
     *
     * Normalized on both sides, because this rule reaches past the delivery the
     * number was copied onto and back to the address Pancake was given —
     * 09171234567, +639171234567 and 9171234567 all turn up there for the same
     * subscriber. The expression is CallLogPersona::normalize written as SQL,
     * the same one LinkVerificationCallLogsAction matches on, and the length
     * guard is its null: anything under ten digits would otherwise be padded
     * into a key that could collide with a real number.
     *
     * Each stamp is bounded by a half-open range rather than wrapped in DATE(),
     * which would hide the column from its index, and the two are unioned
     * rather than OR-ed so each half keeps to one —
     * idx_orders_workspace_confirmed_status and idx_orders_workspace_inserted.
     * An order both taken in and confirmed that day turns up on both halves,
     * with the same order id on each.
     *
     * Ties — the same number on two of that day's orders — go to the earliest
     * stamp that put one of them in the day, matching
     * CallLogPersona::resolveVerification.
     *
     * order_for_delivery_id is written back to null rather than left alone: a
     * verification call is by definition one no delivery accounted for.
     */
    private function stampVerification(): int
    {
        $day = Carbon::parse($this->date)->startOfDay();
        $persona = CallLogPersona::VERIFICATION;

        $key = "CONCAT('0', RIGHT(REGEXP_REPLACE(%s, '[^0-9]', ''), 10))";
        $orderKey = sprintf($key, 'sa.phone_number');
        $callKey = sprintf($key, 'cl.phone_number');

        $confirmed = $this->ordersStampedOnSql('confirmed_at', $orderKey);
        $inserted = $this->ordersStampedOnSql('inserted_at', $orderKey);

        return DB::affectingStatement(<<<SQL
            UPDATE call_logs cl
            JOIN (
                SELECT d.order_id,
                       d.phone_key,
                       ROW_NUMBER() OVER (
                           PARTITION BY d.phone_key
                           ORDER BY d.matched_at, d.order_id
                       ) AS rn
                  FROM (
                      {$confirmed}
                      UNION ALL
                      {$inserted}
                  ) d
            ) v ON v.phone_key = {$callKey} AND v.rn = 1
               SET cl.persona = '{$persona}',
                   cl.order_id = v.order_id,
                   cl.order_for_delivery_id = NULL,
                   cl.updated_at = ?
             WHERE cl.workspace_id = ?
               AND cl.call_date = ?
               AND cl.persona IS NULL
               AND CHAR_LENGTH(REGEXP_REPLACE(cl.phone_number, '[^0-9]', '')) >= 10
        SQL, [
            $this->workspaceId, $day, $day->copy()->addDay(),
            $this->workspaceId, $day, $day->copy()->addDay(),
            now(), $this->workspaceId, $this->date,
        ]);
    }

    /**
     * One half of the verification candidates: the day's orders on one stamp.
     *
     * Three bindings, in the order the statement above supplies them —
     * workspace, the day, and the day after.
     */
    private function ordersStampedOnSql(string $column, string $orderKey): string
    {
        return <<<SQL
            SELECT o.id AS order_id,
                   {$orderKey} AS phone_key,
                   o.{$column} AS matched_at
              FROM pancake_orders o
              JOIN shipping_addresses sa ON sa.order_id = o.id
             WHERE o.workspace_id = ?
               AND o.{$column} >= ?
               AND o.{$column} < ?
               AND sa.phone_number IS NOT NULL
               AND CHAR_LENGTH(REGEXP_REPLACE(sa.phone_number, '[^0-9]', '')) >= 10
        SQL;
    }
}
