<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * The Daily Report's data endpoint. The page is a shell; the whole report for
 * one day — both tables and the analytics — comes from here, on load and on
 * every date change. These pin the shape, the date handling, and that the
 * endpoint sits behind the same module switch and permission as the page.
 */
function smDailyWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

function smDailyUrl(Workspace $workspace, ?string $date = null): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/daily-report";

    return $date ? $url.'?'.http_build_query(['filter' => ['date' => $date]]) : $url;
}

/** A workspace member who advertises. */
function smDailyAdvertiser(Workspace $workspace, string $name): User
{
    $user = User::factory()->create(['name' => $name]);
    $workspace->users()->attach($user->id, ['role' => 'member']);

    return $user;
}

function smDailyRecord(Workspace $workspace, User $advertiser, string $date, float $sales, float $spend, int $orders = 1): void
{
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
    ]);
}

test('it returns the report for the requested day', function () {
    $workspace = smDailyWorkspace();
    $ana = smDailyAdvertiser($workspace, 'Ana');

    smDailyRecord($workspace, $ana, '2026-03-09', sales: 800, spend: 400);
    smDailyRecord($workspace, $ana, '2026-03-10', sales: 1000, spend: 250, orders: 4);

    $this->actingAs($workspace->owner)
        ->getJson(smDailyUrl($workspace, '2026-03-10'))
        ->assertOk()
        ->assertJsonStructure([
            'view' => [
                'date', 'date_label', 'prev_date', 'prev_date_label',
                'rows' => [['id', 'name', 'orders', 'sales', 'yesterday_sales', 'change', 'status', 'month_sales', 'rank', 'roas_yesterday', 'roas_today']],
                'subtotal',
                'ad_rts' => ['rows', 'subtotal'],
                'charts' => ['kpis' => ['total_sales', 'total_ad_spent', 'roas', 'total_orders', 'deltas'], 'by_advertiser'],
            ],
            'filters' => ['date'],
        ])
        ->assertJsonPath('filters.date', '2026-03-10')
        ->assertJsonPath('view.date', '2026-03-10')
        ->assertJsonPath('view.prev_date', '2026-03-09')
        ->assertJsonPath('view.rows.0.id', $ana->id)
        ->assertJsonPath('view.rows.0.orders', 4)
        ->assertJsonPath('view.rows.0.status', 'up')
        ->assertJsonPath('view.rows.0.roas_today', 4)
        ->assertJsonPath('view.rows.0.roas_yesterday', 2);
});

test('without a date it opens on the latest day that has records', function () {
    $workspace = smDailyWorkspace();
    $ana = smDailyAdvertiser($workspace, 'Ana');

    // Yesterday is empty, so the report falls back to the last day with data.
    $latest = Carbon::yesterday()->subDays(3)->toDateString();
    smDailyRecord($workspace, $ana, $latest, sales: 500, spend: 100);

    $this->actingAs($workspace->owner)
        ->getJson(smDailyUrl($workspace))
        ->assertOk()
        ->assertJsonPath('filters.date', $latest)
        ->assertJsonPath('view.date', $latest)
        ->assertJsonCount(1, 'view.rows');
});

test('a day with no records comes back empty, not an error', function () {
    $workspace = smDailyWorkspace();

    $this->actingAs($workspace->owner)
        ->getJson(smDailyUrl($workspace, '2026-03-10'))
        ->assertOk()
        ->assertJsonPath('view.rows', [])
        ->assertJsonPath('view.ad_rts.rows', [])
        ->assertJsonPath('view.charts.kpis.total_sales', 0);
});

test('another workspace\'s records never appear', function () {
    $workspace = smDailyWorkspace();
    $other = smDailyWorkspace();
    $bea = smDailyAdvertiser($other, 'Bea');

    smDailyRecord($other, $bea, '2026-03-10', sales: 900, spend: 300);

    $this->actingAs($workspace->owner)
        ->getJson(smDailyUrl($workspace, '2026-03-10'))
        ->assertOk()
        ->assertJsonPath('view.rows', []);
});

test('it opens for a member holding only the daily report grant', function () {
    $workspace = smDailyWorkspace();

    $member = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDailyReport->value],
        PermissionEnum::ViewSalesMarketingDailyReport->category(),
    );

    $this->actingAs($member)->getJson(smDailyUrl($workspace))->assertOk();
});

test('it refuses a member without the daily report grant', function () {
    $workspace = smDailyWorkspace();

    // The dashboard grant is a sibling, not this page's.
    $member = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );

    $this->actingAs($member)->getJson(smDailyUrl($workspace))->assertForbidden();
});

test('it 404s when the sales & marketing module is off', function () {
    $workspace = smDailyWorkspace();
    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($workspace->owner)->getJson(smDailyUrl($workspace))->assertNotFound();
});

test('it requires a signed-in user', function () {
    $this->getJson(smDailyUrl(smDailyWorkspace()))->assertUnauthorized();
});

test('the page itself ships only the shell', function () {
    $workspace = smDailyWorkspace();

    $this->actingAs($workspace->owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/daily-report?filter[date]=2026-03-10")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/sales-marketing/daily-report/index')
            ->where('filters.date', '2026-03-10')
            ->missing('view'));
});

test('the old tabbed data URL lands on the endpoint', function () {
    $workspace = smDailyWorkspace();

    $this->actingAs($workspace->owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/dashboard/data")
        ->assertRedirect(smDailyUrl($workspace));
});
