<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Sorts\WorkspaceInvitation\InviterNameSort;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class WorkspaceMemberController extends Controller
{

    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace)
    {
        if (!$request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $this->authorize('View Members', $workspace);

        $members = QueryBuilder::for(User::class)
            ->join('workspace_user', 'users.id', '=', 'workspace_user.user_id')
            ->leftJoin('roles', 'workspace_user.role_id', '=', 'roles.id')
            ->where('workspace_user.workspace_id', $workspace->id)
            ->select(
                'users.*',
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
                'id',
                'name',
                'email',
                AllowedSort::field('role', 'roles.name'),
                'pivot_created_at',
            ])
            ->defaultSort('-pivot_created_at')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString()
            ->through(function ($user) {
                $user->pivot = (object) [
                    'role_id' => $user->pivot_role_id,
                    'role' => $user->pivot_role_name,
                    'created_at' => $user->pivot_created_at,
                ];
                unset($user->pivot_role_id, $user->pivot_role_name, $user->pivot_created_at);
                return $user;
            });

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
        $this->authorize('Invite Members', $workspace);

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
        $this->authorize('Edit Members', $workspace);

        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot change the workspace owner\'s role.']);
        }

        $validated = $request->validate([
            'role_id' => ['required', 'exists:roles,id'],
        ]);

        $workspace->updateMemberRole($user, $validated['role_id']);

        return back()->with('success', 'Member role updated successfully.');
    }

    public function destroy(Request $request, Workspace $workspace, User $user)
    {
        if ($request->user()->id !== $user->id) {
            $this->authorize('Remove Members', $workspace);
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
        $this->authorize('Reset Member Password', $workspace);

        $token = Password::createToken($user);
        $url = route('password.reset', ['token' => $token]) . '?' . http_build_query(['email' => $user->email]);

        return response()->json(['url' => $url]);
    }
}