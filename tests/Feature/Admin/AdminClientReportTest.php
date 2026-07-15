<?php

use App\Models\PancakeUserRmoDailyReport;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

function rmoReport(Workspace $workspace, string $date, int $called, int $callTime): void
{
    PancakeUserRmoDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => (string) fake()->numberBetween(1, 9999),
        'date' => $date,
        'total_called' => $called,
        'total_call_time' => $callTime,
    ]);
}

it('renders the client report for a super admin', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.workspaces.report', $workspace))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/workspaces/report')
            ->where('workspace.slug', $workspace->slug)
            ->has('report', fn (Assert $report) => $report
                ->where('range.months', 3)
                ->has('range.start')
                ->has('range.end')
                ->has('rts_rate')
                ->has('rts_monthly')
                ->has('notifications_sent')
                ->has('rmo_called')
                ->has('call_time_seconds')
            )
        );
});

it('sums RMO calls and call time within the trailing window only', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $inRange = Carbon::today()->toDateString();
    $alsoInRange = Carbon::today()->startOfMonth()->toDateString();
    $outOfRange = Carbon::today()->subMonths(6)->toDateString();

    rmoReport($workspace, $inRange, called: 10, callTime: 600);
    rmoReport($workspace, $alsoInRange, called: 5, callTime: 300);
    rmoReport($workspace, $outOfRange, called: 999, callTime: 99999);

    $this->actingAs($admin)
        ->get(route('admin.workspaces.report', $workspace))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.rmo_called', 15)
            ->where('report.call_time_seconds', 900)
        );
});

it('blocks non-admins from the client report', function () {
    $user = User::factory()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.workspaces.report', $workspace))
        ->assertRedirect(route('dashboard'));
});
