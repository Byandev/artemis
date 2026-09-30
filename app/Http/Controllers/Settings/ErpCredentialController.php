<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ErpCredentialUpdateRequest;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ErpCredentialController extends Controller
{
    use AuthorizesRequests;

    /**
     * Show the workspace's ERP automation credentials.
     */
    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureCanManage($request, $workspace);

        return Inertia::render('settings/erp-credentials', [
            // Hidden/append casts ensure the encrypted password never leaves the
            // server — only `erp_username` and `erp_password_set` are serialized.
            'workspace' => $workspace,
        ]);
    }

    /**
     * Persist the workspace's ERP automation credentials.
     */
    public function update(ErpCredentialUpdateRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureCanManage($request, $workspace);

        $validated = $request->validated();

        $workspace->erp_username = $validated['erp_username'] ?? null;

        // Only overwrite the stored password when a new one was supplied; an
        // empty field leaves the existing credential in place.
        if (filled($validated['erp_password'] ?? null)) {
            $workspace->erp_password = $validated['erp_password'];
        }

        $workspace->save();

        return Redirect::route('erp-credentials.edit', ['workspace' => $workspace->slug])
            ->with('status', 'erp-credentials-updated');
    }

    /**
     * Clear the stored ERP password without touching the username.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureCanManage($request, $workspace);

        $workspace->update(['erp_password' => null]);

        return Redirect::route('erp-credentials.edit', ['workspace' => $workspace->slug])
            ->with('status', 'erp-credentials-cleared');
    }

    /**
     * Guard the page: the workspace has to be running Gencys ERP for these
     * credentials to mean anything, and the caller has to be a member of it
     * holding the grant. Owners and super admins pass the grant check through
     * the global Gate::before, so the module check has to come first — without
     * it they'd see a live form on a workspace with no ERP behind it.
     */
    private function ensureCanManage(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->gencys_module_enabled, 404);

        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );

        $this->authorize(Permission::ManageErpCredentials->value, $workspace);
    }
}
