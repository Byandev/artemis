<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Order;
use App\Models\Page;
use App\Models\Role;
use App\Models\User;
use App\Models\Workspace;

/**
 * Blended ROAS KPI on the S&M dashboard: the same sales and ad spend the two
 * cards beside it report, divided — so the ratio always matches the figures
 * shown next to it — carrying the attributed ROAS alongside it, which divides
 * the sales the ad platform credits to the ads by that same spend.
 */
function smRoasWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so sums cover the whole workspace. */
function smRoasOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

/** A member holding exactly the dashboard grant. */
function smRoasViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );
}

function smRoasUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/kpi/blended-roas";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** A confirmed order, the numerator's fixture. */
function smRoasSales(Workspace $workspace, string $date, float $sales): void
{
    Order::factory()->forPage(Page::factory()->forWorkspace($workspace)->create())->create([
        'workspace_id' => $workspace->id,
        'status' => 1,
        'final_amount' => $sales,
        'confirmed_at' => $date.' 12:00:00',
    ]);
}

/**
 * A day of ad spend and the sales the platform attributes to it — the
 * denominator, and the attributed ROAS's numerator.
 */
function smRoasSpend(Workspace $workspace, string $date, float $spend, float $attributedSales = 0): void
{
    $advertiser = smRoasOwner($workspace);

    AdvertiserPerformanceDailyRecord::create([
        'workspace_id' => $workspace->id,
        'source' => AdvertiserPerformanceDailyRecord::SOURCE_ARTEMIS,
        'advertiser_model' => 'user',
        'advertiser_id' => $advertiser->id,
        'advertiser_name' => $advertiser->name,
        'date' => $date,
        'ad_spent' => $spend,
        'sales' => $attributedSales,
    ]);
}

$window = ['start' => '2026-03-08', 'end' => '2026-03-14'];

test('it divides the window\'s sales by its ad spend', function () use ($window) {
    $workspace = smRoasWorkspace();

    smRoasSales($workspace, '2026-03-10', 3000);
    smRoasSpend($workspace, '2026-03-10', 1000);
    // Outside the window, so neither side may count it.
    smRoasSales($workspace, '2026-03-20', 99999);
    smRoasSpend($workspace, '2026-03-20', 99999);

    $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $window))
        ->assertOk()
        ->assertJson(['value' => 3.0]);
});

test('it is null when there was no spend, rather than zero', function () use ($window) {
    $workspace = smRoasWorkspace();

    smRoasSales($workspace, '2026-03-10', 3000);

    $response = $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $window))
        ->assertOk();

    expect($response->json('value'))->toBeNull();
});

test('the attributed ROAS divides the platform\'s own sales by the same spend', function () use ($window) {
    $workspace = smRoasWorkspace();

    // The two ratios are deliberately different, which is the point: blended
    // counts every order against the spend, attributed only the sales the
    // platform claims for the ads.
    smRoasSales($workspace, '2026-03-10', 3000);
    smRoasSpend($workspace, '2026-03-10', 1000, attributedSales: 3300);

    $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $window))
        ->assertOk()
        ->assertJson([
            'value' => 3.0,
            'actual' => 3.3,
        ]);
});

test('both ratios are null when there was no spend', function () use ($window) {
    $workspace = smRoasWorkspace();

    smRoasSales($workspace, '2026-03-10', 3000);
    smRoasSpend($workspace, '2026-03-10', 0, attributedSales: 500);

    $response = $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $window))
        ->assertOk();

    expect($response->json('value'))->toBeNull()
        ->and($response->json('actual'))->toBeNull();
});

test('the attributed ROAS only counts rows inside the window', function () use ($window) {
    $workspace = smRoasWorkspace();

    smRoasSpend($workspace, '2026-03-10', 1000, attributedSales: 2000);
    smRoasSpend($workspace, '2026-03-20', 5000, attributedSales: 90000);

    $response = $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('actual'))->toBe(2.0);
});

test('another workspace\'s rows are never counted', function () use ($window) {
    $workspace = smRoasWorkspace();
    $other = smRoasWorkspace();

    smRoasSpend($workspace, '2026-03-10', 1000, attributedSales: 2000);
    smRoasSpend($other, '2026-03-10', 1000, attributedSales: 90000);

    $response = $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('actual'))->toBe(2.0);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smRoasWorkspace();

    $this->actingAs(smRoasOwner($workspace))
        ->getJson(smRoasUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smRoasWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smRoasUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smRoasWorkspace();
    $viewer = smRoasViewer($workspace);

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smRoasUrl($workspace, $window))
        ->assertNotFound();
});

test('it needs a session', function () use ($window) {
    $this->getJson(smRoasUrl(smRoasWorkspace(), $window))->assertUnauthorized();
});
