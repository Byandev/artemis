<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkspaceMemberController extends Controller
{
    /**
     * Display workspace members.
     */
    public function index(Request $request, Workspace $workspace)
    {
        // Check if user has access to this workspace
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $members = $workspace->users()
            ->withPivot('role', 'created_at')
            ->orderByPivot('created_at', 'desc')
            ->get();

        $pendingInvitations = $workspace->pendingInvitations()
            ->with('inviter')
            ->latest()
            ->get();

        $isAdmin = $request->user()->isAdminOf($workspace);

        return Inertia::render('workspaces/members', [
            'workspace' => $workspace,
            'members' => $members,
            'pendingInvitations' => $pendingInvitations,
            'isAdmin' => $isAdmin,
        ]);
    }

    /**
     * Update a member's role.
     */
    public function update(Request $request, Workspace $workspace, User $user)
    {
        // Only admins and owners can update roles
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to update member roles.');
        }

        // Cannot change the owner's role
        if ($workspace->isOwner($user)) {
            return back()->withErrors(['error' => 'Cannot change the workspace owner\'s role.']);
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
        if (! $request->user()->isAdminOf($workspace)) {
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
}
