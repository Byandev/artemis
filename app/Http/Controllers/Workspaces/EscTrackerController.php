<?php

namespace App\Http\Controllers\Workspaces;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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

        // Workspace-wide read that every member hits, so it's cached. The
        // payload is derived entirely from streak columns that only change when
        // someone saves a record, and a dashboard tolerates being a minute
        // stale — so a short TTL beats invalidating on every write (which would
        // cost an extra "which workspaces is this user in?" query on the hot
        // write path).
        $members = Cache::remember(
            "workspace:{$workspace->id}:esc-tracker:members",
            now()->addSeconds(60),
            fn () => $workspace->users()
                // Only the four columns the page renders — not users.*, which
                // drags along password hashes, 2FA secrets and tokens.
                ->select(['users.id', 'users.name', 'users.current_streak', 'users.longest_streak'])
                ->orderBy('users.name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'current_streak' => (int) $user->current_streak,
                    'longest_streak' => (int) $user->longest_streak,
                ])
                // Cache plain arrays, not Eloquent models — far cheaper to
                // serialise and immune to model changes invalidating the cache.
                ->all(),
        );

        return Inertia::render('workspaces/esc-tracker', [
            'workspace' => $workspace,
            'members' => $members,
        ]);
    }
}
