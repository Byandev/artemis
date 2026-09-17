<?php

namespace App\Support;

use App\Models\Workspace;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * Auto-assignment hands unassigned RMO rows out to a pool of CSRs instead of
 * leaving them to be claimed. The workspace chooses the pool; the rotation
 * itself is least-loaded — each row goes to whoever in the pool holds the
 * fewest orders for that delivery date.
 *
 * Least-loaded rather than a plain cursor because the day is full of manual
 * moves: a CSR claims three rows by hand, another has ten reassigned away, and
 * a naive rotation would keep dealing evenly into an uneven table. Counting
 * first means every pass corrects the balance it finds, and with no manual
 * edits at all it degenerates to exactly round-robin.
 *
 * A row already carrying an assignee is never touched — manual assignment wins,
 * the same rule the bulk-assign action follows.
 *
 * Two entry points drive it: SyncParcelTrackingAction assigns each row as it is
 * created, and `rmo:apply-auto-assign` sweeps up whatever that missed.
 */
class RmoAutoAssign
{
    /**
     * The CSRs a workspace may put in its pool: Pancake users tied to one of
     * its shops. Same population the RMO management page's assignee picker
     * offers, so the settings page can't offer someone the page can't.
     *
     * @return Collection<int, PancakeUser>
     */
    public static function assignableUsers(Workspace $workspace): Collection
    {
        return PancakeUser::query()
            ->whereHas('shops', fn ($query) => $query->where('shops.workspace_id', $workspace->id))
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * The configured pool, narrowed to CSRs still attached to a shop in this
     * workspace. A CSR who has since moved on simply drops out rather than
     * collecting orders nobody is working — and an id that was never valid
     * can't leak in through an old payload.
     *
     * Configured order is preserved: it is what breaks ties between two CSRs
     * holding the same number of orders, so the rotation stays deterministic.
     *
     * @return list<string>
     */
    public static function pool(Workspace $workspace): array
    {
        $configured = $workspace->loadMissing('rmoSetting')->rmoSetting?->auto_assign_user_ids ?? [];

        $configured = array_values(array_unique(array_filter(
            array_map('strval', (array) $configured),
            fn (string $id) => $id !== '',
        )));

        if ($configured === []) {
            return [];
        }

        $valid = PancakeUser::query()
            ->whereIn('id', $configured)
            ->whereHas('shops', fn ($query) => $query->where('shops.workspace_id', $workspace->id))
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        return array_values(array_intersect($configured, $valid));
    }

    /**
     * Hand out every unassigned row on $date, and report how many were taken.
     * A no-op when the workspace has auto-assignment off or its pool is empty.
     *
     * Re-running is harmless: assigned rows are skipped, so a second pass over
     * a settled date assigns nothing.
     */
    public static function apply(Workspace $workspace, string $date): int
    {
        if (! $workspace->rmoAutoAssignEnabled()) {
            return 0;
        }

        $pool = self::pool($workspace);

        if ($pool === []) {
            return 0;
        }

        $unassigned = OrderForDelivery::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $date)
            ->whereNull('assignee_id')
            ->orderBy('id')
            ->pluck('id');

        if ($unassigned->isEmpty()) {
            return 0;
        }

        $load = self::currentLoad($workspace, $date, $pool);

        // Deal first, write second: grouping the rows by who they landed on
        // keeps this to one UPDATE per pool member however many rows there are.
        $batches = [];

        foreach ($unassigned as $orderId) {
            $userId = self::leastLoaded($load, $pool);
            $load[$userId]++;
            $batches[$userId][] = $orderId;
        }

        $assigned = 0;

        foreach ($batches as $userId => $orderIds) {
            $assigned += OrderForDelivery::query()
                ->whereIn('id', $orderIds)
                // Re-checked at write time: a CSR may have claimed one of these
                // by hand between the read above and here, and their claim wins.
                ->whereNull('assignee_id')
                ->update(['assignee_id' => $userId]);
        }

        return $assigned;
    }

    /**
     * Assign one freshly-synced row, returning the CSR it went to or null when
     * it was left alone (auto-assignment off, empty pool, already assigned, or
     * claimed by someone between the read and the write).
     */
    public static function assignOne(Workspace $workspace, OrderForDelivery $order): ?string
    {
        if (! $workspace->rmoAutoAssignEnabled() || $order->assignee_id) {
            return null;
        }

        $pool = self::pool($workspace);

        if ($pool === []) {
            return null;
        }

        $date = $order->delivery_date
            ? Carbon::parse($order->delivery_date)->toDateString()
            : today()->toDateString();

        $userId = self::leastLoaded(self::currentLoad($workspace, $date, $pool), $pool);

        $updated = OrderForDelivery::query()
            ->whereKey($order->getKey())
            ->whereNull('assignee_id')
            ->update(['assignee_id' => $userId]);

        if (! $updated) {
            return null;
        }

        // The caller keeps working with this instance after we return, so don't
        // hand it back still believing it is unassigned. The value is already in
        // the database, so sync the original too — the model isn't dirty.
        $order->setAttribute('assignee_id', $userId)->syncOriginalAttribute('assignee_id');

        return $userId;
    }

    /**
     * How many of $date's orders each pool member already holds. Orders held by
     * someone outside the pool are not counted — the balance being levelled is
     * the pool's own.
     *
     * @param  list<string>  $pool
     * @return array<string, int>
     */
    private static function currentLoad(Workspace $workspace, string $date, array $pool): array
    {
        $counts = OrderForDelivery::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $date)
            ->whereIn('assignee_id', $pool)
            ->groupBy('assignee_id')
            ->selectRaw('assignee_id, COUNT(*) as orders_held')
            ->pluck('orders_held', 'assignee_id');

        $load = [];

        foreach ($pool as $userId) {
            $load[$userId] = (int) ($counts[$userId] ?? 0);
        }

        return $load;
    }

    /**
     * The pool member holding the fewest orders, ties going to whoever sits
     * earlier in the configured pool.
     *
     * @param  array<string, int>  $load
     * @param  list<string>  $pool
     */
    private static function leastLoaded(array $load, array $pool): string
    {
        $chosen = $pool[0];

        foreach ($pool as $userId) {
            if ($load[$userId] < $load[$chosen]) {
                $chosen = $userId;
            }
        }

        return $chosen;
    }
}
