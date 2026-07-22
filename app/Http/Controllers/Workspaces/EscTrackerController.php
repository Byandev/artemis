<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EscTrackerController extends Controller
{
    /**
     * Extreme Self-Care tracker: every user in the workspace alongside their
     * current and longest streak.
     *
     * Records are logged by employees through the WellSync API (per-user Sanctum
     * token) which maintains the streak columns; this page is the read-only
     * workspace view of those streaks.
     */
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        $members = $workspace->users()
            ->orderBy('users.name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'current_streak' => (int) $user->current_streak,
                'longest_streak' => (int) $user->longest_streak,
            ])
            ->values();

        return Inertia::render('workspaces/esc-tracker', [
            'workspace' => $workspace,
            'members' => $members,
        ]);
    }
}
