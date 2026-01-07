<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceInvitation;
use App\Notifications\WorkspaceInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class WorkspaceInvitationController extends Controller
{
    /**
     * Send invitations to join the workspace.
     */
    public function store(Request $request, Workspace $workspace)
    {
        // Only admins and owners can invite
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to invite members.');
        }

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'in:admin,member'],
        ]);

        // Check if user is already a member
        $existingUser = User::where('email', $validated['email'])->first();
        if ($existingUser && $workspace->hasMember($existingUser)) {
            return back()->withErrors(['email' => 'This user is already a member of the workspace.']);
        }

        // Check if there's already a pending invitation
        $existingInvitation = WorkspaceInvitation::where('workspace_id', $workspace->id)
            ->where('email', $validated['email'])
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($existingInvitation) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email address.']);
        }

        // Create the invitation
        $invitation = WorkspaceInvitation::create([
            'workspace_id' => $workspace->id,
            'invited_by' => $request->user()->id,
            'email' => $validated['email'],
            'role' => $validated['role'],
        ]);

        // Send the invitation email
        Notification::route('mail', $validated['email'])
            ->notify(new WorkspaceInvitationNotification($invitation));

        return back()->with('success', 'Invitation sent successfully.');
    }

    /**
     * Resend an invitation.
     */
    public function resend(Request $request, WorkspaceInvitation $invitation)
    {
        $workspace = $invitation->workspace;

        // Only admins and owners can resend invitations
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to resend invitations.');
        }

        // Check if invitation is still valid
        if ($invitation->isAccepted()) {
            return back()->withErrors(['error' => 'This invitation has already been accepted.']);
        }

        if ($invitation->isExpired()) {
            // Extend the expiration date
            $invitation->update(['expires_at' => now()->addDays(7)]);
        }

        // Resend the invitation email
        Notification::route('mail', $invitation->email)
            ->notify(new WorkspaceInvitationNotification($invitation));

        return back()->with('success', 'Invitation resent successfully.');
    }

    /**
     * Show the invitation acceptance page.
     */
    public function show(Request $request, string $token)
    {
        $invitation = WorkspaceInvitation::with(['workspace', 'inviter'])
            ->where('token', $token)
            ->firstOrFail();

        // Check if invitation is valid
        if (! $invitation->isValid()) {
            // If the invitation was accepted but the current authenticated user
            // is the invitee, show the acceptance page (with an `accepted` flag)
            // so they can see the success state after accepting.
            if ($invitation->isAccepted() && $request->user() && $request->user()->email === $invitation->email) {
                return Inertia::render('workspaces/invitation-accept', [
                    'invitation' => $invitation,
                    'isAuthenticated' => true,
                    'accepted' => true,
                ]);
            }

            return Inertia::render('workspaces/invitation-invalid', [
                'invitation' => $invitation,
                'reason' => $invitation->isExpired() ? 'expired' : 'accepted',
            ]);
        }

        return Inertia::render('workspaces/invitation-accept', [
            'invitation' => $invitation,
            'isAuthenticated' => $request->user() !== null,
        ]);
    }

    /**
     * Accept an invitation.
     */
    public function accept(Request $request, string $token)
    {
        $invitation = WorkspaceInvitation::with('workspace')
            ->where('token', $token)
            ->firstOrFail();

        // Check if invitation is valid
        if (! $invitation->isValid()) {
            // If invitation was already accepted by the current authenticated user,
            // send them to the invitation page so they can see the accepted state.
            if ($invitation->isAccepted() && $request->user() && strcasecmp($request->user()->email, $invitation->email) === 0) {
                return redirect()->to("/workspaces/invitations/{$token}")
                    ->with('success', 'You have already joined the workspace!');
            }

            return back()->withErrors(['error' => 'This invitation is no longer valid.']);
        }

        // If user is not authenticated, they need to register or login first
        if (! $request->user()) {
            // Check if a user with this email already exists
            $existingUser = User::where('email', $invitation->email)->first();

            if ($existingUser) {
                // User exists, redirect to login
                return redirect()->route('login', ['invitation' => $token])
                    ->with('info', 'Please login to accept the invitation.');
            }

            // User doesn't exist, redirect to register
            return redirect()->route('register', ['invitation' => $token])
                ->with('info', 'Please create an account to accept the invitation.');
        }

        // Check if the authenticated user's email matches the invitation (case-insensitive)
        if (strcasecmp($request->user()->email, $invitation->email) !== 0) {
            return back()->withErrors(['error' => 'This invitation was sent to a different email address.']);
        }

        DB::transaction(function () use ($invitation, $request) {
            $workspace = $invitation->workspace;

            // Add user to workspace
            $workspace->addMember($request->user(), $invitation->role);

            // Mark invitation as accepted
            $invitation->markAsAccepted();
        });

        return redirect()->to("/workspaces/invitations/{$token}")
            ->with('success', 'You have successfully joined the workspace!');
    }

    /**
     * Revoke/cancel an invitation.
     */
    public function destroy(Request $request, WorkspaceInvitation $invitation)
    {
        $workspace = $invitation->workspace;

        // Only admins and owners can revoke invitations
        if (! $request->user()->isAdminOf($workspace)) {
            abort(403, 'You do not have permission to revoke invitations.');
        }

        $invitation->delete();

        return back()->with('success', 'Invitation revoked successfully.');
    }
}
