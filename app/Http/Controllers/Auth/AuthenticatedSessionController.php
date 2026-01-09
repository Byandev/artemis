<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Models\WorkspaceInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

class AuthenticatedSessionController extends Controller
{
    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        $invitation = null;

        // Check if there's an invitation token
        if ($request->has('invitation')) {
            $invitation = WorkspaceInvitation::with(['workspace'])
                ->valid($request->invitation)
                ->first();
        }

        return Inertia::render('auth/login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => $request->session()->get('status'),
            'invitation' => $invitation,
            'invitationToken' => $request->invitation,
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->validateCredentials();

        if (Features::enabled(Features::twoFactorAuthentication()) && $user->hasEnabledTwoFactorAuthentication()) {
            $request->session()->put([
                'login.id' => $user->getKey(),
                'login.remember' => $request->boolean('remember'),
            ]);

            return to_route('two-factor.login');
        }

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        // Check if there's an invitation to auto-accept
        if ($request->has('invitation')) {
            $invitation = WorkspaceInvitation::with('workspace')
                ->valid($request->invitation)
                ->first();

            if ($invitation && strcasecmp($user->email, $invitation->email) === 0) {
                // Auto-accept the invitation for consistency with register flow
                DB::transaction(function () use ($invitation, $user) {
                    $workspace = $invitation->workspace;
                    $workspace->addMember($user, $invitation->role);
                    $invitation->markAsAccepted();
                });

                // Redirect to the invitation success page
                return redirect()->to("/workspaces/invitations/{$invitation->token}")
                    ->with('success', 'You have successfully joined the workspace!');
            }
        }

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
