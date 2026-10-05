<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\AdvertiserPerformanceDailyRecord;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/**
 * The Ad Spent Summary's data endpoint. The page is a shell; the rows — one per
 * day over the range, summed across advertisers — come from here. These pin
 * the sums and ROAS, the range handling, the advertiser filter, team scoping,
 * and that the endpoint sits behind the same switch and grant as the page.
 */
function smSummaryWorkspace(): Workspace
{
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->update(['sales_marketing_dashboard_module_enabled' => true]);

    return $workspace;
}

function smSummaryUrl(Workspace $workspace, array $query = []): string
{
    $url = "/api/workspaces/{$workspace->slug}/sales-marketing/ad-spent-summary";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** A workspace member who advertises. */
function smSummaryAdvertiser(Workspace $workspace, string $name, ?Team $team = null): User
{
    $user = User::factory()->create(['name' => $name]);
    $workspace->users()->attach($user->id, ['role' => 'member']);

    if ($team) {
        $user->teams()->attach($team);
    }

    return $user;
}

function smSummaryRecord(Workspace $workspace, User $advertiser, string $date, float $sales, float $spend, int $orders = 1): void
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

/** A member holding only this page's grant — scoped to the teams they are on. */
function smSummaryViewer(Workspace $workspace): User
{
    return makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewAdSpentSummary->value],
        PermissionEnum::ViewAdSpentSummary->category(),
    );
}

$range = ['start_date' => '2026-03-08', 'end_date' => '2026-03-10'];

test('it sums every advertiser per day and works out the roas', function () use ($range) {
    $workspace = smSummaryWorkspace();
    $ana = smSummaryAdvertiser($workspace, 'Ana');
    $bea = smSummaryAdvertiser($workspace, 'Bea');

    smSummaryRecord($workspace, $ana, '2026-03-09', sales: 1000, spend: 200, orders: 3);
    smSummaryRecord($workspace, $bea, '2026-03-09', sales: 500, spend: 300, orders: 2);

    $this->actingAs($workspace->owner)
        ->getJson(smSummaryUrl($workspace, $range))
        ->assertOk()
        ->assertJsonStructure([
            'filters' => ['start_date', 'end_date', 'advertisers'],
            'rows' => [['date', 'orders', 'sales', 'ad_spent', 'roas']],
        ])
        ->assertJsonPath('rows.1.date', '2026-03-09')
        ->assertJsonPath('rows.1.orders', 5)
        ->assertJsonPath('rows.1.sales', 1500)
        ->assertJsonPath('rows.1.ad_spent', 500)
        ->assertJsonPath('rows.1.roas', 3);
});

test('it answers every day in the range, empty ones included', function () use ($range) {
    $workspace = smSummaryWorkspace();
    smSummaryRecord($workspace, smSummaryAdvertiser($workspace, 'Ana'), '2026-03-09', sales: 100, spend: 50);

    $this->actingAs($workspace->owner)
        ->getJson(smSummaryUrl($workspace, $range))
        ->assertOk()
        ->assertJsonCount(3, 'rows')
        ->assertJsonPath('rows.0.date', '2026-03-08')
        ->assertJsonPath('rows.0.sales', 0)
        // No spend means no ROAS, rather than a division by zero.
        ->assertJsonPath('rows.0.roas', null)
        ->assertJsonPath('rows.2.date', '2026-03-10');
});

test('it defaults to the trailing seven days', function () {
    $workspace = smSummaryWorkspace();

    $this->actingAs($workspace->owner)
        ->getJson(smSummaryUrl($workspace))
        ->assertOk()
        ->assertJsonCount(7, 'rows')
        ->assertJsonPath('filters.start_date', Carbon::today()->subDays(6)->toDateString())
        ->assertJsonPath('filters.end_date', Carbon::today()->toDateString());
});

test('a backwards range is swapped rather than refused', function () {
    $workspace = smSummaryWorkspace();

    $this->actingAs($workspace->owner)
        ->getJson(smSummaryUrl($workspace, ['start_date' => '2026-03-10', 'end_date' => '2026-03-08']))
        ->assertOk()
        ->assertJsonPath('filters.start_date', '2026-03-08')
        ->assertJsonPath('filters.end_date', '2026-03-10')
        ->assertJsonCount(3, 'rows');
});

test('the advertiser filter narrows the sums', function () use ($range) {
    $workspace = smSummaryWorkspace();
    $ana = smSummaryAdvertiser($workspace, 'Ana');
    $bea = smSummaryAdvertiser($workspace, 'Bea');

    smSummaryRecord($workspace, $ana, '2026-03-09', sales: 1000, spend: 200);
    smSummaryRecord($workspace, $bea, '2026-03-09', sales: 500, spend: 300);

    $this->actingAs($workspace->owner)
        ->getJson(smSummaryUrl($workspace, [...$range, 'advertisers' => [$ana->id]]))
        ->assertOk()
        ->assertJsonPath('filters.advertisers', [(string) $ana->id])
        ->assertJsonPath('rows.1.sales', 1000)
        ->assertJsonPath('rows.1.ad_spent', 200);
});

test('another workspace\'s records never count', function () use ($range) {
    $workspace = smSummaryWorkspace();
    $other = smSummaryWorkspace();
    smSummaryRecord($other, smSummaryAdvertiser($other, 'Bea'), '2026-03-09', sales: 900, spend: 300);

    $this->actingAs($workspace->owner)
        ->getJson(smSummaryUrl($workspace, $range))
        ->assertOk()
        ->assertJsonPath('rows.1.sales', 0);
});

test('a scoped member sums only their own team\'s advertisers', function () use ($range) {
    $workspace = smSummaryWorkspace();
    $mine = Team::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Team::factory()->create(['workspace_id' => $workspace->id]);

    smSummaryRecord($workspace, smSummaryAdvertiser($workspace, 'Ana', $mine), '2026-03-09', sales: 1000, spend: 200);
    smSummaryRecord($workspace, smSummaryAdvertiser($workspace, 'Bea', $theirs), '2026-03-09', sales: 500, spend: 300);

    $member = smSummaryViewer($workspace);
    $member->teams()->attach($mine);

    $this->actingAs($member)
        ->getJson(smSummaryUrl($workspace, $range))
        ->assertOk()
        ->assertJsonPath('rows.1.sales', 1000);
});

test('a scoped member cannot widen the filter to another team\'s advertiser', function () use ($range) {
    $workspace = smSummaryWorkspace();
    $mine = Team::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Team::factory()->create(['workspace_id' => $workspace->id]);

    smSummaryAdvertiser($workspace, 'Ana', $mine);
    $bea = smSummaryAdvertiser($workspace, 'Bea', $theirs);
    smSummaryRecord($workspace, $bea, '2026-03-09', sales: 500, spend: 300);

    $member = smSummaryViewer($workspace);
    $member->teams()->attach($mine);

    $this->actingAs($member)
        ->getJson(smSummaryUrl($workspace, [...$range, 'advertisers' => [$bea->id]]))
        ->assertOk()
        ->assertJsonPath('rows.1.sales', 0);
});

test('it opens for a member holding only the ad spent summary grant', function () {
    $workspace = smSummaryWorkspace();

    $this->actingAs(smSummaryViewer($workspace))->getJson(smSummaryUrl($workspace))->assertOk();
});

test('it refuses a member without the ad spent summary grant', function () {
    $workspace = smSummaryWorkspace();

    $member = makeMemberWithPermissions(
        $workspace,
        [PermissionEnum::ViewSalesMarketingDashboard->value],
        PermissionEnum::ViewSalesMarketingDashboard->category(),
    );

    $this->actingAs($member)->getJson(smSummaryUrl($workspace))->assertForbidden();
});

test('it 404s when the sales & marketing module is off', function () {
    $workspace = smSummaryWorkspace();
    $workspace->update(['sales_marketing_dashboard_module_enabled' => false]);

    $this->actingAs($workspace->owner)->getJson(smSummaryUrl($workspace))->assertNotFound();
});

test('it requires a signed-in user', function () {
    $this->getJson(smSummaryUrl(smSummaryWorkspace()))->assertUnauthorized();
});

test('the page itself ships only the shell and the filter options', function () {
    $workspace = smSummaryWorkspace();
    $ana = smSummaryAdvertiser($workspace, 'Ana');
    smSummaryRecord($workspace, $ana, '2026-03-09', sales: 100, spend: 50);

    $this->actingAs($workspace->owner)
        ->get("/workspaces/{$workspace->slug}/sales-marketing/ad-spent-summary?start_date=2026-03-08&end_date=2026-03-10")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/integrations/meta-ad-spent-summary')
            ->where('filters.start_date', '2026-03-08')
            ->where('filters.end_date', '2026-03-10')
            ->where('advertiserOptions', [['value' => (string) $ana->id, 'label' => 'Ana']])
            ->missing('rows'));
});
