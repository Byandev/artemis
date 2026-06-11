<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\Workspace;
use App\Support\PublicWorkspaceGate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Public CSR leaderboard page. When accessed with `?workspace=slug` and that
 * workspace has a public-pages password set, a credential gate is shown before
 * the leaderboard is revealed (same mechanism as the public RMO page).
 */
class PublicLeaderboardController extends Controller
{
    public function index(Request $request, ?Workspace $workspace = null)
    {
        // Workspace may come from the path (/leaderboards/{slug}) or, for
        // backward compatibility, the ?workspace=slug query string.
        $workspace = $workspace ?? ($request->filled('workspace')
            ? Workspace::where('slug', $request->query('workspace'))->first()
            : null);

        if ($workspace && ! PublicWorkspaceGate::isUnlocked($request, $workspace, Permission::ViewLeaderboards)) {
            return Inertia::render('workspaces/public/leaderboard', [
                'workspace' => $workspace->only('id', 'name', 'slug'),
                'locked' => true,
            ]);
        }

        return Inertia::render('workspaces/public/leaderboard', [
            'workspace' => $workspace?->only('id', 'name', 'slug'),
            'locked' => false,
        ]);
    }

    public function verifyPublicPassword(Request $request, Workspace $workspace)
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! PublicWorkspaceGate::verify($request, $workspace, $request->input('password'))) {
            throw ValidationException::withMessages([
                'password' => 'Incorrect password.',
            ]);
        }

        return back();
    }
}
