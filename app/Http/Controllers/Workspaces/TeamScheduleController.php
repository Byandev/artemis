<?php

namespace App\Http\Controllers\Workspaces;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Team;
use App\Models\TeamMemberSchedule;
use App\Models\Workspace;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class TeamScheduleController extends Controller
{
    use AuthorizesRequests;

    public function index(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::ViewTeams->value, $workspace);

        if ($team->workspace_id !== $workspace->id) {
            abort(403);
        }

        $weekStart = $request->query('week')
            ? Carbon::parse($request->query('week'))->startOfWeek(Carbon::MONDAY)
            : Carbon::now()->startOfWeek(Carbon::MONDAY);

        $weekEnd = $weekStart->copy()->addDays(6);

        $team->load(['members:id,name,email']);

        $schedules = TeamMemberSchedule::where('team_id', $team->id)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();

        return Inertia::render('workspaces/teams/schedule', [
            'workspace' => $workspace,
            'team' => $team,
            'schedules' => $schedules,
            'weekStart' => $weekStart->toDateString(),
        ]);
    }

    public function update(Request $request, Workspace $workspace, Team $team)
    {
        $this->authorize(Permission::ManageSchedule->value, $workspace);

        if ($team->workspace_id !== $workspace->id) {
            abort(403);
        }

        $validated = $request->validate([
            'week_start' => ['required', 'date'],
            'schedules' => ['required', 'array'],
            'schedules.*.user_id' => ['required', 'exists:users,id'],
            'schedules.*.date' => ['required', 'date'],
            'schedules.*.start_time' => ['nullable', 'date_format:H:i'],
            'schedules.*.end_time' => ['nullable', 'date_format:H:i'],
        ]);

        $memberIds = $team->members()->pluck('users.id')->toArray();
        $now = now();
        $rows = [];
        $dates = [];

        foreach ($validated['schedules'] as $entry) {
            if (! in_array($entry['user_id'], $memberIds)) {
                continue;
            }

            $dates[] = $entry['date'];
            $rows[] = [
                'team_id' => $team->id,
                'user_id' => $entry['user_id'],
                'date' => $entry['date'],
                'start_time' => $entry['start_time'],
                'end_time' => $entry['end_time'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if (! empty($rows)) {
            // Delete existing schedules for submitted dates then re-insert
            TeamMemberSchedule::where('team_id', $team->id)
                ->whereIn('date', array_unique($dates))
                ->delete();

            TeamMemberSchedule::insert($rows);
        }

        return redirect()->back()->with('success', 'Schedule updated successfully.');
    }
}
