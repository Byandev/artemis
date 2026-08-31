<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * "Leaders for the period" on the S&M dashboard. Each one picks the advertiser
 * who topped a figure over the window off the same rows the KPIs sum, and
 * answers with raw figures — every ratio the cards show (share, ROAS, AOV) is a
 * division they do, so the endpoints stay sums and an ordering.
 */
function smLeaderWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so sums cover the whole workspace. */
function smLeaderOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

/** A member holding exactly the dashboard grant, and on no team. */
function smLeaderViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

function smLeaderUrl(Workspace $workspace, array $query = [], string $leader = 'highest-ad-spend'): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/leaders/{$leader}";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** A workspace member who advertises. */
function smLeaderAdvertiser(Workspace $workspace, string $name, ?Team $team = null): User
{
    $user = User::factory()->create(['name' => $name]);
    $workspace->users()->attach($user->id, ['role' => 'member']);

    if ($team) {
        $user->teams()->attach($team);
    }

    return $user;
}

/** One day of spend, attributed sales, orders and delivery outcomes. */
function smLeaderSpend(
    Workspace $workspace,
    User $advertiser,
    string $date,
    float $spend,
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

test('it names the biggest spender and totals their days', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $leader = smLeaderAdvertiser($workspace, 'Knathan Rick Villaspina');
    $other = smLeaderAdvertiser($workspace, 'Someone Else');

    // Split across days, so the winner is decided on the window's total.
    smLeaderSpend($workspace, $leader, '2026-03-10', 600, 1800);
    smLeaderSpend($workspace, $leader, '2026-03-12', 400, 1200);
    smLeaderSpend($workspace, $other, '2026-03-10', 900, 900);

    $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertOk()
        ->assertJson([
            'advertiser' => ['id' => $leader->id, 'name' => 'Knathan Rick Villaspina'],
            'value' => 1000,
            // The card divides these two for "% of total".
            'total' => 1900,
            // And these two for the ROAS it states.
            'sales' => 3000,
        ]);
});

test('it only weighs spend inside the window', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $inWindow = smLeaderAdvertiser($workspace, 'In Window');
    $outside = smLeaderAdvertiser($workspace, 'Outside');

    smLeaderSpend($workspace, $inWindow, '2026-03-10', 100);
    // A far bigger spender, but not in this window — must not lead it.
    smLeaderSpend($workspace, $outside, '2026-03-20', 90000);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertOk();

    expect($response->json('advertiser.id'))->toBe($inWindow->id)
        ->and((float) $response->json('value'))->toBe(100.0);
});

test('there is no leader when nothing was spent', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertOk();

    expect($response->json('advertiser'))->toBeNull()
        ->and((float) $response->json('value'))->toBe(0.0)
        ->and((float) $response->json('total'))->toBe(0.0);
});

test('another workspace\'s spenders are never considered', function () use ($window) {
    $workspace = smLeaderWorkspace();
    $other = smLeaderWorkspace();

    $mine = smLeaderAdvertiser($workspace, 'Mine');
    smLeaderSpend($workspace, $mine, '2026-03-10', 100);
    smLeaderSpend($other, smLeaderAdvertiser($other, 'Theirs'), '2026-03-10', 90000);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertOk();

    expect($response->json('advertiser.id'))->toBe($mine->id)
        ->and((float) $response->json('total'))->toBe(100.0);
});

test('the workspace-wide team switcher narrows who can lead', function () use ($window) {
    $workspace = smLeaderWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $onA = smLeaderAdvertiser($workspace, 'Team A Spender', $teamA);
    $onB = smLeaderAdvertiser($workspace, 'Team B Spender', $teamB);

    smLeaderSpend($workspace, $onA, '2026-03-10', 400);
    smLeaderSpend($workspace, $onB, '2026-03-10', 900);

    $owner = smLeaderOwner($workspace);

    // No team picked → the biggest spender overall.
    $all = $this->actingAs($owner)
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertOk();

    expect($all->json('advertiser.id'))->toBe($onB->id);

    // Viewing as team A → its own leader, and its own total to divide by.
    $narrowed = $this->actingAs($owner)
        ->getJson(smLeaderUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect($narrowed->json('advertiser.id'))->toBe($onA->id)
        ->and((float) $narrowed->json('total'))->toBe(400.0);
});

test('highest sales names the advertiser credited with the most, and its orders', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $leader = smLeaderAdvertiser($workspace, 'Knathan Rick Villaspina');
    $other = smLeaderAdvertiser($workspace, 'Someone Else');

    // The sales leader is not the spend leader here — the two cards answer
    // different questions and must not be resolved off the same ordering.
    smLeaderSpend($workspace, $leader, '2026-03-10', 100, sales: 4000, orders: 8);
    smLeaderSpend($workspace, $other, '2026-03-10', 900, sales: 1000, orders: 2);

    $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'highest-sales'))
        ->assertOk()
        ->assertJson([
            'advertiser' => ['id' => $leader->id, 'name' => 'Knathan Rick Villaspina'],
            'value' => 4000,
            // Every advertiser's attributed sales, for "% of total".
            'total' => 5000,
            // The card divides the sales by this for the AOV it states.
            'orders' => 8,
        ]);
});

test('highest sales has no leader when nothing was credited', function () use ($window) {
    $workspace = smLeaderWorkspace();

    // Spend with nothing to show for it: there is a spender, but no sales
    // leader, and the card says so rather than naming one at zero.
    smLeaderSpend($workspace, smLeaderAdvertiser($workspace, 'Spent Nothing Back'), '2026-03-10', 500);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'highest-sales'))
        ->assertOk();

    expect($response->json('advertiser'))->toBeNull()
        ->and((float) $response->json('value'))->toBe(0.0);
});

test('highest roas ranks on efficiency, not on size', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $efficient = smLeaderAdvertiser($workspace, 'Rnd Krizia Kaye Dimatulac');
    $big = smLeaderAdvertiser($workspace, 'Spends The Most');

    // The big spender sells more in absolute terms but returns less per peso —
    // this card must pick the ratio, not the size.
    smLeaderSpend($workspace, $efficient, '2026-03-10', 1000, sales: 7300);
    smLeaderSpend($workspace, $big, '2026-03-10', 90000, sales: 180000);

    $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'highest-roas'))
        ->assertOk()
        ->assertJson([
            'advertiser' => ['id' => $efficient->id, 'name' => 'Rnd Krizia Kaye Dimatulac'],
            'value' => 7.3,
            // The card states both sides of the ratio underneath it.
            'ad_spend' => 1000,
            'sales' => 7300,
        ]);
});

test('highest roas ignores an advertiser who spent nothing', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $spender = smLeaderAdvertiser($workspace, 'Actually Spent');
    $freeloader = smLeaderAdvertiser($workspace, 'Spent Nothing');

    smLeaderSpend($workspace, $spender, '2026-03-10', 1000, sales: 2000);
    // Sales with no spend behind them: an infinite ratio, not efficiency.
    smLeaderSpend($workspace, $freeloader, '2026-03-10', 0, sales: 50000);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'highest-roas'))
        ->assertOk();

    expect($response->json('advertiser.id'))->toBe($spender->id)
        ->and((float) $response->json('value'))->toBe(2.0);
});

test('highest roas has no leader when nobody spent', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'highest-roas'))
        ->assertOk();

    expect($response->json('advertiser'))->toBeNull()
        ->and((float) $response->json('value'))->toBe(0.0);
});

test('lowest rts picks the best delivery rate, weighted by amount', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $best = smLeaderAdvertiser($workspace, 'Rnd Krizia Kaye Dimatulac');
    $worst = smLeaderAdvertiser($workspace, 'Sends Them Back');

    // 100 back out of 1,000 with an outcome → 0.1, against 400 of 1,000 → 0.4.
    smLeaderSpend($workspace, $best, '2026-03-10', 500, returnedAmount: 100, deliveredAmount: 900);
    smLeaderSpend($workspace, $worst, '2026-03-10', 500, returnedAmount: 400, deliveredAmount: 600);

    $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'lowest-rts'))
        ->assertOk()
        ->assertJson([
            'advertiser' => ['id' => $best->id, 'name' => 'Rnd Krizia Kaye Dimatulac'],
            'value' => 0.1,
            'returned_amount' => 100,
        ]);
});

test('lowest rts weighs a busy day more than a quiet one', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $advertiser = smLeaderAdvertiser($workspace, 'Mixed Record');

    // A perfect quiet day and a bad busy one. Averaging the two daily rates
    // would call it 24%; weighting by amount — 900 back out of 2,000 with an
    // outcome — says 45%, which is what actually happened.
    smLeaderSpend($workspace, $advertiser, '2026-03-10', 100, returnedAmount: 0, deliveredAmount: 100);
    smLeaderSpend($workspace, $advertiser, '2026-03-11', 100, returnedAmount: 900, deliveredAmount: 1000);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'lowest-rts'))
        ->assertOk();

    expect((float) $response->json('value'))->toBe(0.45);
});

test('lowest rts ignores an advertiser with no outcome either way', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $delivered = smLeaderAdvertiser($workspace, 'Has A Record');
    $nothing = smLeaderAdvertiser($workspace, 'Nothing Shipped');

    smLeaderSpend($workspace, $delivered, '2026-03-10', 500, returnedAmount: 50, deliveredAmount: 450);
    // Spend but nothing delivered and nothing returned: not a perfect record.
    smLeaderSpend($workspace, $nothing, '2026-03-10', 500);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'lowest-rts'))
        ->assertOk();

    expect($response->json('advertiser.id'))->toBe($delivered->id);
});

test('lowest rts has no leader when nothing has an outcome', function () use ($window) {
    $workspace = smLeaderWorkspace();

    smLeaderSpend($workspace, smLeaderAdvertiser($workspace, 'Spent Only'), '2026-03-10', 500);

    $response = $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $window, 'lowest-rts'))
        ->assertOk();

    expect($response->json('advertiser'))->toBeNull();
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smLeaderWorkspace();

    $this->actingAs(smLeaderOwner($workspace))
        ->getJson(smLeaderUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smLeaderWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smLeaderWorkspace();
    $viewer = smLeaderViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smLeaderUrl($workspace, $window))
        ->assertNotFound();
});

test('it needs a session', function () use ($window) {
    $this->getJson(smLeaderUrl(smLeaderWorkspace(), $window))->assertUnauthorized();
});
