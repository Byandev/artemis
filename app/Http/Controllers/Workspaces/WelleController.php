<?php

namespace App\Http\Controllers\Workspaces;

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
     * Deliberately blank for now: the route, the page and the grant are in
     * place so the module toggle and the role editor can be exercised ahead of
     * the content that will fill it.
     */
    public function myEsc(Request $request, Workspace $workspace): Response
    {
        abort_unless($workspace->welle_module_enabled, 404);

        $this->authorize(Permission::ViewMyEsc->value, $workspace);

        return Inertia::render('workspaces/welle/my-esc', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
        ]);
    }
}
