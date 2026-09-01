<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Order;
use App\Models\Page;
use App\Models\Role;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * Total Sales KPI on the S&M dashboard. It resolves the `totalSales` metric —
 * the same one behind the main dashboard's stat cards, so both dashboards report
 * the same figure for the same window.
 *
 * The window comes from the page; team narrowing comes from the workspace-wide
 * "viewing as team" switcher. These pin the sum, the window it is taken over,
 * both kinds of narrowing, and that the endpoint is behind the same module
 * switch and permission as the page that renders it.
 */
function smKpiWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/**
 * The workspace owner — unrestricted, so the sums below are the workspace's
 * whole figure rather than one team's slice. Team scoping has its own test.
 */
function smKpiOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

/** A member holding exactly the dashboard grant. */
function smKpiViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

/**
 * A confirmed order of $sales on $date. `totalSales` sums `final_amount` over
 * `confirmed_at`, ignoring the two cancelled/returned statuses, so that is what
 * these fixtures have to be.
 */
function smKpiSales(Workspace $workspace, Page $page, string $date, float $sales): void
{
    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'status' => 1,
        'final_amount' => $sales,
        'confirmed_at' => $date.' 12:00:00',
    ]);
}

function smKpiUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/kpi/total-sales";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

test('it sums only the selected window', function () {
    $workspace = smKpiWorkspace();
    $page = Page::factory()->forWorkspace($workspace)->create();

    smKpiSales($workspace, $page, '2026-03-10', 1000);
    smKpiSales($workspace, $page, '2026-03-14', 3000);
    // Either side of the window, so neither may be counted.
    smKpiSales($workspace, $page, '2026-03-05', 2000);
    smKpiSales($workspace, $page, '2026-03-20', 999999);

    $this->actingAs(smKpiOwner($workspace))
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk()
        ->assertJson(['value' => 4000]);
});

test('it counts the whole day at both ends of the window', function () {
    $workspace = smKpiWorkspace();
    $page = Page::factory()->forWorkspace($workspace)->create();

    Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'status' => 1,
        'final_amount' => 250,
        'confirmed_at' => '2026-03-14 23:30:00',
    ]);

    $response = $this->actingAs(smKpiOwner($workspace))
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(250.0);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smKpiWorkspace();

    $this->actingAs(smKpiOwner($workspace))
        ->getJson(smKpiUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('cancelled and returned orders are left out, as the metric defines it', function () {
    $workspace = smKpiWorkspace();
    $page = Page::factory()->forWorkspace($workspace)->create();

    smKpiSales($workspace, $page, '2026-03-10', 400);

    // Statuses 6 and 7 are the two the metric excludes; an order in one of them
    // must not reach the total even though it is confirmed and in the window.
    foreach ([6, 7] as $status) {
        Order::factory()->forPage($page)->create([
            'workspace_id' => $workspace->id,
            'status' => $status,
            'final_amount' => 10000,
            'confirmed_at' => '2026-03-10 12:00:00',
        ]);
    }

    $response = $this->actingAs(smKpiOwner($workspace))
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(400.0);
});

test('another workspace\'s sales are never counted', function () {
    $workspace = smKpiWorkspace();
    $other = smKpiWorkspace();

    smKpiSales($workspace, Page::factory()->forWorkspace($workspace)->create(), '2026-03-10', 100);
    smKpiSales($other, Page::factory()->forWorkspace($other)->create(), '2026-03-10', 5000);

    $response = $this->actingAs(smKpiOwner($workspace))
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(100.0);
});

test('the workspace-wide team switcher narrows the figure', function () {
    $workspace = smKpiWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    // Team visibility flows through the shop, so each page hangs off a shop
    // assigned to one team.
    $pageFor = function (Team $team) use ($workspace): Page {
        $shop = Shop::factory()->forWorkspace($workspace)->create();
        $shop->teams()->attach($team->id);

        return Page::factory()->create([
            'workspace_id' => $workspace->id,
            'shop_id' => $shop->id,
        ]);
    };

    smKpiSales($workspace, $pageFor($teamA), '2026-03-10', 600);
    smKpiSales($workspace, $pageFor($teamB), '2026-03-10', 900);

    // The owner is unrestricted, so without the switcher they see both teams.
    $owner = smKpiOwner($workspace);
    $window = ['start' => '2026-03-08', 'end' => '2026-03-14'];

    $all = $this->actingAs($owner)
        ->getJson(smKpiUrl($workspace, $window))
        ->assertOk();

    expect((float) $all->json('value'))->toBe(1500.0);

    // Switching to team A narrows the figure to that team's pages — the
    // switcher drives it with ?team_id=, exactly as the rest of the app does.
    $narrowed = $this->actingAs($owner)
        ->getJson(smKpiUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect((float) $narrowed->json('value'))->toBe(600.0);
});

test('a scoped viewer only counts their own team\'s pages', function () {
    $workspace = smKpiWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $pageFor = function (Team $team) use ($workspace): Page {
        $shop = Shop::factory()->forWorkspace($workspace)->create();
        $shop->teams()->attach($team->id);

        return Page::factory()->create([
            'workspace_id' => $workspace->id,
            'shop_id' => $shop->id,
        ]);
    };

    smKpiSales($workspace, $pageFor($teamA), '2026-03-10', 800);
    smKpiSales($workspace, $pageFor($teamB), '2026-03-10', 200);

    // A scoped member of team A holding only the dashboard grant.
    $user = smKpiViewer($workspace);
    $user->teams()->attach($teamA);

    $response = $this->actingAs($user)
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(800.0);
});

test('it is refused without the dashboard permission', function () {
    $workspace = smKpiWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () {
    $workspace = smKpiWorkspace();
    $viewer = smKpiViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertNotFound();
});

test('it needs a session', function () {
    $workspace = smKpiWorkspace();

    $this->getJson(smKpiUrl($workspace, ['start' => '2026-03-08', 'end' => '2026-03-14']))
        ->assertUnauthorized();
});
