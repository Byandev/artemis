<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Sorts\WorkspaceInvitation\InviterNameSort;
use App\Http\Sorts\WorkspaceMember\RoleNameSort;
use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
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
            ->leftJoin('departments', 'workspace_user.department_id', '=', 'departments.id')
            ->where('workspace_user.workspace_id', $workspace->id)
            ->select(
                'users.*',
                'workspace_user.role as pivot_legacy_role',
                'workspace_user.role_id as pivot_role_id',
                'workspace_user.department_id as pivot_department_id',
                'workspace_user.created_at as pivot_created_at',
                'roles.name as pivot_role_name',
                'departments.name as pivot_department_name',
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
                AllowedSort::field('department', 'departments.name'),
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
                    'department_id' => $user->pivot_department_id,
                    'department' => $user->pivot_department_name,
                    'created_at' => $user->pivot_created_at,
                ];
                unset(
                    $user->pivot_legacy_role,
                    $user->pivot_role_id,
                    $user->pivot_role_name,
                    $user->pivot_department_id,
                    $user->pivot_department_name,
                    $user->pivot_created_at,
                );

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
            'departments' => Department::ofWorkspace($workspace)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name']),
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

        // Guardrail: a scoped role (one without "View All Workspace Data") sees only
        // its team's data, so a member on no team would see nothing. Warn, don't block.
        $roleHasBypass = Role::where('id', $validated['role_id'])
            ->whereHas('permissions', fn ($q) => $q->where('name', Permission::ViewAllWorkspaceData->value))
            ->exists();

        $onTeam = $user->teams()->where('teams.workspace_id', $workspace->id)->exists();

        if (! $roleHasBypass && ! $onTeam) {
            return back()->with('warning', 'Member role updated. This role only sees its team\'s data, but this member is not on any team yet — they will see no pages, orders or metrics until you add them to a team.');
        }

        return back()->with('success', 'Member role updated successfully.');
    }

    public function assignDepartment(Request $request, Workspace $workspace, User $user)
    {
        $this->authorize(Permission::EditMembers->value, $workspace);

        if (! $user->isMemberOf($workspace)) {
            return back()->withErrors(['error' => 'This user is not a member of the workspace.']);
        }

        $validated = $request->validate([
            'department_id' => $this->departmentRule($workspace),
        ]);

        $workspace->assignMemberDepartment($user, $validated['department_id'] ?? null);

        return back()->with('success', 'Department updated successfully.');
    }

    public function bulkAssignDepartment(Request $request, Workspace $workspace)
    {
        $this->authorize(Permission::EditMembers->value, $workspace);

        $validated = $request->validate([
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => [
                Rule::exists('workspace_user', 'user_id')
                    ->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
            ],
            'department_id' => $this->departmentRule($workspace),
        ]);

        $count = $workspace->assignMembersDepartment(
            $validated['user_ids'],
            $validated['department_id'] ?? null,
        );

        $label = $validated['department_id'] ? 'assigned' : 'cleared';

        return back()->with('success', "Department {$label} for {$count} member(s).");
    }

    /**
     * A nullable department must belong to the current workspace.
     *
     * @return array<int, mixed>
     */
    private function departmentRule(Workspace $workspace): array
    {
        return [
            'nullable',
            Rule::exists('departments', 'id')
                ->where(fn ($query) => $query->where('workspace_id', $workspace->id)),
        ];
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
