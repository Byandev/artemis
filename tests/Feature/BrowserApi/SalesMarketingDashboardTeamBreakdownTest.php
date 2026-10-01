<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * Breakdown per team member. It answers with the same per-advertiser rows the
 * comparison chart plots — deliberately the same query behind its own endpoint,
 * so the table loads and refreshes on its own without the two being able to
 * disagree about a figure.
 *
 * Still only sums: the ROAS and RTS columns, and the whole sub-total row, are
 * divisions the table does client-side.
 */
function smBreakdownWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

function smBreakdownOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

function smBreakdownViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

function smBreakdownUrl(Workspace $workspace, array $query = [], string $panel = 'team-breakdown'): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/{$panel}";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

function smBreakdownAdvertiser(Workspace $workspace, string $name, ?Team $team = null): User
{
    $user = User::factory()->create(['name' => $name]);
    $workspace->users()->attach($user->id, ['role' => 'member']);

    if ($team) {
        $user->teams()->attach($team);
    }

    return $user;
}

function smBreakdownDay(
    Workspace $workspace,
    User $advertiser,
    string $date,
    float $spend = 0,
    float $sales = 0,
    int $orders = 0,
    float $returnedAmount = 0,
    float $deliveredAmount = 0,
): void {
    AdvertiserPerformanceDailyRecord::create([
        'workspace_id' => $workspace->id,
        'source' => AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS,
        'advertiser_model' => 'user',
        'advertiser_id' => $advertiser->id,
        'advertiser_name' => $advertiser->name,
        'date' => $date,
        'ad_spent' => $spend,
        'sales' => $sales,
        'orders' => $orders,
        'returned_amount' => $returnedAmount,
        'delivered_amount' => $deliveredAmount,
    ]);
}

$window = ['start' => '2026-03-08', 'end' => '2026-03-14'];

test('it carries every column the table states, summed over the window', function () use ($window) {
    $workspace = smBreakdownWorkspace();

    $advertiser = smBreakdownAdvertiser($workspace, 'Knathan Rick Villaspina');

    smBreakdownDay($workspace, $advertiser, '2026-03-10', spend: 400, sales: 1200, orders: 8, returnedAmount: 200, deliveredAmount: 800);
    smBreakdownDay($workspace, $advertiser, '2026-03-11', spend: 100, sales: 300, orders: 2, returnedAmount: 50, deliveredAmount: 200);
    // Outside the window.
    smBreakdownDay($workspace, $advertiser, '2026-03-20', spend: 9000, sales: 9000, orders: 99);

    $this->actingAs(smBreakdownOwner($workspace))
        ->getJson(smBreakdownUrl($workspace, $window))
        ->assertOk()
        ->assertJson([
            'rows' => [
                [
                    'advertiser' => ['id' => $advertiser->id, 'name' => 'Knathan Rick Villaspina'],
                    'ad_spend' => 500,
                    'sales' => 1500,
                    'orders' => 10,
                    'returned_amount' => 250,
                    'delivered_amount' => 1000,
                ],
            ],
        ]);
});

test('it answers exactly what the comparison chart is given', function () use ($window) {
    $workspace = smBreakdownWorkspace();

    smBreakdownDay(
        $workspace,
        smBreakdownAdvertiser($workspace, 'Same Either Way'),
        '2026-03-10',
        spend: 250,
        sales: 1000,
        orders: 10,
        returnedAmount: 200,
        deliveredAmount: 800,
    );

    $owner = smBreakdownOwner($workspace);

    $breakdown = $this->actingAs($owner)
        ->getJson(smBreakdownUrl($workspace, $window))
        ->assertOk()
        ->json('rows');

    $comparison = $this->actingAs($owner)
        ->getJson(smBreakdownUrl($workspace, $window, 'team-comparison'))
        ->assertOk()
        ->json('rows');

    // Two endpoints, one query — a divergence here is the table and the chart
    // starting to tell different stories about the same period.
    expect($breakdown)->toBe($comparison);
});

test('the sub-total the table shows is derivable from the rows', function () use ($window) {
    $workspace = smBreakdownWorkspace();

    $big = smBreakdownAdvertiser($workspace, 'Big');
    $small = smBreakdownAdvertiser($workspace, 'Small');

    // Deliberately lopsided, so blending and averaging give different answers:
    // ROAS 4.0 vs 3.0 and RTS 20% vs 10%, on very different volumes.
    smBreakdownDay($workspace, $big, '2026-03-10', spend: 1000, sales: 4000, orders: 40, returnedAmount: 800, deliveredAmount: 3200);
    smBreakdownDay($workspace, $small, '2026-03-10', spend: 100, sales: 300, orders: 3, returnedAmount: 30, deliveredAmount: 270);

    $rows = $this->actingAs(smBreakdownOwner($workspace))
        ->getJson(smBreakdownUrl($workspace, $window))
        ->assertOk()
        ->json('rows');

    $sales = (float) array_sum(array_column($rows, 'sales'));
    $spend = (float) array_sum(array_column($rows, 'ad_spend'));
    $returned = (float) array_sum(array_column($rows, 'returned_amount'));
    $outcomes = $returned + (float) array_sum(array_column($rows, 'delivered_amount'));

    // Blended, the way the sub-total row computes it. Averaging the members'
    // own ratios would say ROAS 3.50 and RTS 15% — both wrong, because the
    // smaller member would count as much as the one with 40 of the 43 orders.
    expect(array_sum(array_column($rows, 'orders')))->toBe(43)
        ->and(round($sales / $spend, 4))->toBe(3.9091)
        ->and(round($returned / $outcomes, 4))->toBe(0.193);
});

test('another workspace\'s members never appear', function () use ($window) {
    $workspace = smBreakdownWorkspace();
    $other = smBreakdownWorkspace();

    smBreakdownDay($workspace, smBreakdownAdvertiser($workspace, 'Mine'), '2026-03-10', spend: 100);
    smBreakdownDay($other, smBreakdownAdvertiser($other, 'Theirs'), '2026-03-10', spend: 900);

    $response = $this->actingAs(smBreakdownOwner($workspace))
        ->getJson(smBreakdownUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.advertiser.name'))->toBe('Mine');
});

test('the workspace-wide team switcher narrows the table', function () use ($window) {
    $workspace = smBreakdownWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $onA = smBreakdownAdvertiser($workspace, 'On Team A', $teamA);
    smBreakdownDay($workspace, $onA, '2026-03-10', spend: 100);
    smBreakdownDay($workspace, smBreakdownAdvertiser($workspace, 'On Team B', $teamB), '2026-03-10', spend: 200);

    $narrowed = $this->actingAs(smBreakdownOwner($workspace))
        ->getJson(smBreakdownUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect($narrowed->json('rows'))->toHaveCount(1)
        ->and($narrowed->json('rows.0.advertiser.id'))->toBe($onA->id);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smBreakdownWorkspace();

    $this->actingAs(smBreakdownOwner($workspace))
        ->getJson(smBreakdownUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smBreakdownWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smBreakdownUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smBreakdownWorkspace();
    $viewer = smBreakdownViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smBreakdownUrl($workspace, $window))
        ->assertNotFound();
});

test('it needs a session', function () use ($window) {
    $this->getJson(smBreakdownUrl(smBreakdownWorkspace(), $window))->assertUnauthorized();
});
