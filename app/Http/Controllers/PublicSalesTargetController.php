<?php

namespace App\Http\Controllers;

use App\Enums\Permission;
use App\Models\SalesTarget;
use App\Models\Workspace;
use App\Queries\SalesTargetScoreboardQuery;
use App\Support\PublicWorkspaceGate;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * The public sales gameboard, behind the shared public-pages password (same
 * mechanism as the public RMO page and leaderboard).
 *
 * One day at a time: the KPI row scores the day's target — today's if the
 * workspace set one, otherwise the most recent — against what the teams
 * actually did. No history list; a wall display shows the day it is on.
 *
 * Deliberately view-only: no create, edit or delete. Managing targets stays on
 * the authenticated S&M dashboard tab, where permissions and team visibility
 * apply. This page also ignores team scoping — it is the workspace-wide board,
 * and anyone holding the password sees every team's number.
 */
class PublicSalesTargetController extends Controller
{
    public function index(Request $request, Workspace $workspace)
    {
        if (! PublicWorkspaceGate::isUnlocked($request, $workspace, Permission::ViewSalesMarketingDashboard)) {
            return Inertia::render('workspaces/sales-targets/public-pages/sales-targets', [
                'workspace' => $workspace->only('id', 'name', 'slug'),
                'locked' => true,
            ]);
        }

        $featured = $this->featuredTarget($workspace);

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
                // The cast keeps this a Carbon instance, and the frontend parses
                // a bare Y-m-d as a local date — send it in that shape.
                'date' => $featured->date->toDateString(),
            ] : null,
            'teams' => $boardTeams,
            'teamId' => $teamId,
            'kpis' => $featured
                ? (new SalesTargetScoreboardQuery($workspace))->kpisFor($featured, $teamId)
                : null,
        ]);
    }

    /**
     * The target the board is pointed at: today's if the workspace set one,
     * otherwise the most recent. The KPI row is scored against this one day.
     */
    private function featuredTarget(Workspace $workspace): ?SalesTarget
    {
        $base = fn () => SalesTarget::ofWorkspace($workspace)->with(['teamTargets.team:id,name']);

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
