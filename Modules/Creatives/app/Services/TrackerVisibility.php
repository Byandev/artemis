<?php

namespace Modules\Creatives\Services;

use App\Enums\Permission;
use App\Models\User;
use App\Models\Workspace;
use App\Support\TeamVisibility;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Modules\Creatives\Models\Creative;

/**
 * Which creatives the Creatives Tracker app may show a user, and what they may
 * do to them. A creative is visible when it sits in a workspace the user
 * belongs to with the Creatives module on, and either they are one of its
 * assigned reviewers or they hold "View Creatives" there (narrowed to their
 * teams, the same as the web list).
 *
 * Permission checks are memoised per workspace, so formatting a page of
 * creatives costs one lookup per workspace rather than per row — and the
 * class is request-scoped so the controller and presenter share that memo.
 */
#[Scoped]
class TrackerVisibility
{
    /** @var array<string, bool> */
    private array $abilities = [];

    /** @var array<int, Collection<int, Workspace>> */
    private array $workspaces = [];

    /**
     * Workspaces with the Creatives module on that the user belongs to.
     *
     * @return Collection<int, Workspace>
     */
    public function workspaces(User $user): Collection
    {
        return $this->workspaces[$user->id] ??= Workspace::query()
            ->where('creatives_module_enabled', true)
            ->when(! $user->isSuperAdmin(), fn ($q) => $q->where(fn ($w) => $w->where('owner_id', $user->id)
                ->orWhereHas('users', fn ($m) => $m->where('users.id', $user->id))))
            ->get();
    }

    public function can(User $user, Workspace $workspace, Permission $permission): bool
    {
        return $this->abilities["{$user->id}:{$workspace->id}:{$permission->value}"] ??= $user->hasPermission($permission, $workspace);
    }

    /** Limit a creatives query to the ones the user may see in the app. */
    public function apply(Builder $query, User $user): Builder
    {
        $workspaces = $this->workspaces($user);
        $viewable = $workspaces->filter(fn (Workspace $w) => $this->can($user, $w, Permission::ViewCreatives));

        return $query->where(function (Builder $q) use ($user, $workspaces, $viewable) {
            // Your own assignments, even without "View Creatives".
            $q->whereIn('workspace_id', $workspaces->modelKeys())
                ->whereHas('assignedReviewers', fn ($r) => $r->where('users.id', $user->id));

            foreach ($viewable as $workspace) {
                $q->orWhere(fn (Builder $w) => $w->where('workspace_id', $workspace->id)
                    ->when(
                        TeamVisibility::shouldScope($user, $workspace),
                        fn ($s) => $s->whereHas('product.pages', fn ($p) => $p->visibleTo($user, $workspace)),
                    ));
            }
        });
    }

    public function canSee(User $user, Creative $creative): bool
    {
        return $this->apply(Creative::query()->whereKey($creative->id), $user)->exists();
    }
}
