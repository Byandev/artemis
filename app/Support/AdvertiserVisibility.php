<?php

namespace App\Support;

use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Modules\GencysERP\Models\Intern;

/**
 * Resolves which advertisers a viewer may see in advertiser_performance_daily_records
 * under team scoping. Shared by every Sales & Marketing surface that reads those
 * rows (the Daily Report and the Ad Spent Summary) so they scope identically.
 *
 * "Advertiser" is source-dependent: for the Gencys source it's an Intern linked
 * to a workspace user; for the Artemis source the advertiser id IS the user id.
 * A user is visible when they're a member of a team the viewer can see.
 */
final class AdvertiserVisibility
{
    /**
     * Advertiser ids visible to $viewer for the given source, or null for "no
     * restriction" (unrestricted user with no "viewing as team" selected). An
     * empty array means "nothing" — a scoped user with no visible advertisers,
     * which callers apply as whereIn(..., []) to fail closed.
     *
     * @return array<int, int>|null
     */
    public static function visibleIds(?User $viewer, Workspace $workspace, string $source): ?array
    {
        if (! $viewer) {
            return null;
        }

        $teamIds = TeamVisibility::scopeTeamIds($viewer, $workspace);

        if ($teamIds === null) {
            return null; // unrestricted (and no active team) → everything
        }

        if (empty($teamIds)) {
            return []; // scoped but on no team → nothing (fail closed)
        }

        $memberUserIds = DB::table('team_user')
            ->whereIn('team_id', $teamIds)
            ->pluck('user_id')
            ->unique()
            ->values()
            ->all();

        if (empty($memberUserIds)) {
            return [];
        }

        if ($source === AdvertiserPerformanceDailyRecord::SOURCE_GENCYS) {
            return Intern::where('workspace_id', $workspace->id)
                ->whereIn('user_id', $memberUserIds)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return array_map('intval', $memberUserIds);
    }
}
