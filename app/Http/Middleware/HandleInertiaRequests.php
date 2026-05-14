<?php

namespace App\Http\Middleware;

use App\Models\Role;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
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

        $currentWorkspace = $request->route('workspace');

        $workspaceModel = ($currentWorkspace instanceof Workspace) ? $currentWorkspace : null;

        $workspaces = $request->user()
            ? ($request->user()->isSuperAdmin()
                ? Workspace::query()->limit(50)->get()
                : $request->user()->workspaces()->limit(3)->get())
            : collect();

        $user = $request->user();
        $permissions = $this->resolvePermissions($user, $workspaceModel);
        $isOwner = $user && $workspaceModel
            ? $user->ownsWorkspace($workspaceModel)
            : false;
        $can = [
            'viewAnySupportTickets' => $user && $currentWorkspace instanceof Workspace
                ? $user->ownsWorkspace($currentWorkspace)
                    || $user->isAdminOf($currentWorkspace)
                    || $user->hasWorkspaceRole($currentWorkspace, 'admin')
                : false,
        ];

        // Show syncing modal when any page has no orders_last_synced_at
        $syncingData = null;
        if ($currentWorkspace instanceof Workspace && $currentWorkspace->pages()->exists()) {
            $hasAnySyncedPage = $currentWorkspace->pages()
                ->whereNotNull('orders_last_synced_at')
                ->exists();

            if (! $hasAnySyncedPage) {
                $syncingData = ['workspaceSlug' => $currentWorkspace->slug];
            }
        }

        // Check subscription status for current workspace
        // On localhost, skip the subscription gate entirely
        $subscriptionExpired = null;
        if ($currentWorkspace instanceof Workspace && ! app()->isLocal()) {
            $subscription = $currentWorkspace->subscription;

            $isExpired = ! $subscription
                || $subscription->status === Subscription::STATUS_EXPIRED
                || $subscription->status === Subscription::STATUS_CANCELED
                || ($subscription->status === Subscription::STATUS_TRIALING && $subscription->trial_ends_at && $subscription->trial_ends_at->isPast())
                || ($subscription->status === Subscription::STATUS_ACTIVE && $subscription->current_period_end && $subscription->current_period_end->isPast());

            if ($isExpired) {
                $subscriptionExpired = [
                    'workspace' => $currentWorkspace->only('id', 'name', 'slug'),
                    'plans' => SubscriptionPlan::where('is_active', true)
                        ->where('code', '!=', SubscriptionPlan::CODE_FREE_TRIAL)
                        ->orderBy('sort_order')
                        ->get(),
                    'current_period_end' => $subscription?->current_period_end?->toIso8601String()
                        ?? $subscription?->trial_ends_at?->toIso8601String(),
                ];
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
                    'can' => $can,
                ]) : null,
            ],
            'workspaces' => $workspaces,

            'currentWorkspace' => $workspaceModel ? array_merge($workspaceModel->toArray(), [

                'metric_setting' => $workspaceModel->loadMissing('metricSetting')->metricSetting,
                'metricSettings' => $workspaceModel->getMetricSettings(),

            ]) : $currentWorkspace,

            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'ziggy' => [
                'location' => $request->url(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'newApiKey' => $request->session()->get('newApiKey'),
            ],
            'appEnv' => config('app.env'),
            'subscriptionExpired' => $subscriptionExpired,
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

        // TEMP: bypass role/permission checks in production while RBAC rollout is still on the test server.
        if (app()->environment('production')) {
            return ['*'];
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
            $workspace->finance_module_enabled ? null : 'Finance',
            $workspace->inventory_module_enabled ? null : 'Inventory',
            $workspace->products_module_enabled ? null : 'Products',
            $workspace->teams_module_enabled ? null : 'Teams',
            $workspace->checklist_module_enabled ? null : 'Checklist',
            $workspace->csr_module_enabled ? null : 'CSR',
            $workspace->botcake_module_enabled ? null : 'Botcake',
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
