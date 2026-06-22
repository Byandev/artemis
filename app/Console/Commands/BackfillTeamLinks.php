<?php

namespace App\Console\Commands;

use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Modules\MetaAds\Models\AdAccount;

/**
 * Seeds the team_page and team_ad_account links used by team-level visibility,
 * derived from existing ownership:
 *   - Pages   -> the teams the page's owner belongs to (in the same workspace).
 *   - Ad accts -> the teams of the app user who connected the account (manage tier),
 *                 to mirror today's full access. Admins can downgrade later.
 *
 * Records with no resolvable team are reported and left unlinked for manual
 * assignment via the team data-access screens.
 */
class BackfillTeamLinks extends Command
{
    protected $signature = 'teams:backfill-team-links
        {--workspace= : Limit to a single workspace id}
        {--dry-run : Report what would change without writing}';

    protected $description = 'Backfill team_page and team_ad_account links from existing ownership for team-level visibility.';

    public function handle(): int
    {
        $dry = (bool) $this->option('dry-run');
        $prefix = $dry ? '[dry-run] ' : '';

        $workspaces = Workspace::query()
            ->when($this->option('workspace'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        $pageLinks = 0;
        $pagesUnlinked = 0;
        $accountLinks = 0;
        $accountsUnlinked = 0;

        foreach ($workspaces as $workspace) {
            // Pages -> owner's teams in this workspace.
            foreach (Page::ofWorkspace($workspace)->with('owner.teams')->get() as $page) {
                $teamIds = $page->owner
                    ? $page->owner->teams->where('workspace_id', $workspace->id)->pluck('id')
                    : collect();

                if ($teamIds->isEmpty()) {
                    $pagesUnlinked++;

                    continue;
                }

                if (! $dry) {
                    $page->teams()->syncWithoutDetaching($teamIds);
                }

                $pageLinks += $teamIds->count();
            }

            // Ad accounts -> teams of the app user(s) who connected them (manage tier).
            foreach (AdAccount::forWorkspace($workspace)->get() as $account) {
                $connectingUserIds = DB::table('meta_ads_user_account as mua')
                    ->join('meta_ads_workspace_user as mwu', 'mwu.meta_ads_user_id', '=', 'mua.meta_ads_user_id')
                    ->where('mua.meta_ads_account_id', $account->id)
                    ->where('mwu.workspace_id', $workspace->id)
                    ->whereNotNull('mwu.connected_by_user_id')
                    ->pluck('mwu.connected_by_user_id')
                    ->unique();

                $teamIds = DB::table('team_user')
                    ->join('teams', 'teams.id', '=', 'team_user.team_id')
                    ->whereIn('team_user.user_id', $connectingUserIds)
                    ->where('teams.workspace_id', $workspace->id)
                    ->pluck('teams.id')
                    ->unique();

                if ($teamIds->isEmpty()) {
                    $accountsUnlinked++;

                    continue;
                }

                if (! $dry) {
                    $account->teams()->syncWithoutDetaching(
                        $teamIds->mapWithKeys(fn ($id) => [$id => ['access_level' => 'manage']])->all()
                    );
                }

                $accountLinks += $teamIds->count();
            }
        }

        $this->info("{$prefix}Page links written: {$pageLinks}; pages with no resolvable team: {$pagesUnlinked}");
        $this->info("{$prefix}Ad-account links written: {$accountLinks}; ad accounts with no resolvable team: {$accountsUnlinked}");

        return self::SUCCESS;
    }
}
