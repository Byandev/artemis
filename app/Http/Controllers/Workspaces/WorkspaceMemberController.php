<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Http\Sorts\WorkspaceInvitation\InviterNameSort;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;
use Spatie\QueryBuilder\QueryBuilder;
use App\Models\Role;

class WorkspaceMemberController extends Controller
{
    /**
     * Display workspace members.
     */
    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (!$request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // Get members with pagination, sorting, and filtering
        $members = QueryBuilder::for(User::class)
            ->join('workspace_user', 'users.id', '=', 'workspace_user.user_id')
            ->where('workspace_user.workspace_id', $workspace->id)
            ->select(
                'users.*',
                'workspace_user.role as pivot_role',
                'workspace_user.created_at as pivot_created_at'
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
                'pivot_role',
                AllowedSort::field('role', 'pivot_role'), // Map role to pivot_role for consistency with invitations table
                'pivot_created_at',
                AllowedSort::field('expires_at', 'pivot_created_at'), // Map expires_at for consistency
                AllowedSort::field('inviter_name', 'name'), // Map inviter_name to name for consistency
            ])
            ->defaultSort('-pivot_created_at')
            ->paginate($request->input('perPage', 10))
            ->withQueryString()
            ->through(function ($user) {
                // Transform the flat pivot columns back into a nested pivot object
                $user->pivot = (object) [
                    'role' => $user->pivot_role,
                    'created_at' => $user->pivot_created_at,
                ];
                unset($user->pivot_role, $user->pivot_created_at);

                return $user;
            });

        // Get pending invitations with pagination
        $pendingInvitations = QueryBuilder::for($workspace->pendingInvitations()->getQuery())
            ->with('inviter')
            ->allowedFilters([
                AllowedFilter::partial('search', 'email'),
            ])
            ->allowedSorts([
                'id',
                'email',
                AllowedSort::field('name', 'email'), // Map name to email for consistency with members table
                'role',
                AllowedSort::field('pivot_role', 'role'), // Map pivot_role to role for consistency with members table
                AllowedSort::field('pivot_created_at', 'created_at'), // Map pivot_created_at to created_at
                'expires_at',
                AllowedSort::custom('inviter_name', new InviterNameSort, 'inviter.name'),
            ])
            ->defaultSort('-created_at')
            ->paginate($request->input('perPage', 10))
            ->withQueryString();

        $isAdmin = $request->user()->isAdminOf($workspace);

        return Inertia::render('workspaces/members', [
            'workspace' => $workspace,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'isAdmin' => $isAdmin,

            'roles' => Role::where('workspace_id', $workspace->id)
                ->get(['id', 'role', 'display_name', 'description']),

            'query' => [
                ...$request->only(['sort', 'perPage', 'page']),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    /**
     * Update a member's role.
     */
    public function updateMember(Request $request, Workspace $workspace, User $user)
    {

        if (!$request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to update member roles.');
        }

        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot change the workspace owner\'s role.']);
        }

        if ($workspace->owner_id === $user->id) {
            return back()->withErrors(['role' => 'Owner roles are protected.']);
        }

        $validated = $request->validate([
            'role' => ['required', 'in:admin,member'],
        ]);

        $workspace->updateMemberRole($user, $validated['role']);

        return back()->with('success', 'Member role updated successfully.');
    }

    /**
     * Remove a member from the workspace.
     */
    public function destroy(Request $request, Workspace $workspace, User $user)
    {
        // Only admins and owners can remove members
        if (!$request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to remove members.');
        }

        // Cannot remove the owner
        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot remove the workspace owner.']);
        }

        // Users can remove themselves
        if ($request->user()->id === $user->id) {
            $workspace->removeMember($user);

            return redirect()->route('workspaces.index')
                ->with('success', 'You have left the workspace.');
        }

        $workspace->removeMember($user);

        return back()->with('success', 'Member removed successfully.');
    }


    public function store(Request $request, Workspace $workspace)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|string|exists:roles,role',
        ]);

        $workspace->invitations()->create([
            'email' => $request->email,
            'role' => $request->role,
            'token' => \Illuminate\Support\Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        return back();
    }

    public function edit(Workspace $workspace)
    {
        return Inertia::render('Workspaces/Members', [
            'workspace' => $workspace,
            'members' => $workspace->users()->get(),
            // Item 4: Fetch roles filtered by the current workspace
            'roles' => Role::where('workspace_id', $workspace->id)->get(),
        ]);
    }
}
