<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\SalesTarget;
use App\Models\Workspace;
use App\Queries\SalesTargetScoreboardQuery;
use App\Support\PublicWorkspaceGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The public sales gameboard, behind the shared public-pages password (same
 * mechanism as the public RMO page and leaderboard).
 *
 * One day at a time: the board scores the day's target — today's if the workspace
 * set one, otherwise the most recent — against what the teams actually did. No
 * history list; a wall display shows the day it is on.
 *
 * Adding the target's id to the path pins the board to that one target instead,
 * which is how a target's detail page links out to its own board — four targets,
 * four links, each scored on its own day. Without an id the board keeps rolling
 * to today.
 *
 * These endpoints return measured numbers only. Percentages, ROAS, ranking, the
 * achievement bands and the leaderboard slice are derived in the browser from the
 * same payload, so the board makes two requests instead of six and each rule has
 * one definition. See resources/js/components/sales-targets/derive.ts.
 *
 * Deliberately view-only: no create, edit or delete. Managing targets stays on
 * the authenticated S&M dashboard tab, where permissions and team visibility
 * apply. This page also ignores team scoping — it is the workspace-wide board,
 * and anyone holding the password sees every team's number.
 */
class PublicSalesTargetController extends Controller
{
    public function index(Request $request, Workspace $workspace, ?int $salesTarget = null)
    {
        if (! PublicWorkspaceGate::isUnlocked($request, $workspace, Permission::ViewSalesMarketingDashboard)) {
            return Inertia::render('workspaces/sales-targets/public-pages/sales-targets', [
                'workspace' => $workspace->only('id', 'name', 'slug'),
                'locked' => true,
            ]);
        }

        $featured = $this->featuredTarget($workspace, $salesTarget);

        // The teams to choose between are the ones on the board's own day; a
        // team id that isn't one of them falls back to the whole board.
        $boardTeams = $featured
            ? $featured->teamTargets
                ->map(fn ($row) => [
                    'id' => (int) $row->team_id,
                    'name' => $row->team?->name ?? 'Unknown team',
                ])
                ->unique('id')
                ->sortBy('name')
                ->values()
            : collect();

        $teamId = $request->integer('team_id') ?: null;

        if ($teamId !== null && ! $boardTeams->contains('id', $teamId)) {
            $teamId = null;
        }

        return Inertia::render('workspaces/sales-targets/public-pages/sales-targets', [
            'workspace' => $workspace->only('id', 'name', 'slug'),
            'locked' => false,
            'featured' => $featured ? [
                'id' => $featured->id,
                'name' => $featured->name,
                'date' => $featured->date->toDateString(),
            ] : null,
            'teams' => $boardTeams,
            'teamId' => $teamId,
            // Only a target pinned by the path is echoed back. Null means the
            // sections keep resolving the day themselves, so a board left up
            // overnight rolls onto tomorrow's target on its next refresh.
            'targetId' => $featured && $salesTarget === $featured->id ? $featured->id : null,
        ]);
    }

    /** The day's measured totals, and the previous day's for the trend arrows. */
    public function kpis(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->section(
            $request,
            $workspace,
            fn (SalesTargetScoreboardQuery $query, SalesTarget $target, ?int $teamId) => $query->factsFor($target, $teamId),
        );
    }

    /** Each included team's goal, budget and actual — unscored. */
    public function teams(Request $request, Workspace $workspace): JsonResponse
    {
        return $this->section(
            $request,
            $workspace,
            fn (SalesTargetScoreboardQuery $query, SalesTarget $target, ?int $teamId) => $query->teamRows($target, $teamId),
        );
    }

    /**
     * One team's recent daily sales for the leader sparkline. The board works out
     * who leads and asks for that team; only the window needs the database.
     */
    public function teamTrend(Request $request, Workspace $workspace): JsonResponse
    {
        $teamId = $request->integer('team_id') ?: null;

        return $this->section($request, $workspace, function (SalesTargetScoreboardQuery $query, SalesTarget $target) use ($teamId) {
            if ($teamId === null || ! $target->teamTargets->contains('team_id', $teamId)) {
                return null;
            }

            return $query->teamSalesTrend($teamId, $target->date->toDateString());
        });
    }

    /**
     * Shared shell for the section endpoints: same password gate, same target,
     * same team filter — only the slice of data differs, so a section can load,
     * fail and refresh on its own.
     */
    private function section(Request $request, Workspace $workspace, callable $resolve): JsonResponse
    {
        if (! PublicWorkspaceGate::isUnlocked($request, $workspace, Permission::ViewSalesMarketingDashboard)) {
            abort(403, 'This board is locked.');
        }

        $target = $this->featuredTarget($workspace, $request->integer('id') ?: null);

        if (! $target) {
            return response()->json(['data' => null]);
        }

        $teamId = $request->integer('team_id') ?: null;

        // A team that isn't on the board's day scores nothing, so ignore it
        // rather than returning an empty section.
        if ($teamId !== null && ! $target->teamTargets->contains('team_id', $teamId)) {
            $teamId = null;
        }

        return response()->json([
            'data' => $resolve(new SalesTargetScoreboardQuery($workspace), $target, $teamId),
        ]);
    }

    /**
     * The target the board is pointed at: the one asked for by id, else today's
     * if the workspace set one, else the most recent.
     *
     * An id that isn't this workspace's falls through to the usual day rather
     * than erroring — a stale link shows the current board, not a 404.
     */
    private function featuredTarget(Workspace $workspace, ?int $targetId = null): ?SalesTarget
    {
        $base = fn () => SalesTarget::ofWorkspace($workspace)->with(['teamTargets.team:id,name']);

        if ($targetId !== null && $pinned = $base()->whereKey($targetId)->first()) {
            return $pinned;
        }

        return $base()->where('date', now()->toDateString())->first()
            ?? $base()->orderByDesc('date')->orderByDesc('id')->first();
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
