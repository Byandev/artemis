<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * Team member comparison on the S&M dashboard. One row of raw sums per
 * advertiser and nothing else: the panel switches between Sales, Ad spend, ROAS
 * and RTS without refetching, so every ratio, ranking and comparison is worked
 * out client-side off these figures.
 */
function smTeamWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so every advertiser is in scope. */
function smTeamOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

/** A member holding exactly the dashboard grant. */
function smTeamViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

function smTeamUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/team-comparison";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** A workspace member who advertises. */
function smTeamAdvertiser(Workspace $workspace, string $name, ?Team $team = null): User
{
    $user = User::factory()->create(['name' => $name]);
    $workspace->users()->attach($user->id, ['role' => 'member']);

    if ($team) {
        $user->teams()->attach($team);
    }

    return $user;
}

/** One day of figures for an advertiser. */
function smTeamDay(
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

test('it returns one row of raw sums per advertiser', function () use ($window) {
    $workspace = smTeamWorkspace();

    $one = smTeamAdvertiser($workspace, 'Aaa Advertiser');
    $two = smTeamAdvertiser($workspace, 'Bbb Advertiser');

    // Two days for one advertiser, so the row has to be a sum, not a day.
    smTeamDay($workspace, $one, '2026-03-10', spend: 100, sales: 400, orders: 4, returnedAmount: 50, deliveredAmount: 350);
    smTeamDay($workspace, $one, '2026-03-11', spend: 100, sales: 200, orders: 2, returnedAmount: 50, deliveredAmount: 150);
    smTeamDay($workspace, $two, '2026-03-10', spend: 500, sales: 500, orders: 5, returnedAmount: 100, deliveredAmount: 400);

    $response = $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(2);

    $response->assertJson([
        'rows' => [
            [
                'advertiser' => ['id' => $one->id, 'name' => 'Aaa Advertiser'],
                'ad_spend' => 200,
                'sales' => 600,
                'orders' => 6,
                'returned_amount' => 100,
                'delivered_amount' => 500,
            ],
            [
                'advertiser' => ['id' => $two->id, 'name' => 'Bbb Advertiser'],
                'ad_spend' => 500,
                'sales' => 500,
            ],
        ],
    ]);
});

test('rows come back by name, not ranked — the panel does the ranking', function () use ($window) {
    $workspace = smTeamWorkspace();

    // Deliberately created biggest-first, so a size ordering would show.
    $big = smTeamAdvertiser($workspace, 'Zzz Big Spender');
    $small = smTeamAdvertiser($workspace, 'Aaa Small Spender');

    smTeamDay($workspace, $big, '2026-03-10', spend: 9000, sales: 9000);
    smTeamDay($workspace, $small, '2026-03-10', spend: 10, sales: 10);

    $response = $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk();

    expect(array_column(array_column($response->json('rows'), 'advertiser'), 'name'))
        ->toBe(['Aaa Small Spender', 'Zzz Big Spender']);
});

test('it carries every column the four metrics are derived from', function () use ($window) {
    $workspace = smTeamWorkspace();

    smTeamDay(
        $workspace,
        smTeamAdvertiser($workspace, 'Complete Row'),
        '2026-03-10',
        spend: 250,
        sales: 1000,
        orders: 10,
        returnedAmount: 200,
        deliveredAmount: 800,
    );

    $row = $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk()
        ->json('rows.0');

    // Sales and Ad spend read straight off; ROAS is 1000/250 and RTS is
    // 200/(200+800) once the panel divides them.
    expect($row)->toHaveKeys([
        'advertiser', 'ad_spend', 'sales', 'orders', 'returned_amount', 'delivered_amount',
    ]);
    // JSON hands back ints where the decimal is .0, so compare as floats.
    expect((float) $row['sales'] / (float) $row['ad_spend'])->toBe(4.0)
        ->and((float) $row['returned_amount'] / ((float) $row['returned_amount'] + (float) $row['delivered_amount']))->toBe(0.2);
});

test('it only sums days inside the window', function () use ($window) {
    $workspace = smTeamWorkspace();

    $advertiser = smTeamAdvertiser($workspace, 'In And Out');

    smTeamDay($workspace, $advertiser, '2026-03-10', spend: 100, sales: 300);
    smTeamDay($workspace, $advertiser, '2026-03-20', spend: 90000, sales: 90000);

    $response = $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('rows.0.ad_spend'))->toBe(100.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(300.0);
});

test('another workspace\'s advertisers never appear', function () use ($window) {
    $workspace = smTeamWorkspace();
    $other = smTeamWorkspace();

    smTeamDay($workspace, smTeamAdvertiser($workspace, 'Mine'), '2026-03-10', spend: 100);
    smTeamDay($other, smTeamAdvertiser($other, 'Theirs'), '2026-03-10', spend: 900);

    $response = $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.advertiser.name'))->toBe('Mine');
});

test('the workspace-wide team switcher narrows who is compared', function () use ($window) {
    $workspace = smTeamWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $onA = smTeamAdvertiser($workspace, 'On Team A', $teamA);
    $onB = smTeamAdvertiser($workspace, 'On Team B', $teamB);

    smTeamDay($workspace, $onA, '2026-03-10', spend: 100);
    smTeamDay($workspace, $onB, '2026-03-10', spend: 200);

    $owner = smTeamOwner($workspace);

    $all = $this->actingAs($owner)
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk();

    expect($all->json('rows'))->toHaveCount(2);

    $narrowed = $this->actingAs($owner)
        ->getJson(smTeamUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect($narrowed->json('rows'))->toHaveCount(1)
        ->and($narrowed->json('rows.0.advertiser.id'))->toBe($onA->id);
});

test('an empty window returns no rows rather than failing', function () use ($window) {
    $workspace = smTeamWorkspace();

    $response = $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toBe([]);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smTeamWorkspace();

    $this->actingAs(smTeamOwner($workspace))
        ->getJson(smTeamUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smTeamWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smTeamUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smTeamWorkspace();
    $viewer = smTeamViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smTeamUrl($workspace, $window))
        ->assertNotFound();
});

test('it needs a session', function () use ($window) {
    $this->getJson(smTeamUrl(smTeamWorkspace(), $window))->assertUnauthorized();
});
