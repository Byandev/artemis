<?php

namespace App\Http\Controllers\Settings;

use App\Enums\IntegrationService;
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

        $welle = $request->user()->integrationFor(IntegrationService::Welle);

        return Inertia::render('settings/integrations', [
            'workspace' => $workspace,
            // The encrypted token never leaves the server, and there is no
            // address to show either — the page learns only that something is
            // connected, and how the last unattended fetch went.
            'welle' => [
                'connected' => (bool) $welle?->hasToken(),
                'last_synced_at' => $welle?->last_synced_at?->toIso8601String(),
                'last_error' => $welle?->last_error,
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

        $request->user()->integrations()->updateOrCreate(
            ['service' => IntegrationService::Welle],
            [
                'token' => $token,
                // A fresh token makes any previous "reconnect" stale.
                'last_error' => null,
            ],
        );

        return Redirect::route('integrations.edit', ['workspace' => $workspace->slug])
            ->with('status', 'welle-connected');
    }

    /**
     * Disconnect the Welle account, dropping the token and its sync state with
     * it — there is nothing else to clear, which is the point of the token
     * living in its own row.
     */
    public function destroyWelle(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $request->user()->integrations()->forService(IntegrationService::Welle)->delete();

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
