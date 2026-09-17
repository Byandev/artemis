<?php

namespace App\Support;

use App\Models\Workspace;
use Illuminate\Support\Collection;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * Auto-assignment hands unassigned RMO rows to one CSR instead of leaving them
 * to be claimed. The workspace picks who; every unassigned row for a delivery
 * date goes to them.
 *
 * A row already carrying an assignee is never touched — manual assignment wins,
 * the same rule the bulk-assign action follows. So a CSR who claims a row by
 * hand, or has one reassigned to them, keeps it.
 *
 * Two entry points drive it: SyncParcelTrackingAction assigns each row as it is
 * created, and `rmo:apply-auto-assign` sweeps up whatever that missed.
 */
class RmoAutoAssign
{
    /**
     * The CSRs a workspace may auto-assign to: Pancake users tied to one of its
     * shops. Same population the RMO management page's assignee picker offers,
     * so the settings page can't offer someone the page can't.
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
     * The configured CSR, or null when none is set or the one that is set is no
     * longer attached to a shop in this workspace. A CSR who has since moved on
     * simply drops out rather than collecting orders nobody is working — and an
     * id that was never valid can't leak in through an old payload.
     */
    public static function assignee(Workspace $workspace): ?string
    {
        $configured = $workspace->loadMissing('rmoSetting')->rmoSetting?->auto_assign_user_id;
        $configured = is_scalar($configured) ? trim((string) $configured) : '';

        if ($configured === '') {
            return null;
        }

        $stillHere = PancakeUser::query()
            ->whereKey($configured)
            ->whereHas('shops', fn ($query) => $query->where('shops.workspace_id', $workspace->id))
            ->exists();

        return $stillHere ? $configured : null;
    }

    /**
     * Hand every unassigned row on $date to the configured CSR, and report how
     * many were taken. A no-op when the workspace has auto-assignment off or
     * nobody set.
     *
     * Re-running is harmless: assigned rows are skipped, so a second pass over
     * a settled date assigns nothing.
     */
    public static function apply(Workspace $workspace, string $date): int
    {
        if (! $workspace->rmoAutoAssignEnabled()) {
            return 0;
        }

        $assignee = self::assignee($workspace);

        if ($assignee === null) {
            return 0;
        }

        return OrderForDelivery::query()
            ->where('workspace_id', $workspace->id)
            ->whereDate('delivery_date', $date)
            ->whereNull('assignee_id')
            ->update(['assignee_id' => $assignee]);
    }

    /**
     * Assign one freshly-synced row, returning the CSR it went to or null when
     * it was left alone (auto-assignment off, nobody set, already assigned, or
     * claimed by someone between the read and the write).
     */
    public static function assignOne(Workspace $workspace, OrderForDelivery $order): ?string
    {
        if (! $workspace->rmoAutoAssignEnabled() || $order->assignee_id) {
            return null;
        }

        $assignee = self::assignee($workspace);

        if ($assignee === null) {
            return null;
        }

        $updated = OrderForDelivery::query()
            ->whereKey($order->getKey())
            // Re-checked at write time: a CSR may have claimed this row by hand
            // between the read above and here, and their claim wins.
            ->whereNull('assignee_id')
            ->update(['assignee_id' => $assignee]);

        if (! $updated) {
            return null;
        }

        // The caller keeps working with this instance after we return, so don't
        // hand it back still believing it is unassigned. The value is already in
        // the database, so sync the original too — the model isn't dirty.
        $order->setAttribute('assignee_id', $assignee)->syncOriginalAttribute('assignee_id');

        return $assignee;
    }
}
