<?php

namespace Modules\Billing\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\Billing\Models\WorkspaceBillingDetail;

/**
 * Per-workspace billing details. Viewing needs "View Billing Settings";
 * saving additionally needs "Manage Billing Settings".
 */
class BillingSettingsController extends Controller
{
    use AuthorizesRequests;

    public function edit(Request $request, Workspace $workspace): Response
    {
        $this->ensureAvailable($request, $workspace);
        $this->authorize(Permission::ViewBillingSettings->value, $workspace);

        $setting = WorkspaceBillingDetail::forWorkspace($workspace->id);

        return Inertia::render('settings/billing', [
            'workspace' => $workspace,
            'settings' => [
                'billing_name' => $setting->billing_name,
                'billing_address' => $setting->billing_address,
                'billing_email' => $setting->billing_email,
            ],
        ]);
    }

    public function update(Request $request, Workspace $workspace): RedirectResponse
    {
        $this->ensureAvailable($request, $workspace);
        $this->authorize(Permission::ManageBillingSettings->value, $workspace);

        $validated = $request->validate([
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'billing_email' => ['nullable', 'email', 'max:255'],
        ]);

        WorkspaceBillingDetail::updateOrCreate(
            ['workspace_id' => $workspace->id],
            $validated,
        );

        return Redirect::route('billing-settings.edit', ['workspace' => $workspace->slug])
            ->with('status', 'billing-settings-updated');
    }

    /**
     * Billing settings only exist for members of a workspace that has the
     * Billing module switched on. Owners bypass the permission checks (they get
     * '*'), so the module flag has to be enforced here rather than left to the
     * disabled-permission-category filter.
     */
    private function ensureAvailable(Request $request, Workspace $workspace): void
    {
        abort_unless($workspace->billing_module_enabled, 404);

        abort_unless(
            $workspace->users()->whereKey($request->user()->getKey())->exists()
                || $workspace->owner_id === $request->user()->getKey(),
            403,
        );
    }
}
