<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Page;
use App\Models\PageDailyRecord;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;

/**
 * Product comparison on the S&M dashboard. One row of raw sums per product and
 * nothing else — the panel switches between Sales, Ad spend, ROAS and RTS, and
 * folds everything past the palette into an "Others" bar, all client-side off
 * these figures.
 *
 * A product's figures are its pages' figures, reached through the shop that
 * sells it (shops.product_id), summed off the same page_daily_records the
 * nightly build fills from the same Pancake/Meta data the team panel reads.
 */
function smProductWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so every product is in scope. */
function smProductOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

function smProductUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/product-comparison";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/**
 * A product with a page selling it. The shop carries the product link and the
 * team assignment, which is the path both the join and the visibility scoping
 * take.
 */
function smProductPage(Workspace $workspace, string $name, ?Team $team = null): Page
{
    $product = Product::factory()->create([
        'workspace_id' => $workspace->id,
        'name' => $name,
    ]);

    $shop = Shop::factory()->forWorkspace($workspace)->create([
        'product_id' => $product->id,
    ]);

    if ($team) {
        $shop->teams()->attach($team);
    }

    return Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => $shop->id,
    ]);
}

/** A second page selling the same product as an existing one. */
function smProductSiblingPage(Workspace $workspace, Page $page): Page
{
    return Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => $page->shop_id,
    ]);
}

/** One day of figures for a page. */
function smProductDay(
    Workspace $workspace,
    Page $page,
    string $date,
    float $spend = 0,
    float $sales = 0,
    int $orders = 0,
    float $returnedAmount = 0,
    float $deliveredAmount = 0,
): void {
    PageDailyRecord::create([
        'workspace_id' => $workspace->id,
        'source' => PageDailyRecord::SOURCE_ARTEMIS,
        'page_type' => $page->getMorphClass(),
        'page_id' => $page->id,
        'date' => $date,
        'ad_spent' => $spend,
        'sales' => $sales,
        'orders' => $orders,
        'returned_amount' => $returnedAmount,
        'delivered_amount' => $deliveredAmount,
    ]);
}

$window = ['start' => '2026-03-08', 'end' => '2026-03-14'];

test('it returns one row of raw sums per product', function () use ($window) {
    $workspace = smProductWorkspace();

    $one = smProductPage($workspace, 'Aaa Collagen Drink');
    $two = smProductPage($workspace, 'Bbb Hair Serum');

    // Two days for one product, so the row has to be a sum, not a day.
    smProductDay($workspace, $one, '2026-03-10', spend: 100, sales: 400, orders: 4, returnedAmount: 50, deliveredAmount: 350);
    smProductDay($workspace, $one, '2026-03-11', spend: 100, sales: 200, orders: 2, returnedAmount: 50, deliveredAmount: 150);
    smProductDay($workspace, $two, '2026-03-10', spend: 500, sales: 500, orders: 5, returnedAmount: 100, deliveredAmount: 400);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(2);

    $response->assertJson([
        'rows' => [
            [
                'product' => ['name' => 'Aaa Collagen Drink'],
                'ad_spend' => 200,
                'sales' => 600,
                'orders' => 6,
                'returned_amount' => 100,
                'delivered_amount' => 500,
            ],
            [
                'product' => ['name' => 'Bbb Hair Serum'],
                'ad_spend' => 500,
                'sales' => 500,
            ],
        ],
    ]);
});

test('every page selling a product rolls into that product\'s one row', function () use ($window) {
    $workspace = smProductWorkspace();

    $page = smProductPage($workspace, 'Two Page Product');
    $sibling = smProductSiblingPage($workspace, $page);

    smProductDay($workspace, $page, '2026-03-10', spend: 100, sales: 300);
    smProductDay($workspace, $sibling, '2026-03-10', spend: 200, sales: 900);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and((float) $response->json('rows.0.ad_spend'))->toBe(300.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(1200.0);
});

test('rows come back by name, not ranked — the panel does the ranking', function () use ($window) {
    $workspace = smProductWorkspace();

    // Deliberately created biggest-first, so a size ordering would show.
    $big = smProductPage($workspace, 'Zzz Best Seller');
    $small = smProductPage($workspace, 'Aaa Slow Mover');

    smProductDay($workspace, $big, '2026-03-10', spend: 9000, sales: 9000);
    smProductDay($workspace, $small, '2026-03-10', spend: 10, sales: 10);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect(array_column(array_column($response->json('rows'), 'product'), 'name'))
        ->toBe(['Aaa Slow Mover', 'Zzz Best Seller']);
});

test('it carries every column the four metrics are derived from', function () use ($window) {
    $workspace = smProductWorkspace();

    smProductDay(
        $workspace,
        smProductPage($workspace, 'Complete Row'),
        '2026-03-10',
        spend: 250,
        sales: 1000,
        orders: 10,
        returnedAmount: 200,
        deliveredAmount: 800,
    );

    $row = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk()
        ->json('rows.0');

    // Sales and Ad spend read straight off; ROAS is 1000/250 and RTS is
    // 200/(200+800) once the panel divides them.
    expect($row)->toHaveKeys([
        'product', 'ad_spend', 'sales', 'orders', 'returned_amount', 'delivered_amount',
    ]);
    // JSON hands back ints where the decimal is .0, so compare as floats.
    expect((float) $row['sales'] / (float) $row['ad_spend'])->toBe(4.0)
        ->and((float) $row['returned_amount'] / ((float) $row['returned_amount'] + (float) $row['delivered_amount']))->toBe(0.2);
});

test('it only sums days inside the window', function () use ($window) {
    $workspace = smProductWorkspace();

    $page = smProductPage($workspace, 'In And Out');

    smProductDay($workspace, $page, '2026-03-10', spend: 100, sales: 300);
    smProductDay($workspace, $page, '2026-03-20', spend: 90000, sales: 90000);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('rows.0.ad_spend'))->toBe(100.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(300.0);
});

test('a page whose shop sells nothing is left out rather than pooled', function () use ($window) {
    $workspace = smProductWorkspace();

    $sold = smProductPage($workspace, 'Assigned Product');

    $unassigned = Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => Shop::factory()->forWorkspace($workspace)->create(['product_id' => null])->id,
    ]);

    smProductDay($workspace, $sold, '2026-03-10', spend: 100, sales: 300);
    smProductDay($workspace, $unassigned, '2026-03-10', spend: 700, sales: 2100);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.product.name'))->toBe('Assigned Product');
});

test('a deleted page takes its figures with it', function () use ($window) {
    $workspace = smProductWorkspace();

    $page = smProductPage($workspace, 'Still Selling');
    $retired = smProductSiblingPage($workspace, $page);

    smProductDay($workspace, $page, '2026-03-10', spend: 100, sales: 300);
    smProductDay($workspace, $retired, '2026-03-10', spend: 900, sales: 2700);

    $retired->delete();

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('rows.0.ad_spend'))->toBe(100.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(300.0);
});

test('a Gencys-partner workspace still gets its products', function () use ($window) {
    // A product hangs off the Pancake page, which is Artemis-source data
    // wherever it lives. Narrowing this query by the workspace's *advertiser*
    // source — the way every advertiser query here does — would ask for a
    // combination that cannot exist and answer "no products" forever.
    $workspace = smProductWorkspace();
    $workspace->update(['is_gencys_partner' => true]);

    smProductDay($workspace, smProductPage($workspace, 'Sold By A Partner'), '2026-03-10', spend: 100, sales: 400);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.product.name'))->toBe('Sold By A Partner');
});

test('another workspace\'s products never appear', function () use ($window) {
    $workspace = smProductWorkspace();
    $other = smProductWorkspace();

    smProductDay($workspace, smProductPage($workspace, 'Mine'), '2026-03-10', spend: 100);
    smProductDay($other, smProductPage($other, 'Theirs'), '2026-03-10', spend: 900);

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.product.name'))->toBe('Mine');
});

test('the workspace-wide team switcher narrows which products are compared', function () use ($window) {
    $workspace = smProductWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $onA = smProductPage($workspace, 'On Team A', $teamA);
    $onB = smProductPage($workspace, 'On Team B', $teamB);

    smProductDay($workspace, $onA, '2026-03-10', spend: 100);
    smProductDay($workspace, $onB, '2026-03-10', spend: 200);

    $owner = smProductOwner($workspace);

    $all = $this->actingAs($owner)
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($all->json('rows'))->toHaveCount(2);

    $narrowed = $this->actingAs($owner)
        ->getJson(smProductUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect($narrowed->json('rows'))->toHaveCount(1)
        ->and($narrowed->json('rows.0.product.name'))->toBe('On Team A');
});

test('an empty window returns no rows rather than failing', function () use ($window) {
    $workspace = smProductWorkspace();

    $response = $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toBe([]);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smProductWorkspace();

    $this->actingAs(smProductOwner($workspace))
        ->getJson(smProductUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smProductWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smProductUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smProductWorkspace();

    $viewer = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smProductUrl($workspace, $window))
        ->assertNotFound();
});

test('it needs a session', function () use ($window) {
    $this->getJson(smProductUrl(smProductWorkspace(), $window))->assertUnauthorized();
});
