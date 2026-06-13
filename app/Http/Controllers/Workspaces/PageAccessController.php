<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Models\PageAccessGrant;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use App\Support\Access\PageAccessScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

/**
 * Manage which pages a member / team may see. A grantee with no grants (and, for
 * members, in no granted team) has full access. Editing happens on a dedicated
 * page reached from the members / teams lists.
 */
class PageAccessController extends Controller
{
    use AuthorizesRequests;

    public function editUser(Request $request, Workspace $workspace, User $user)
    {
        $this->authorize(Permission::EditMembers->value, $workspace);

        abort_unless($user->isMemberOf($workspace) || $workspace->isOwner($user), 404);

        return Inertia::render('workspaces/access/user', [
            'workspace' => $workspace,
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_owner' => $workspace->isOwner($user),
            ],
            'pages' => $this->workspacePages($workspace),
            'pageIds' => $this->grantedPageIds($workspace, $user),
        ]);
    }

    public function updateUser(Request $request, Workspace $workspace, User $user)
    {
        $this->authorize(Permission::EditMembers->value, $workspace);

        abort_unless($user->isMemberOf($workspace) || $workspace->isOwner($user), 404);

        $this->sync($workspace, $user, $this->validatedPageIds($request, $workspace));

        return back()->with('success', "Access updated for {$user->name}.");
    }

    public function editTeam(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::EditTeams->value, $workspace);

        abort_unless($team->workspace_id === $workspace->id, 404);

        return Inertia::render('workspaces/access/team', [
            'workspace' => $workspace,
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'members_count' => $team->members()->count(),
            ],
            'pages' => $this->workspacePages($workspace),
            'pageIds' => $this->grantedPageIds($workspace, $team),
        ]);
    }

    public function updateTeam(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::EditTeams->value, $workspace);

        abort_unless($team->workspace_id === $workspace->id, 404);

        $this->sync($workspace, $team, $this->validatedPageIds($request, $workspace));

        return back()->with('success', "Access updated for {$team->name}.");
    }

    /**
     * @return array<int, array{id: int, name: string}>
     */
    private function workspacePages(Workspace $workspace): array
    {
        return Page::where('workspace_id', $workspace->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Page $p) => ['id' => (int) $p->id, 'name' => $p->name])
            ->all();
    }

    /**
     * @return array<int, int>
     */
    private function grantedPageIds(Workspace $workspace, Model $grantee): array
    {
        return PageAccessGrant::where('workspace_id', $workspace->id)
            ->where('grantee_type', $grantee->getMorphClass())
            ->where('grantee_id', $grantee->getKey())
            ->pluck('page_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Validate submitted page ids and keep only those in the workspace.
     *
     * @return array<int, int>
     */
    private function validatedPageIds(Request $request, Workspace $workspace): array
    {
        $validated = $request->validate([
            'page_ids' => ['present', 'array'],
            'page_ids.*' => ['integer'],
        ]);

        return Page::where('workspace_id', $workspace->id)
            ->whereIn('id', $validated['page_ids'])
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Replace the grantee's page grants for this workspace with the given set.
     *
     * @param  array<int, int>  $pageIds
     */
    private function sync(Workspace $workspace, Model $grantee, array $pageIds): void
    {
        DB::transaction(function () use ($workspace, $grantee, $pageIds) {
            PageAccessGrant::where('workspace_id', $workspace->id)
                ->where('grantee_type', $grantee->getMorphClass())
                ->where('grantee_id', $grantee->getKey())
                ->delete();

            if ($pageIds === []) {
                return;
            }

            $now = now();
            PageAccessGrant::insert(array_map(fn (int $pageId) => [
                'workspace_id' => $workspace->id,
                'grantee_type' => $grantee->getMorphClass(),
                'grantee_id' => $grantee->getKey(),
                'page_id' => $pageId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $pageIds));
        });

        PageAccessScope::flush();
    }
}
