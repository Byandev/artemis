<?php

namespace App\Http\Controllers\Settings;

use App\Exceptions\WelleAuthException;
use App\Exceptions\WelleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WelleIntegrationUpdateRequest;
use App\Models\Workspace;
use App\Services\Welle\WelleClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Third-party accounts, one section per provider. Currently just Welle, whose
 * credentials belong to the signed-in user while the `welle_module_enabled`
 * toggle super admins flip decides whether a workspace sees the page at all.
 */
class IntegrationSettingsController extends Controller
{
    /**
     * Show the user's connected integrations for this workspace.
     */
    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $user = $request->user();

        return Inertia::render('settings/integrations', [
            'workspace' => $workspace,
            // The encrypted token never leaves the server — the page only
            // learns the email, whether a token is on file, and how the last
            // unattended fetch went.
            'welle' => [
                'email' => $user->welle_email,
                'connected' => $user->hasWelleToken(),
                'last_synced_at' => $user->welle_last_synced_at?->toIso8601String(),
                'last_error' => $user->welle_last_error,
            ],
        ]);
    }

    /**
     * Connect the signed-in user's Welle account.
     *
     * The password is sent straight to Welle, exchanged for an API token, and
     * then dropped — it is never written down. That is the whole point of doing
     * the exchange here rather than storing credentials and replaying them on
     * every fetch: what sits in the database is a token Welle can revoke, not a
     * password the person probably uses elsewhere.
     */
    public function updateWelle(
        WelleIntegrationUpdateRequest $request,
        Workspace $workspace,
        WelleClient $client,
    ): RedirectResponse {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $validated = $request->validated();

        try {
            $token = $client->login($validated['welle_email'], $validated['welle_password']);
        } catch (WelleAuthException) {
            throw ValidationException::withMessages([
                'welle_password' => 'Welle did not accept that email and password.',
            ]);
        } catch (WelleException) {
            // Welle being unreachable is not the person's mistake, and saying
            // so on the password field would read as one.
            throw ValidationException::withMessages([
                'welle_email' => 'Could not reach Welle just now. Try again in a moment.',
            ]);
        }

        $request->user()->forceFill([
            'welle_email' => $validated['welle_email'],
            'welle_token' => $token,
            // A fresh token makes any previous "reconnect" stale.
            'welle_last_error' => null,
        ])->save();

        return Redirect::route('integrations.edit', ['workspace' => $workspace->slug])
            ->with('status', 'welle-connected');
    }

    /**
     * Disconnect the Welle account, dropping the token and the sync state.
     */
    public function destroyWelle(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $request->user()->forceFill([
            'welle_email' => null,
            'welle_token' => null,
            'welle_last_synced_at' => null,
            'welle_last_error' => null,
        ])->save();

        return Redirect::route('integrations.edit', ['workspace' => $workspace->slug])
            ->with('status', 'welle-disconnected');
    }

    /** Guard against editing from a workspace the user is not a member of. */
    private function ensureMember(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );
    }

    /** The page and its writes only exist while the Welle module is on. */
    private function ensureWelleEnabled(Workspace $workspace): void
    {
        abort_unless($workspace->welle_module_enabled, 404);
    }
}
