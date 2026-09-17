<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\IntegrationService;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own Welle record inside a workspace.
 *
 * Welle credentials belong to the person, not the workspace — they connect
 * their own account under settings/integrations. The workspace only decides
 * whether these pages exist at all, through the `welle_module_enabled` toggle
 * super admins flip in admin workspace module management.
 */
class WelleController extends Controller
{
    use AuthorizesRequests;

    /**
     * My ESC — the person's own Extreme Self Care days.
     *
     * The figures themselves are fetched card by card over XHR; what the page
     * itself has to know is whether there is a Welle account behind it at all.
     * That comes from here rather than from the cards so an unconnected page
     * opens straight onto "connect your account" — asked of the cards, the
     * answer arrives seven times over, after a screen of skeletons that were
     * never going to fill.
     */
    public function myEsc(Request $request, Workspace $workspace): Response
    {
        abort_unless($workspace->welle_module_enabled, 404);

        $this->authorize(Permission::ViewMyEsc->value, $workspace);

        $welle = $request->user()->integrationFor(IntegrationService::Welle);

        return Inertia::render('workspaces/welle/my-esc', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            // The token never leaves the server; the page learns only that the
            // email and password have been exchanged for one.
            'connected' => (bool) $welle?->hasToken(),
        ]);
    }
}
