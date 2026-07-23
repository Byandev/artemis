<?php

use App\Enums\Permission as AppPermission;
use App\Models\Page;
use App\Models\PageDailyBudgetRecord;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Collection;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    // The workspace owner is implicitly all-powerful, which keeps these tests
    // about the template behaviour rather than about permissions.
    $this->user = User::factory()->create();
    // The factory attaches the owner as a member for us.
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);

    $this->product = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'owner_id' => $this->user->id,
        'name' => 'Happy Heart Gel',
    ]);

    // A page reaches its product through its shop (shops.product_id), which is
    // the path the picker relies on.
    $shop = Shop::factory()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $this->product->id,
    ]);

    $this->page = Page::factory()->create([
        'workspace_id' => $this->workspace->id,
        'shop_id' => $shop->id,
        'owner_id' => $this->user->id,
        'name' => 'PH Main',
    ]);

    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

function adSpentPayload(array $overrides = []): array
{
    return array_merge([
        'template' => 'ad_spent',
        'request_date' => '2026-05-14',
        'requested_by' => test()->user->id,
        'charge_to' => test()->user->id,
        'gotyme_number' => '11068673582',
        'purpose' => 'For scaling / running',
        'items' => [
            ['product_id' => test()->product->id, 'page_id' => test()->page->id, 'creatives_running' => 5, 'budget_per_day' => 200, 'days' => 5],
            ['product_id' => test()->product->id, 'page_id' => null, 'creatives_running' => 5, 'budget_per_day' => 2500, 'days' => 5],
        ],
    ], $overrides);
}

it('totals an ad spent request from its items and ignores the amount the client sent', function () {
    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload(['amount_requested' => 999999]))
        ->assertRedirect();

    $request = FundRequest::with('items')->latest('id')->first();

    // 200x5 + 2500x5, matching the spreadsheet this template mirrors.
    expect((float) $request->amount_requested)->toBe(13500.00)
        ->and($request->template)->toBe('ad_spent')
        ->and($request->gotyme_number)->toBe('11068673582')
        ->and($request->items)->toHaveCount(2);

    expect((float) $request->items[0]->total)->toBe(1000.00)
        ->and((float) $request->items[1]->total)->toBe(12500.00);
});

it('snapshots the product name onto each item', function () {
    $this->actingAs($this->user)->post($this->url, adSpentPayload());

    $items = FundRequest::with('items')->latest('id')->first()->items;

    expect($items->pluck('item_label')->all())->each->toBe('Happy Heart Gel');
});

it('keeps the client amount on a blank request and stores no items', function () {
    $this->actingAs($this->user)
        ->post($this->url, [
            'template' => 'blank',
            'request_date' => '2026-05-14',
            'requested_by' => $this->user->id,
            'charge_to' => $this->user->id,
            'purpose' => 'Office supplies',
            'amount_requested' => 1234.56,
        ])
        ->assertRedirect();

    $request = FundRequest::with('items')->latest('id')->first();

    expect((float) $request->amount_requested)->toBe(1234.56)
        ->and($request->template)->toBe('blank')
        ->and($request->items)->toHaveCount(0);
});

it('rejects an ad spent request with no items', function () {
    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload(['items' => []]))
        ->assertSessionHasErrors('items');

    expect(FundRequest::count())->toBe(0);
});

it('requires an amount on a blank request', function () {
    $this->actingAs($this->user)
        ->post($this->url, [
            'template' => 'blank',
            'request_date' => '2026-05-14',
            'requested_by' => $this->user->id,
            'charge_to' => $this->user->id,
            'purpose' => 'Missing amount',
        ])
        ->assertSessionHasErrors('amount_requested');
});

it('drops the items when a request is switched back to blank', function () {
    $this->actingAs($this->user)->post($this->url, adSpentPayload());
    $request = FundRequest::latest('id')->first();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", [
            'template' => 'blank',
            'request_date' => '2026-05-14',
            'requested_by' => $this->user->id,
            'charge_to' => $this->user->id,
            'purpose' => 'Switched to blank',
            'amount_requested' => 50,
        ])
        ->assertRedirect();

    $request->refresh()->load('items');

    expect($request->items)->toHaveCount(0)
        ->and((float) $request->amount_requested)->toBe(50.00);
});

it('rejects an item whose product belongs to another workspace', function () {
    $stranger = User::factory()->create();
    $foreign = Product::factory()->create([
        'workspace_id' => Workspace::factory()->create(['owner_id' => $stranger->id])->id,
        'owner_id' => $stranger->id,
    ]);

    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload([
            'items' => [
                ['product_id' => $foreign->id, 'page_id' => null, 'creatives_running' => 1, 'budget_per_day' => 100, 'days' => 1],
            ],
        ]))
        ->assertSessionHasErrors('items.0.product_id');

    expect(FundRequest::count())->toBe(0);
});

/** The page options the request-funds screen hands to the item picker. */
function pageOptionsFor(User $user): Collection
{
    return collect(
        test()->actingAs($user)->get(test()->url)
            ->viewData('page')['props']['myPages']
    );
}

/**
 * A member scoped to their own team's data — no "View All Workspace Data" — but
 * allowed to see the request-funds screen.
 *
 * @param  array<int, Team>  $teams
 */
function financeMember(Workspace $workspace, array $teams = []): User
{
    $user = User::factory()->create();

    $role = Role::factory()->create(['workspace_id' => $workspace->id]);
    $role->permissions()->attach(
        Permission::whereIn('name', [
            AppPermission::ViewFinanceRequestFunds->value,
            AppPermission::CreateFinanceRequestFunds->value,
        ])->pluck('id')
    );

    $user->workspaces()->attach($workspace, ['role_id' => $role->id]);

    foreach ($teams as $team) {
        $user->teams()->attach($team);
    }

    return $user;
}

it('carries the latest budget on each offered page', function () {
    // Two records so the picker is proven to take the most recent, not just any.
    PageDailyBudgetRecord::create([
        'workspace_id' => $this->workspace->id,
        'page_id' => $this->page->id,
        'date' => '2026-05-10',
        'budget' => 150,
    ]);
    PageDailyBudgetRecord::create([
        'workspace_id' => $this->workspace->id,
        'page_id' => $this->page->id,
        'date' => '2026-05-14',
        'budget' => 200,
    ]);

    $pages = pageOptionsFor($this->user);

    expect($pages)->toHaveCount(1)
        ->and($pages[0]['name'])->toBe('PH Main')
        ->and($pages[0]['product_id'])->toBe($this->product->id)
        ->and((float) $pages[0]['budget_per_day'])->toBe(200.00)
        ->and($pages[0]['budget_date'])->toBe('2026-05-14');
});

it('offers a scoped member the pages of their team, whoever owns them', function () {
    $team = Team::factory()->create(['workspace_id' => $this->workspace->id]);

    // The page's shop belongs to the team; the page itself is owned by somebody
    // else entirely — team membership is what decides now, not pages.owner_id.
    $this->page->shop->teams()->attach($team);
    $this->page->update(['owner_id' => User::factory()->create()->id]);

    $member = financeMember($this->workspace, [$team]);

    $pages = pageOptionsFor($member);

    expect($pages)->toHaveCount(1)
        ->and($pages[0]['name'])->toBe('PH Main');
});

it('hides pages belonging to a team the member is not in', function () {
    $ourTeam = Team::factory()->create(['workspace_id' => $this->workspace->id]);
    $theirTeam = Team::factory()->create(['workspace_id' => $this->workspace->id]);

    $this->page->shop->teams()->attach($ourTeam);

    $otherShop = Shop::factory()->create([
        'workspace_id' => $this->workspace->id,
        'product_id' => $this->product->id,
    ]);
    $otherShop->teams()->attach($theirTeam);
    Page::factory()->create([
        'workspace_id' => $this->workspace->id,
        'shop_id' => $otherShop->id,
        'owner_id' => $this->user->id,
        'name' => 'Other Team Page',
    ]);

    $pages = pageOptionsFor(financeMember($this->workspace, [$ourTeam]));

    expect($pages)->toHaveCount(1)
        ->and($pages[0]['name'])->toBe('PH Main');
});

it('offers a scoped member with no team nothing', function () {
    $this->page->shop->teams()->attach(
        Team::factory()->create(['workspace_id' => $this->workspace->id])
    );

    expect(pageOptionsFor(financeMember($this->workspace)))->toBeEmpty();
});

it('offers an unrestricted user every page in the workspace', function () {
    // The owner sees everything, including pages on shops with no team at all.
    Page::factory()->create([
        'workspace_id' => $this->workspace->id,
        'shop_id' => $this->page->shop_id,
        'owner_id' => User::factory()->create()->id,
        'name' => 'Not Mine',
    ]);

    expect(pageOptionsFor($this->user)->pluck('name')->all())
        ->toEqualCanonicalizing(['PH Main', 'Not Mine']);
});
