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
 * RTS Rate KPI on the S&M dashboard. It resolves the `rtsRate` metric — the same
 * one behind the main dashboard's card of that name — as a 0–1 ratio, and
 * carries the money behind it: the pesos that entered the return journey over
 * the same window, which is the rate's own numerator.
 */
function smRtsWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so sums cover the whole workspace. */
function smRtsOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

/** A member holding exactly the dashboard grant. */
function smRtsViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

function smRtsUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/kpi/rts-rate";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** An order that came back: it entered returning on $date. */
function smRtsReturning(Workspace $workspace, string $date, float $amount, ?Page $page = null): void
{
    Order::factory()->forPage($page ?? Page::factory()->forWorkspace($workspace)->create())->create([
        'workspace_id' => $workspace->id,
        'status' => 1,
        'final_amount' => $amount,
        'returning_at' => $date.' 12:00:00',
        'delivered_at' => null,
    ]);
}

/** An order that landed: it was delivered on $date. */
function smRtsDelivered(Workspace $workspace, string $date, float $amount, ?Page $page = null): void
{
    Order::factory()->forPage($page ?? Page::factory()->forWorkspace($workspace)->create())->create([
        'workspace_id' => $workspace->id,
        'status' => 1,
        'final_amount' => $amount,
        'returning_at' => null,
        'delivered_at' => $date.' 12:00:00',
    ]);
}

$window = ['start' => '2026-03-08', 'end' => '2026-03-14'];

test('it reports the returned share of everything with an outcome', function () use ($window) {
    $workspace = smRtsWorkspace();

    // 2,000 back out of 10,000 with an outcome → 0.2.
    smRtsReturning($workspace, '2026-03-10', 2000);
    smRtsDelivered($workspace, '2026-03-10', 8000);

    $this->actingAs(smRtsOwner($workspace))
        ->getJson(smRtsUrl($workspace, $window))
        ->assertOk()
        ->assertJson([
            'value' => 0.2,
            'returning_amount' => 2000,
        ]);
});

test('it is zero when nothing came back', function () use ($window) {
    $workspace = smRtsWorkspace();

    smRtsDelivered($workspace, '2026-03-10', 5000);

    $response = $this->actingAs(smRtsOwner($workspace))
        ->getJson(smRtsUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(0.0)
        ->and((float) $response->json('returning_amount'))->toBe(0.0);
});

test('it only counts outcomes inside the window', function () use ($window) {
    $workspace = smRtsWorkspace();

    smRtsReturning($workspace, '2026-03-10', 1000);
    smRtsDelivered($workspace, '2026-03-10', 1000);
    // Either side of the window — neither may move the rate.
    smRtsReturning($workspace, '2026-03-01', 90000);
    smRtsDelivered($workspace, '2026-03-20', 90000);

    $response = $this->actingAs(smRtsOwner($workspace))
        ->getJson(smRtsUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(0.5)
        ->and((float) $response->json('returning_amount'))->toBe(1000.0);
});

test('another workspace\'s returns are never counted', function () use ($window) {
    $workspace = smRtsWorkspace();
    $other = smRtsWorkspace();

    smRtsReturning($workspace, '2026-03-10', 1000);
    smRtsDelivered($workspace, '2026-03-10', 3000);
    smRtsReturning($other, '2026-03-10', 90000);

    $response = $this->actingAs(smRtsOwner($workspace))
        ->getJson(smRtsUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('returning_amount'))->toBe(1000.0);
});

test('the workspace-wide team switcher narrows the figure', function () use ($window) {
    $workspace = smRtsWorkspace();
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

    $pageA = $pageFor($teamA);
    $pageB = $pageFor($teamB);

    smRtsReturning($workspace, '2026-03-10', 600, $pageA);
    smRtsDelivered($workspace, '2026-03-10', 400, $pageA);
    smRtsReturning($workspace, '2026-03-10', 100, $pageB);
    smRtsDelivered($workspace, '2026-03-10', 900, $pageB);

    $owner = smRtsOwner($workspace);

    // Unrestricted and no team picked → both teams: 700 of 2,000.
    $all = $this->actingAs($owner)
        ->getJson(smRtsUrl($workspace, $window))
        ->assertOk();

    expect((float) $all->json('returning_amount'))->toBe(700.0)
        ->and((float) $all->json('value'))->toBe(0.35);

    // Team A alone: 600 of 1,000.
    $narrowed = $this->actingAs($owner)
        ->getJson(smRtsUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect((float) $narrowed->json('returning_amount'))->toBe(600.0)
        ->and((float) $narrowed->json('value'))->toBe(0.6);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smRtsWorkspace();

    $this->actingAs(smRtsOwner($workspace))
        ->getJson(smRtsUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smRtsWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smRtsUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smRtsWorkspace();
    $viewer = smRtsViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smRtsUrl($workspace, $window))
        ->assertNotFound();
});

test('it needs a session', function () use ($window) {
    $this->getJson(smRtsUrl(smRtsWorkspace(), $window))->assertUnauthorized();
});
