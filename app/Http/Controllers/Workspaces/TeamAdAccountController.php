<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\MetaAds\Models\AdAccount;

class TeamAdAccountController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::ViewTeams->value, $workspace);
        $this->ensureTeamBelongsToWorkspace($team, $workspace);

        // Only synced (active) ad accounts are managed on this screen, annotated
        // with the Facebook user(s) who connected them.
        $accounts = AdAccount::forWorkspace($workspace)
            ->where('active_sync', true)
            ->with(['metaUsers' => function ($q) use ($workspace) {
                $q->whereHas('workspaces', fn ($w) => $w->where('workspaces.id', $workspace->id))
                    ->select('meta_ads_users.id', 'meta_ads_users.name');
            }])
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($account) => [
                'id' => (string) $account->id,
                'name' => $account->name,
                'connected_by' => $account->metaUsers->pluck('name')->filter()->implode(', '),
            ]);

        // { "<adAccountId>": "view"|"manage" } for already-linked active accounts.
        $assigned = $team->adAccounts()
            ->where('active_sync', true)
            ->get(['meta_ads_accounts.id'])
            ->mapWithKeys(fn ($account) => [(string) $account->id => $account->pivot->access_level]);

        return Inertia::render('workspaces/teams/ad-accounts', [
            'workspace' => $workspace,
            'team' => $team->only(['id', 'name']),
            'adAccounts' => $accounts,
            'assigned' => $assigned,
        ]);
    }

    public function update(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::EditTeams->value, $workspace);
        $this->ensureTeamBelongsToWorkspace($team, $workspace);

        $validated = $request->validate([
            'ad_accounts' => ['array'],
            'ad_accounts.*.id' => ['required'],
            'ad_accounts.*.access_level' => ['required', Rule::in(['view', 'manage'])],
        ]);

        // This screen only manages synced (active) ad accounts. Restrict the save
        // to them so links to inactive accounts are left untouched.
        $activeAccountIds = AdAccount::forWorkspace($workspace)
            ->where('active_sync', true)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $sync = [];
        foreach ($validated['ad_accounts'] ?? [] as $entry) {
            if (in_array((string) $entry['id'], $activeAccountIds, true)) {
                $sync[$entry['id']] = ['access_level' => $entry['access_level']];
            }
        }

        // Replace only the active-account links; inactive-account links are preserved.
        $team->adAccounts()->detach($activeAccountIds);

        if (! empty($sync)) {
            $team->adAccounts()->attach($sync);
        }

        return redirect()->back()->with('success', 'Team ad accounts updated successfully.');
    }

    private function ensureTeamBelongsToWorkspace(Team $team, Workspace $workspace): void
    {
        if ($team->workspace_id !== $workspace->id) {
            abort(403, 'This team does not belong to the current workspace.');
        }
    }
}
