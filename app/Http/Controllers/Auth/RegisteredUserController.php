<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use App\Models\WorkspaceUser;
use App\Services\PostHogService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

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
                ->valid($request->invitation)
                ->first()
                ->load('role');
        }

        return Inertia::render('auth/register', [
            'invitation' => $invitation,
            'invitationToken' => $request->invitation,
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'terms_accepted' => ['accepted'],
        ], [
            'terms_accepted.accepted' => 'You must accept the Terms & Conditions to create an account.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        try {
            event(new Registered($user));
        } catch (TransportExceptionInterface $e) {
            // The account exists by this point, so a verification email that
            // couldn't leave the building is no reason to fail the signup and
            // strand them on the register form. Record it and carry on — the
            // verify-email screen has a resend button for once mail is back.
            report($e);
        }

        Auth::login($user);

        $request->session()->regenerate();

        $posthog = new PostHogService;
        $posthog->identify((string) $user->id, [
            'email' => $user->email,
            'name' => $user->name,
        ]);
        $posthog->capture((string) $user->id, 'user_signed_up', [
            'email' => $user->email,
            'name' => $user->name,
            'via_invitation' => $request->has('invitation'),
        ]);

        // Check if there's an invitation to auto-accept
        if ($request->has('invitation')) {
            $invitation = WorkspaceInvitation::with('workspace')
                ->valid($request->invitation)
                ->first();

            if ($invitation && strcasecmp($user->email, $invitation->email) === 0) {
                // Auto-accept the invitation for consistency with login flow
                DB::transaction(function () use ($invitation, $user) {
                    $workspace = $invitation->workspace;

                    WorkspaceUser::create([
                        'workspace_id' => $workspace->id,
                        'user_id' => $user->id,
                        'role_id' => $invitation->role_id,
                    ]);

                    $invitation->markAsAccepted();
                });

                // Redirect to the invitation success page
                return redirect()->to("/workspaces/invitations/{$invitation->token}")
                    ->with('success', 'You have successfully joined the workspace!');
            }
        }

        // Redirect to workspace setup for first-time users
        return redirect()->intended(route('workspaces.setup', absolute: false));
    }
}
