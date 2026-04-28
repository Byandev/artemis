<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Get current workspace from URL route parameter
        $currentWorkspace = null;
        if ($request->user() && $request->route('workspace')) {
            $currentWorkspace = $request->route('workspace');
        }

        // Get first 3 workspaces of the authenticated user
        $workspaces = $request->user()
            ? $request->user()->workspaces()->limit(3)->get()
            : collect();

        $user = $request->user();
        $permissions = $this->resolvePermissions($user, $currentWorkspace);
        $isOwner = $user && $currentWorkspace instanceof Workspace
            ? $user->ownsWorkspace($currentWorkspace)
            : false;

        // Show syncing modal when any page has no orders_last_synced_at
        $syncingData = null;
        if ($currentWorkspace instanceof Workspace && $currentWorkspace->pages()->exists()) {
            $hasSyncingPages = $currentWorkspace->pages()
                ->whereNull('orders_last_synced_at')
                ->exists();

            if ($hasSyncingPages) {
                $syncingData = ['workspaceSlug' => $currentWorkspace->slug];
            }
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $user ? array_merge($user->toArray(), [
                    'is_super_admin' => $user->isSuperAdmin(),
                    'is_workspace_owner' => $isOwner,
                    'permissions' => $permissions,
                ]) : null,
            ],
            'workspaces' => $workspaces,
            'currentWorkspace' => $currentWorkspace,
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'ziggy' => [
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'newApiKey' => $request->session()->get('newApiKey'),
            ],
            'appEnv' => config('app.env'),
            'syncingData' => $syncingData,
        ];
    }

    /**
     * Resolve the permission names available to the user in the current workspace.
     * Owners and super admins receive ['*'] which the frontend treats as full access.
     *
     * @return array<int, string>
     */
    private function resolvePermissions(?User $user, ?Workspace $workspace): array
    {
        if (! $user) {
            return [];
        }

        if ($user->isSuperAdmin()) {
            return ['*'];
        }

        if (! $workspace instanceof Workspace) {
            return [];
        }

        if ($user->ownsWorkspace($workspace)) {
            return ['*'];
        }

        $roleId = $user->workspaces()
            ->where('workspaces.id', $workspace->id)
            ->first()
            ?->pivot
            ?->role_id;

        if (! $roleId) {
            return [];
        }

        $disabled = array_values(array_filter([
            $workspace->show_finance ? null : 'Finance',
            $workspace->show_inventory ? null : 'Inventory',
        ]));

        return Role::with('permissions:id,name,category')
            ->find($roleId)
            ?->permissions
            ->reject(fn ($permission) => in_array($permission->category, $disabled, true))
            ->pluck('name')
            ->values()
            ->all() ?? [];
    }
}
