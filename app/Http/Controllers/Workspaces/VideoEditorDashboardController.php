<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\VideoEditorDashboardRequest;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Products\Models\Product;

class VideoEditorDashboardController extends Controller
{
    /**
     * Editor-focused dashboard scoped to the signed-in editor's own creatives.
     *
     * Renders only the shell (filters, product options, identity). Each
     * statistic is fetched independently by the frontend from the matching
     * API\Workspace\VideoEditorDashboardController endpoint, so sections load
     * progressively with their own skeletons.
     */
    public function __invoke(VideoEditorDashboardRequest $request, Workspace $workspace): Response
    {
        return Inertia::render('workspaces/video-editor/dashboard', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'currentUserId' => $request->user()->id,
            'products' => Product::query()
                ->where('workspace_id', $workspace->id)
                ->when(
                    TeamVisibility::shouldScope($request->user(), $workspace),
                    fn ($q) => $q->whereHas('pages', fn ($p) => $p->visibleTo($request->user(), $workspace)),
                )
                ->orderBy('title')
                ->get(['id', 'title']),
            'editors' => $this->creativeUsers($workspace, $request->user()),
            'filters' => $request->filters()->toArray(),
        ]);
    }

    /**
     * Users selectable in the dashboard's User filter: every workspace member
     * who can access creatives — i.e. whose role grants "View Creatives", plus
     * the workspace owner and the signed-in user (who always have access).
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function creativeUsers(Workspace $workspace, User $currentUser)
    {
        $creativeRoleIds = Role::query()
            ->where('workspace_id', $workspace->id)
            ->whereHas('permissions', fn ($q) => $q->where('name', Permission::ViewCreatives->value))
            ->pluck('id');

        return $workspace->users()
            ->where(function ($query) use ($creativeRoleIds, $workspace, $currentUser) {
                $query->whereIn('workspace_user.role_id', $creativeRoleIds)
                    ->orWhere('users.id', $workspace->owner_id)
                    ->orWhere('users.id', $currentUser->id);
            })
            ->when(
                TeamVisibility::shouldScope($currentUser, $workspace),
                fn ($q) => $q->whereIn('users.id', function ($sub) use ($currentUser, $workspace) {
                    $sub->select('user_id')
                        ->from('team_user')
                        ->whereIn('team_id', TeamVisibility::scopeTeamIds($currentUser, $workspace) ?? []);
                }),
            )
            ->orderBy('users.name')
            ->get(['users.id', 'users.name'])
            ->unique('id')
            ->values()
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name]);
    }
}
