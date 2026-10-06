<?php

namespace Modules\Creatives\Http\Controllers\Concerns;

use App\Models\Workspace;
use Illuminate\Http\Request;
use Modules\Creatives\Models\Creative;

trait GuardsCreatives
{
    /**
     * Membership first; then, since route-model binding resolves a creative by
     * id alone, make sure it belongs to the workspace in the URL.
     */
    private function guard(Request $request, Workspace $workspace, ?Creative $creative = null): void
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403);
        }

        if ($creative && $creative->workspace_id !== $workspace->id) {
            abort(404);
        }
    }
}
