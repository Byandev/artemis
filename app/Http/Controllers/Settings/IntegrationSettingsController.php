<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\WelleIntegrationUpdateRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
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
            // The encrypted password never leaves the server — the page only
            // learns the email and whether a password is on file.
            'welle' => [
                'email' => $user->welle_email,
                'connected' => $user->hasWellePassword(),
            ],
        ]);
    }

    /**
     * Connect (or re-save) the signed-in user's Welle account.
     */
    public function updateWelle(WelleIntegrationUpdateRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $validated = $request->validated();
        $user = $request->user();

        $user->welle_email = $validated['welle_email'];

        // Only overwrite the stored password when a new one was supplied; an
        // empty field leaves the existing credential in place.
        if (filled($validated['welle_password'] ?? null)) {
            $user->welle_password = $validated['welle_password'];
        }

        $user->save();

        return Redirect::route('integrations.edit', ['workspace' => $workspace->slug])
            ->with('status', 'welle-connected');
    }

    /**
     * Disconnect the Welle account, clearing both the email and the password.
     */
    public function destroyWelle(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);
        $this->ensureWelleEnabled($workspace);

        $request->user()->update([
            'welle_email' => null,
            'welle_password' => null,
        ]);

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
