<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Sorts\WorkspaceInvitation\InviterNameSort;
use App\Http\Sorts\WorkspaceMember\RoleNameSort;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Modules\MetaAds\Models\AdAccount;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;

class WorkspaceMemberController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize(Permission::ViewMembers->value, $workspace);

        $members = QueryBuilder::for(User::class)
            // --- CHANGED: Using leftJoin instead of join to ensure Owners (who might not be in the pivot) still show up and sort correctly ---
            ->leftJoin('workspace_user', 'users.id', '=', 'workspace_user.user_id')
            ->leftJoin('roles', 'workspace_user.role_id', '=', 'roles.id')
            ->where('workspace_user.workspace_id', $workspace->id)
            ->select(
                'users.*',
                'workspace_user.role as pivot_legacy_role',
                'workspace_user.role_id as pivot_role_id',
                'workspace_user.created_at as pivot_created_at',
                'roles.name as pivot_role_name',
            )
            ->allowedFilters([
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('users.name', 'like', "%{$value}%")
                            ->orWhere('users.email', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts([
                AllowedSort::field('id', 'users.id'),
                AllowedSort::field('name', 'users.name'),
                AllowedSort::field('email', 'users.email'),
                AllowedSort::custom('role', new RoleNameSort),
                'pivot_created_at',
            ])
            ->defaultSort('-pivot_created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString()
            ->through(function ($user) {
                $role = $user->pivot_role_name;

                if (! $role && $user->pivot_legacy_role) {
                    $role = ucfirst($user->pivot_legacy_role);
                }

                $user->pivot = (object) [
                    'role_id' => $user->pivot_role_id,
                    'role' => $role,
                    'created_at' => $user->pivot_created_at,
                ];
                unset($user->pivot_legacy_role, $user->pivot_role_id, $user->pivot_role_name, $user->pivot_created_at);

                return $user;
            });

        // Ad accounts available in this workspace + each listed member's current
        // per-account grants ({ accountId: 'view'|'manage' }). Drives the
        // "Ad Account Access" dialog; empty when the workspace has no accounts.
        $adAccounts = AdAccount::forWorkspace($workspace)
            ->where('active_sync', true)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (AdAccount $account) => ['id' => (string) $account->id, 'name' => $account->name])
            ->values();

        $accessByUser = DB::table('meta_ads_account_access')
            ->where('workspace_id', $workspace->id)
            ->whereIn('user_id', $members->getCollection()->pluck('id'))
            ->get(['user_id', 'meta_ads_account_id', 'access_level'])
            ->groupBy('user_id');

        $members->getCollection()->transform(function ($user) use ($accessByUser) {
            $user->ad_account_access = ($accessByUser[$user->id] ?? collect())
                ->mapWithKeys(fn ($row) => [(string) $row->meta_ads_account_id => $row->access_level])
                ->all();

            return $user;
        });

        // Get pending invitations with pagination — uses invitation_sort / invitation_page params
        $invitationRequest = $request->duplicate(
            query: array_merge($request->query(), $request->has('invitation_sort') ? ['sort' => $request->input('invitation_sort')] : [])
        );

        $pendingInvitations = QueryBuilder::for($workspace->pendingInvitations()->getQuery(), $invitationRequest)
            ->leftJoin('roles', 'roles.id', '=', 'workspace_invitations.role_id')
            ->select('workspace_invitations.*')
            ->with(['inviter', 'role'])
            ->allowedFilters([AllowedFilter::partial('search', 'email')])
            ->allowedSorts([
                'id',
                'email',
                'expires_at',
                AllowedSort::field('role_name', 'roles.name'),
                AllowedSort::custom('inviter_name', new InviterNameSort, 'inviter.name'),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->input('perPage', 10), ['*'], 'invitation_page')
            ->withQueryString();

        return Inertia::render('workspaces/members', [
            'workspace' => $workspace,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'isAdmin' => $request->user()->isAdminOf($workspace),
            'roles' => Role::where('workspace_id', $workspace->id)->get(),
            'adAccounts' => $adAccounts,
            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'invitation_sort' => $request->input('invitation_sort'),
                'invitation_page' => $request->input('invitation_page'),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function store(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::InviteMembers->value, $workspace);

        $request->validate([
            'email' => 'required|email',
            'role_id' => 'required|exists:roles,id',
        ]);

        $workspace->invitations()->create([
            'email' => $request->email,
            'role_id' => $request->role_id,
        ]);

        return back()->with('success', 'Invitation sent.');
    }

    public function updateMember(Request $request, Workspace $workspace, User $user)
    {
        // NEW: Check granular permission
        $this->authorize(Permission::EditMembers->value, $workspace);

        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot change the workspace owner\'s role.']);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $workspace->updateMemberRole($user, $validated['role_id']);

        return back()->with('success', 'Member role updated successfully.');
    }

    /**
     * Replace a member's per-ad-account access grants for this workspace.
     *
     * An empty payload clears all grants, returning the member to full
     * (unrestricted) access. Each grant is `view` (read-only) or `manage`
     * (create/edit optimization rules + approve/reject proposals for it).
     */
    public function updateAdAccountAccess(Request $request, Workspace $workspace, User $user)
    {
        $this->authorize(Permission::EditMembers->value, $workspace);

        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot change the workspace owner\'s access.']);
        }

        abort_unless($user->isMemberOf($workspace), 404);

        $allowedAccountIds = AdAccount::forWorkspace($workspace)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $validated = $request->validate([
            'access' => ['present', 'array'],
            'access.*.meta_ads_account_id' => ['required', Rule::in($allowedAccountIds)],
            'access.*.level' => ['required', Rule::in(['view', 'manage'])],
        ]);

        DB::transaction(function () use ($workspace, $user, $validated) {
            DB::table('meta_ads_account_access')
                ->where('workspace_id', $workspace->id)
                ->where('user_id', $user->id)
                ->delete();

            $rows = collect($validated['access'])
                // Last entry wins if an account appears more than once.
                ->keyBy('meta_ads_account_id')
                ->map(fn ($row) => [
                    'workspace_id' => $workspace->id,
                    'user_id' => $user->id,
                    'meta_ads_account_id' => $row['meta_ads_account_id'],
                    'access_level' => $row['level'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
                ->values()
                ->all();

            if ($rows !== []) {
                DB::table('meta_ads_account_access')->insert($rows);
            }
        });

        return back()->with('success', 'Ad account access updated.');
    }

    public function destroy(Request $request, Workspace $workspace, User $user)
    {
        if ($request->user()->id !== $user->id) {
            $this->authorize(Permission::RemoveMembers->value, $workspace);
        }

        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot remove the workspace owner.']);
        }

        $workspace->removeMember($user);

        if ($request->user()->id === $user->id) {
            return redirect()->route('workspaces.index')->with('success', 'You have left the workspace.');
        }

        return back()->with('success', 'Member removed successfully.');
    }

    public function generatePasswordReset(Request $request, Workspace $workspace, User $user)
    {
        $this->authorize(Permission::ResetMemberPassword->value, $workspace);

        $token = Password::createToken($user);
        $url = route('password.reset', ['token' => $token]).'?'.http_build_query(['email' => $user->email]);

        return response()->json(['url' => $url]);
    }
}
