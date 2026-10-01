<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * Total Ad Spend KPI on the S&M dashboard. It sums the rows the group's own Ad
 * Spent Summary sums — advertiser_performance_daily_records — so the dashboard
 * and that page report the same spend for the same window.
 *
 * These pin the sum, the window, advertiser visibility (which is how the
 * workspace-wide "viewing as team" switcher reaches this figure), and that the
 * endpoint is behind the same module switch and permission as the page.
 */
function smAdSpendWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so sums cover the whole workspace. */
function smAdSpendOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

/** A member holding exactly the dashboard grant, and on no team. */
function smAdSpendViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

function smAdSpendUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/kpi/total-ad-spend";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** One day of spend for an advertiser (the Artemis source: advertiser = user). */
function smAdSpend(Workspace $workspace, User $advertiser, string $date, float $spend): void
{
    AdvertiserPerformanceDailyRecord::create([
        'workspace_id' => $workspace->id,
        'source' => AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS,
        'advertiser_model' => 'user',
        'advertiser_id' => $advertiser->id,
        'advertiser_name' => $advertiser->name,
        'date' => $date,
        'ad_spent' => $spend,
    ]);
}

test('it sums only the selected window', function () {
    $workspace = smAdSpendWorkspace();
    $advertiser = smAdSpendOwner($workspace);

    smAdSpend($workspace, $advertiser, '2026-03-10', 1000);
    smAdSpend($workspace, $advertiser, '2026-03-14', 250);
    // Either side of the window, so neither may be counted.
    smAdSpend($workspace, $advertiser, '2026-03-05', 4000);
    smAdSpend($workspace, $advertiser, '2026-03-20', 9000);

    $this->actingAs($advertiser)
        ->getJson(smAdSpendUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk()
        ->assertJson(['value' => 1250]);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smAdSpendWorkspace();

    $this->actingAs(smAdSpendOwner($workspace))
        ->getJson(smAdSpendUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('another workspace\'s spend is never counted', function () {
    $workspace = smAdSpendWorkspace();
    $other = smAdSpendWorkspace();

    $owner = smAdSpendOwner($workspace);
    smAdSpend($workspace, $owner, '2026-03-10', 100);
    smAdSpend($other, smAdSpendOwner($other), '2026-03-10', 5000);

    $response = $this->actingAs($owner)
        ->getJson(smAdSpendUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(100.0);
});

test('the workspace-wide team switcher narrows the figure', function () {
    $workspace = smAdSpendWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    // Advertisers are workspace users; visibility follows the team they are on.
    $advertiserFor = function (Team $team) use ($workspace): User {
        $user = User::factory()->create();
        $workspace->users()->attach($user->id, ['role' => 'member']);
        $user->teams()->attach($team);

        return $user;
    };

    smAdSpend($workspace, $advertiserFor($teamA), '2026-03-10', 700);
    smAdSpend($workspace, $advertiserFor($teamB), '2026-03-10', 300);

    $owner = smAdSpendOwner($workspace);
    $window = ['start' => '2026-03-08', 'end' => '2026-03-14'];

    // Unrestricted and no team picked → both teams' spend.
    $all = $this->actingAs($owner)
        ->getJson(smAdSpendUrl($workspace, $window))
        ->assertOk();

    expect((float) $all->json('value'))->toBe(1000.0);

    // The switcher drives narrowing with ?team_id=, as it does app-wide.
    $narrowed = $this->actingAs($owner)
        ->getJson(smAdSpendUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect((float) $narrowed->json('value'))->toBe(700.0);
});

test('a scoped viewer on no team sees nothing rather than everything', function () {
    $workspace = smAdSpendWorkspace();

    smAdSpend($workspace, smAdSpendOwner($workspace), '2026-03-10', 800);

    // Scoped (no "View All Workspace Data") and on no team: fail closed.
    $viewer = smAdSpendViewer($workspace);

    $response = $this->actingAs($viewer)
        ->getJson(smAdSpendUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(0.0);
});

test('it is refused without the dashboard permission', function () {
    $workspace = smAdSpendWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smAdSpendUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () {
    $workspace = smAdSpendWorkspace();
    $viewer = smAdSpendViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smAdSpendUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertNotFound();
});

test('it needs a session', function () {
    $workspace = smAdSpendWorkspace();

    $this->getJson(smAdSpendUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertUnauthorized();
});
