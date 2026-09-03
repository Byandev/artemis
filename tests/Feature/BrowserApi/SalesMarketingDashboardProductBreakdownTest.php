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
 * Product breakdown on the S&M dashboard — the table beneath the comparison
 * chart. Its own endpoint so it loads and refreshes independently, but
 * deliberately the same rows the chart plots: the table states what the chart
 * shows, and every ratio in it (ROAS, RTS rate, the sub-total) is divided
 * client-side off these sums.
 */
function smPbWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

/** The workspace owner — unrestricted, so every product is in scope. */
function smPbOwner(Workspace $workspace): User
{
    return $workspace->owner;
}

function smPbUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/product-breakdown";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/**
 * A product with a page selling it. The shop carries the product link and the
 * team assignment, which is the path both the join and the visibility scoping
 * take.
 */
function smPbPage(Workspace $workspace, string $name, ?Team $team = null): Page
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
function smPbSiblingPage(Workspace $workspace, Page $page): Page
{
    return Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => $page->shop_id,
    ]);
}

/** One day of figures for a page. */
function smPbDay(
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
    $workspace = smPbWorkspace();

    $one = smPbPage($workspace, 'Aaa Collagen Drink');
    $two = smPbPage($workspace, 'Bbb Hair Serum');

    // Two days for one product, so the row has to be a sum, not a day.
    smPbDay($workspace, $one, '2026-03-10', spend: 100, sales: 400, orders: 4, returnedAmount: 50, deliveredAmount: 350);
    smPbDay($workspace, $one, '2026-03-11', spend: 100, sales: 200, orders: 2, returnedAmount: 50, deliveredAmount: 150);
    smPbDay($workspace, $two, '2026-03-10', spend: 500, sales: 500, orders: 5, returnedAmount: 100, deliveredAmount: 400);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
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
    $workspace = smPbWorkspace();

    $page = smPbPage($workspace, 'Two Page Product');
    $sibling = smPbSiblingPage($workspace, $page);

    smPbDay($workspace, $page, '2026-03-10', spend: 100, sales: 300);
    smPbDay($workspace, $sibling, '2026-03-10', spend: 200, sales: 900);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and((float) $response->json('rows.0.ad_spend'))->toBe(300.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(1200.0);
});

test('rows come back by name, not ranked — the panel does the ranking', function () use ($window) {
    $workspace = smPbWorkspace();

    // Deliberately created biggest-first, so a size ordering would show.
    $big = smPbPage($workspace, 'Zzz Best Seller');
    $small = smPbPage($workspace, 'Aaa Slow Mover');

    smPbDay($workspace, $big, '2026-03-10', spend: 9000, sales: 9000);
    smPbDay($workspace, $small, '2026-03-10', spend: 10, sales: 10);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect(array_column(array_column($response->json('rows'), 'product'), 'name'))
        ->toBe(['Aaa Slow Mover', 'Zzz Best Seller']);
});

test('it carries every column the four metrics are derived from', function () use ($window) {
    $workspace = smPbWorkspace();

    smPbDay(
        $workspace,
        smPbPage($workspace, 'Complete Row'),
        '2026-03-10',
        spend: 250,
        sales: 1000,
        orders: 10,
        returnedAmount: 200,
        deliveredAmount: 800,
    );

    $row = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
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
    $workspace = smPbWorkspace();

    $page = smPbPage($workspace, 'In And Out');

    smPbDay($workspace, $page, '2026-03-10', spend: 100, sales: 300);
    smPbDay($workspace, $page, '2026-03-20', spend: 90000, sales: 90000);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('rows.0.ad_spend'))->toBe(100.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(300.0);
});

test('a page whose shop sells nothing is left out rather than pooled', function () use ($window) {
    $workspace = smPbWorkspace();

    $sold = smPbPage($workspace, 'Assigned Product');

    $unassigned = Page::factory()->create([
        'workspace_id' => $workspace->id,
        'shop_id' => Shop::factory()->forWorkspace($workspace)->create(['product_id' => null])->id,
    ]);

    smPbDay($workspace, $sold, '2026-03-10', spend: 100, sales: 300);
    smPbDay($workspace, $unassigned, '2026-03-10', spend: 700, sales: 2100);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.product.name'))->toBe('Assigned Product');
});

test('a deleted page takes its figures with it', function () use ($window) {
    $workspace = smPbWorkspace();

    $page = smPbPage($workspace, 'Still Selling');
    $retired = smPbSiblingPage($workspace, $page);

    smPbDay($workspace, $page, '2026-03-10', spend: 100, sales: 300);
    smPbDay($workspace, $retired, '2026-03-10', spend: 900, sales: 2700);

    $retired->delete();

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect((float) $response->json('rows.0.ad_spend'))->toBe(100.0)
        ->and((float) $response->json('rows.0.sales'))->toBe(300.0);
});

test('a Gencys-partner workspace still gets its products', function () use ($window) {
    // A product hangs off the Pancake page, which is Artemis-source data
    // wherever it lives. Narrowing this query by the workspace's *advertiser*
    // source — the way every advertiser query here does — would ask for a
    // combination that cannot exist and answer "no products" forever.
    $workspace = smPbWorkspace();
    $workspace->update(['is_gencys_partner' => true]);

    smPbDay($workspace, smPbPage($workspace, 'Sold By A Partner'), '2026-03-10', spend: 100, sales: 400);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.product.name'))->toBe('Sold By A Partner');
});

test('another workspace\'s products never appear', function () use ($window) {
    $workspace = smPbWorkspace();
    $other = smPbWorkspace();

    smPbDay($workspace, smPbPage($workspace, 'Mine'), '2026-03-10', spend: 100);
    smPbDay($other, smPbPage($other, 'Theirs'), '2026-03-10', spend: 900);

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toHaveCount(1)
        ->and($response->json('rows.0.product.name'))->toBe('Mine');
});

test('the workspace-wide team switcher narrows which products are compared', function () use ($window) {
    $workspace = smPbWorkspace();
    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    $onA = smPbPage($workspace, 'On Team A', $teamA);
    $onB = smPbPage($workspace, 'On Team B', $teamB);

    smPbDay($workspace, $onA, '2026-03-10', spend: 100);
    smPbDay($workspace, $onB, '2026-03-10', spend: 200);

    $owner = smPbOwner($workspace);

    $all = $this->actingAs($owner)
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect($all->json('rows'))->toHaveCount(2);

    $narrowed = $this->actingAs($owner)
        ->getJson(smPbUrl($workspace, $window + ['team_id' => $teamA->id]))
        ->assertOk();

    expect($narrowed->json('rows'))->toHaveCount(1)
        ->and($narrowed->json('rows.0.product.name'))->toBe('On Team A');
});

test('an empty window returns no rows rather than failing', function () use ($window) {
    $workspace = smPbWorkspace();

    $response = $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $window))
        ->assertOk();

    expect($response->json('rows'))->toBe([]);
});

test('the window is required and has to be a real range', function (array $query) {
    $workspace = smPbWorkspace();

    $this->actingAs(smPbOwner($workspace))
        ->getJson(smPbUrl($workspace, $query))
        ->assertUnprocessable();
})->with([
    'missing' => [[]],
    'no end' => [['start' => '2026-03-08']],
    'not a date' => [['start' => 'last tuesday', 'end' => '2026-03-14']],
    'backwards' => [['start' => '2026-03-14', 'end' => '2026-03-08']],
]);

test('it is refused without the dashboard permission', function () use ($window) {
    $workspace = smPbWorkspace();

    $user = User::factory()->create();
    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'No grants '.uniqid()]);
    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    $this->actingAs($user)
        ->getJson(smPbUrl($workspace, $window))
        ->assertForbidden();
});

test('it 404s when the module is switched off', function () use ($window) {
    $workspace = smPbWorkspace();

    $viewer = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );

    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($viewer)
        ->getJson(smPbUrl($workspace, $window))
        ->assertNotFound();
});

test('it answers exactly what the comparison endpoint answers', function () use ($window) {
    // The table states what the chart plots. They are separate endpoints only
    // so each loads and retries on its own — the moment the two disagree about
    // a figure, the panel above and the row below stop being the same thing.
    $workspace = smPbWorkspace();

    $one = smPbPage($workspace, 'Aaa Product');
    $two = smPbPage($workspace, 'Bbb Product');

    smPbDay($workspace, $one, '2026-03-10', spend: 250, sales: 1000, orders: 10, returnedAmount: 200, deliveredAmount: 800);
    smPbDay($workspace, $two, '2026-03-11', spend: 500, sales: 500, orders: 5, returnedAmount: 100, deliveredAmount: 400);

    $owner = smPbOwner($workspace);

    $breakdown = $this->actingAs($owner)->getJson(smPbUrl($workspace, $window))->assertOk();
    $comparison = $this->actingAs($owner)->getJson(
        "/api/workspaces/{$workspace->slug}/sales-marketing/dashboard/product-comparison?".http_build_query($window)
    )->assertOk();

    expect($breakdown->json('rows'))->toBe($comparison->json('rows'));
});

test('it needs a session', function () use ($window) {
    $this->getJson(smPbUrl(smPbWorkspace(), $window))->assertUnauthorized();
});
