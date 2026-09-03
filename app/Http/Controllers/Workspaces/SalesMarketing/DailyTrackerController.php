<?php

namespace App\Http\Controllers\Workspaces\SalesMarketing;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workspaces\ToggleDailyTrackerCompletionRequest;
use App\Models\DailyTrackerCompletion;
use App\Models\DailyTrackerItem;
use App\Models\Workspace;
use App\Queries\DailyTrackerBoardQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daily Tracker: the team's deliverables checklist for a day, one column of
 * boxes per tracked member.
 *
 * The board itself is assembled by DailyTrackerBoardQuery; this controller
 * resolves the day and the view being looked at, decides who may tick what, and
 * writes ticks.
 */
class DailyTrackerController extends Controller
{
    use AuthorizesRequests;

    /** The views the page can open on; the first is the default. */
    private const VIEWS = ['checklist', 'matrix'];

    public function index(Request $request, Workspace $workspace): Response
    {
        // Same gating as the rest of the S&M group: module flag + permission.
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewDailyTracker->value, $workspace);

        $user = $request->user();
        $date = $this->resolveDate($request);

        $board = (new DailyTrackerBoardQuery($workspace, $user, $date))->get();

        return Inertia::render('workspaces/sales-marketing/daily-tracker/index', [
            'workspace' => $workspace,
            'date' => $date->toDateString(),
            // Which of the two views to open on. It lives in the URL so a reload
            // (and a shared link) comes back to the one you were reading.
            'view' => $this->resolveView($request),
            'members' => $board['members'],
            'items' => $board['items'],
            // Cast so the map is always a JSON object. Member ids never start
            // at zero, so PHP would not emit a list today — but the page reads
            // these keys as member ids, and a list would silently mean indices.
            'completions' => (object) $board['completions'],
            'viewer' => [
                // Everyone ticks their own row; this is the reach to tick
                // somebody else's, and it drives whether the box is clickable.
                'can_manage' => $user->hasPermission(Permission::ManageDailyTracker, $workspace),
            ],
        ]);
    }

    /**
     * Tick or untick one member's deliverable for the day.
     *
     * Idempotent in both directions: ticking twice leaves one row, unticking
     * something that was never ticked is a no-op. The unique index on
     * (item, member, period) is what makes a double-click safe.
     */
    public function toggle(ToggleDailyTrackerCompletionRequest $request, Workspace $workspace): JsonResponse
    {
        abort_unless($workspace->sales_marketing_dashboard_module_enabled, 404);

        $this->authorize(Permission::ViewDailyTracker->value, $workspace);

        $user = $request->user();
        $targetId = $request->integer('user_id');

        // Only a row the board actually draws can be ticked, read from the same
        // definition the roster is built from.
        abort_unless(
            $workspace->dailyTrackerMembers()->whereKey($targetId)->exists(),
            404,
            'That member is not tracked on this board.',
        );

        // Your own row is yours to tick. Anyone else's needs the manage grant.
        abort_unless(
            $targetId === $user->id || $user->hasPermission(Permission::ManageDailyTracker, $workspace),
            403,
            'You can only tick your own deliverables.',
        );

        /** @var DailyTrackerItem $item */
        $item = DailyTrackerItem::query()
            ->where('workspace_id', $workspace->id)
            ->findOrFail($request->integer('item_id'));

        // The period the tick is filed under — the day for a daily item, its
        // Monday for a weekly one.
        $trackedOn = $item->cadence->periodStart($request->date('date'));

        $completed = $request->boolean('completed');

        $key = [
            'daily_tracker_item_id' => $item->id,
            'user_id' => $targetId,
            'tracked_on' => $trackedOn->toDateString(),
        ];

        if ($completed) {
            DailyTrackerCompletion::updateOrCreate($key, [
                'workspace_id' => $workspace->id,
                'checked_by' => $user->id,
                'completed_at' => now(),
            ]);
        } else {
            DailyTrackerCompletion::query()->where($key)->delete();
        }

        return response()->json([
            'item_id' => $item->id,
            'user_id' => $targetId,
            'completed' => $completed,
        ]);
    }

    /**
     * Which view to open on. Anything unrecognised falls back to the checklist,
     * for the same reason the date does.
     */
    private function resolveView(Request $request): string
    {
        return in_array($request->query('view'), self::VIEWS, true)
            ? $request->query('view')
            : self::VIEWS[0];
    }

    /**
     * The day on screen. Anything unparseable falls back to today rather than
     * erroring — the date arrives from a URL people share and edit by hand.
     */
    private function resolveDate(Request $request): CarbonImmutable
    {
        $raw = $request->query('date');

        if (is_string($raw) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw) === 1) {
            try {
                return CarbonImmutable::createFromFormat('Y-m-d', $raw)->startOfDay();
            } catch (\Throwable) {
                // Fall through to today.
            }
        }

        return CarbonImmutable::today();
    }
}
