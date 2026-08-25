<?php

namespace Modules\Inventory\Support;

use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Modules\Inventory\Models\InventoryItemSnapshot;

/**
 * The frozen day every item-derived figure reads from, and the query that
 * reaches it.
 *
 * The items list stopped computing per request and started reading
 * inventory_item_snapshots; the dashboard has to read the same rows or its tiles
 * quietly disagree with the table they summarise. Two pages showing the same
 * workspace and different totals is worse than either being slightly stale, so
 * they share this one source.
 *
 * Everything here mirrors InventoryItemController: the same day, the same
 * active-only default, and the same team rule — which deliberately keeps a
 * parent placeholder whose children are visible, because a parent has no product
 * of its own and scoping it directly would drop every group.
 */
class SnapshotItemScope
{
    /**
     * The newest day the workspace has frozen, or null if it has never been.
     *
     * Callers that get null have nothing honest to show and should say so rather
     * than fall back to a live calculation, which would answer for right now
     * under a heading that means "as of the snapshot".
     */
    public static function date(Workspace $workspace): ?string
    {
        return InventoryItemSnapshot::where('workspace_id', $workspace->id)->max('snapshot_date');
    }

    /**
     * Active item rows for that day, team-scoped.
     *
     * Columns keep the names the live query used — sku, unfulfilled_count,
     * current_stocks, remaining_after_fulfillment and the rest are stored
     * outright, so aggregation written against the live shape ports across
     * unchanged. `id` is aliased to the item's id, which is what every row
     * action and drill-down targets.
     *
     * @return Builder<InventoryItemSnapshot>
     */
    public static function query(Request $request, Workspace $workspace, string $date): Builder
    {
        $query = InventoryItemSnapshot::query()
            ->where('inventory_item_snapshots.workspace_id', $workspace->id)
            ->where('inventory_item_snapshots.snapshot_date', $date)
            ->where('inventory_item_snapshots.is_active', true);

        self::applyTeamVisibility($request, $query, $workspace);

        return $query;
    }

    /**
     * Team scoping for snapshot rows. Deliberately not the model's visibleTo()
     * scope: a parent row has no product of its own, so scoping it directly
     * would drop every group from a scoped user's totals.
     *
     * @param  Builder<InventoryItemSnapshot>  $query
     */
    public static function applyTeamVisibility(Request $request, Builder $query, Workspace $workspace): void
    {
        $teamIds = TeamVisibility::scopeTeamIds($request->user(), $workspace);

        // null -> unrestricted (or no "viewing as team"): see everything.
        if ($teamIds === null) {
            return;
        }

        // Scoped user with no team -> nothing. Fail closed.
        if (empty($teamIds)) {
            $query->whereRaw('1 = 0');

            return;
        }

        $inTeams = fn ($q) => $q->whereHas('product.shops.teams', fn ($t) => $t->whereIn('teams.id', $teamIds));

        $query->where(fn ($q) => $inTeams($q)->orWhereHas('children', $inTeams));
    }

    /**
     * The group's identity, resolved from the parent rather than from whichever
     * rows survived a filter — the same expressions the items list rolls up on.
     *
     * @return array<string, string>
     */
    public static function groupIdentity(): array
    {
        $parentRow = fn (string $column) => "(SELECT p.$column FROM inventory_item_snapshots p
            WHERE p.inventory_item_id = inventory_item_snapshots.parent_id
              AND p.snapshot_date = inventory_item_snapshots.snapshot_date LIMIT 1)";

        return [
            'group_sku' => 'COALESCE('.$parentRow('sku').', inventory_item_snapshots.sku)',
            'group_product_name' => 'COALESCE('.$parentRow('product_name').',
                CASE WHEN inventory_item_snapshots.parent_id IS NULL THEN inventory_item_snapshots.product_name END)',
        ];
    }
}
