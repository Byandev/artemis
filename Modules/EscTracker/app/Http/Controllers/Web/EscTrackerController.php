<?php

namespace Modules\EscTracker\Http\Controllers\Web;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class EscTrackerController extends Controller
{
    use AuthorizesRequests;

    /**
     * Extreme Self-Care tracker: every user in the workspace, their streaks, and
     * how many days they logged within the selected date range.
     *
     * Records are logged by employees through the WellSync API (per-user Sanctum
     * token) which maintains the streak columns; this page is the read-only
     * workspace view of that data.
     *
     * Note the date range only scopes `days_logged`. `current_streak` and
     * `longest_streak` are all-time values stored on `users` — a date filter
     * can't meaningfully narrow them, so they're reported as-is.
     */
    public function index(Request $request, Workspace $workspace)
    {
        if (! $request->user()->isMemberOf($workspace)) {
            abort(403, 'You do not have access to this workspace.');
        }

        // The module is opt-in per workspace (admin → Toggle Modules).
        abort_unless($workspace->esc_tracker_module_enabled, 404);

        $this->authorize(Permission::ViewEscTracker->value, $workspace);

        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        // Default to the current month — the span people actually review.
        $from = isset($validated['from'])
            ? Carbon::parse($validated['from'])->startOfDay()
            : Carbon::now()->startOfMonth();

        $to = isset($validated['to'])
            ? Carbon::parse($validated['to'])->endOfDay()
            : Carbon::now()->endOfDay();

        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        // Inclusive day count, used by the page to show "X / N days".
        $daysInRange = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;

        // Workspace-wide read that every member hits, so it's cached. The range
        // is part of the key — without it, switching dates would serve another
        // range's numbers from cache.
        $members = Cache::remember(
            "workspace:{$workspace->id}:esc-tracker:members:{$fromDate}:{$toDate}",
            now()->addSeconds(60),
            fn () => $workspace->users()
                // Only the columns the page renders — not users.*, which drags
                // along password hashes, 2FA secrets and tokens.
                ->select(['users.id', 'users.name', 'users.current_streak', 'users.longest_streak'])
                // Correlated subquery rather than loading records — resolves on
                // the (user_id, record_date) unique index.
                ->withCount([
                    'dailyEscRecords as days_logged' => fn ($query) => $query
                        ->whereBetween('record_date', [$fromDate, $toDate]),
                ])
                ->orderBy('users.name')
                ->get()
                ->map(fn (User $user) => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'current_streak' => (int) $user->current_streak,
                    'longest_streak' => (int) $user->longest_streak,
                    'days_logged' => (int) $user->days_logged,
                ])
                // Cache plain arrays, not Eloquent models — far cheaper to
                // serialise and immune to model changes invalidating the cache.
                ->all(),
        );

        return Inertia::render('workspaces/esc-tracker', [
            'workspace' => $workspace,
            'members' => $members,
            'range' => [
                'from' => $fromDate,
                'to' => $toDate,
                'days' => $daysInRange,
            ],
        ]);
    }
}
