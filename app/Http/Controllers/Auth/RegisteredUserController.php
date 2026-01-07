<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Show the registration page.
     */
    public function create(Request $request): Response
    {
        $invitation = null;

        // Check if there's an invitation token
        if ($request->has('invitation')) {
            $invitation = WorkspaceInvitation::with(['workspace'])
                ->where('token', $request->invitation)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();
        }

        return Inertia::render('auth/register', [
            'invitation' => $invitation,
            'invitationToken' => $request->invitation,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        // Check if there's an invitation to auto-accept
        if ($request->has('invitation')) {
            $invitation = WorkspaceInvitation::with('workspace')
                ->where('token', $request->invitation)
                ->whereNull('accepted_at')
                ->where('expires_at', '>', now())
                ->first();

            if ($invitation && strcasecmp($user->email, $invitation->email) === 0) {
                // Auto-accept the invitation
                $workspace = $invitation->workspace;
                $workspace->addMember($user, $invitation->role);
                $invitation->markAsAccepted();

                // Redirect to the invitation success page
                return redirect()->to("/workspaces/invitations/{$invitation->token}")
                    ->with('success', 'You have successfully joined the workspace!');
            }
        }

        // Redirect to workspace setup for first-time users
        return redirect()->intended(route('workspaces.setup', absolute: false));
    }
}
