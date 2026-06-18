<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ErpCredentialUpdateRequest;
use App\Models\Workspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ErpCredentialController extends Controller
{
    /**
     * Show the workspace's ERP automation credentials.
     */
    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureMember($request, $workspace);

        return Inertia::render('settings/erp-credentials', [
            // Hidden/append casts ensure the encrypted password never leaves the
            // server — only `erp_email` and `erp_password_set` are serialized.
            'workspace' => $workspace,
        ]);
    }

    /**
     * Persist the workspace's ERP automation credentials.
     */
    public function update(ErpCredentialUpdateRequest $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);

        $validated = $request->validated();

        $workspace->erp_email = $validated['erp_email'] ?? null;

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
     * Clear the stored ERP password without touching the email.
     */
    public function destroy(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureMember($request, $workspace);

        $workspace->update(['erp_password' => null]);

        return Redirect::route('erp-credentials.edit', ['workspace' => $workspace->slug])
            ->with('status', 'erp-credentials-cleared');
    }

    /** Guard against editing a workspace the user is not a member of. */
    private function ensureMember(Request $request, Workspace $workspace): void
    {
        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists(),
            403,
        );
    }
}
