<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

/** A blank request payload with everything but the charge-to rows filled in. */
function fundRequestPayload(array $attrs = []): array
{
    return array_merge([
        'template' => 'blank',
        'request_date' => '2026-05-14',
        'requested_by' => test()->user->id,
        'purpose' => 'Office supplies',
        'amount_requested' => 900,
    ], $attrs);
}

/** A workspace member, so a request can be split between several people. */
function fundMember(Workspace $workspace): User
{
    $user = User::factory()->create();
    $workspace->users()->attach($user->id);

    return $user;
}

test('a request charged to one user gives them the whole amount', function () {
    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'charge_to' => [['user_id' => $this->user->id]],
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('finance_request_fund_charge_to', [
        'fund_request_id' => FundRequest::first()->id,
        'user_id' => $this->user->id,
        'amount' => 900,
    ]);
});

test('blank shares split the amount evenly between the charged users', function () {
    $maria = fundMember($this->workspace);
    $juan = fundMember($this->workspace);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'charge_to' => [
                ['user_id' => $this->user->id],
                ['user_id' => $maria->id],
                ['user_id' => $juan->id],
            ],
        ]))
        ->assertRedirect();

    $shares = FundRequest::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$this->user->id])->toBe(300.0)
        ->and($shares[$maria->id])->toBe(300.0)
        ->and($shares[$juan->id])->toBe(300.0);
});

test('an uneven split keeps every centavo, the odd ones going to the first user', function () {
    $maria = fundMember($this->workspace);
    $juan = fundMember($this->workspace);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'amount_requested' => 100,
            'charge_to' => [
                ['user_id' => $this->user->id],
                ['user_id' => $maria->id],
                ['user_id' => $juan->id],
            ],
        ]))
        ->assertRedirect();

    $shares = FundRequest::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$this->user->id])->toBe(33.34)
        ->and($shares[$maria->id])->toBe(33.33)
        ->and($shares[$juan->id])->toBe(33.33)
        ->and(round($shares->sum(), 2))->toBe(100.0);
});

test('a blank share takes whatever the explicit shares left over', function () {
    $maria = fundMember($this->workspace);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'charge_to' => [
                ['user_id' => $this->user->id, 'amount' => 700],
                ['user_id' => $maria->id],
            ],
        ]))
        ->assertRedirect();

    $shares = FundRequest::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$this->user->id])->toBe(700.0)
        ->and($shares[$maria->id])->toBe(200.0);
});

test('shares that do not add up to the amount are rejected', function () {
    $maria = fundMember($this->workspace);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'charge_to' => [
                ['user_id' => $this->user->id, 'amount' => 100],
                ['user_id' => $maria->id, 'amount' => 200],
            ],
        ]))
        ->assertSessionHasErrors('charge_to');

    expect(FundRequest::count())->toBe(0);
});

test('a request must be charged to at least one member', function () {
    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload(['charge_to' => []]))
        ->assertSessionHasErrors('charge_to');

    expect(FundRequest::count())->toBe(0);
});

test('a non-member cannot be charged', function () {
    $stranger = User::factory()->create();

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'charge_to' => [['user_id' => $stranger->id]],
        ]))
        ->assertSessionHasErrors('charge_to.0.user_id');

    expect(FundRequest::count())->toBe(0);
});

test('an ad spent request splits the item-derived total, not the amount the client sent', function () {
    $maria = fundMember($this->workspace);
    $product = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'owner_id' => $this->user->id,
    ]);

    // The controller recomputes the total from the items (200x5 = 1000), so the
    // shares must be checked against that rather than the bogus figure below.
    $this->actingAs($this->user)
        ->post($this->url, [
            'template' => 'ad_spent',
            'request_date' => '2026-05-14',
            'requested_by' => $this->user->id,
            'purpose' => 'For scaling',
            'amount_requested' => 999999,
            'charge_to' => [
                ['user_id' => $this->user->id],
                ['user_id' => $maria->id],
            ],
            'items' => [
                ['product_id' => $product->id, 'page_id' => null, 'creatives_running' => 5, 'budget_per_day' => 200, 'days' => 5],
            ],
        ])
        ->assertRedirect();

    $request = FundRequest::first();

    $shares = $request->chargeToUsers->pluck('pivot.amount', 'id')->map(fn ($a) => (float) $a);

    expect((float) $request->amount_requested)->toBe(1000.00)
        ->and($shares[$this->user->id])->toBe(500.0)
        ->and($shares[$maria->id])->toBe(500.0);
});

test('editing a request replaces its charged users', function () {
    $maria = fundMember($this->workspace);

    $this->actingAs($this->user)->post($this->url, fundRequestPayload([
        'charge_to' => [['user_id' => $this->user->id]],
    ]));

    $request = FundRequest::first();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", fundRequestPayload([
            'charge_to' => [['user_id' => $maria->id]],
        ]))
        ->assertRedirect();

    $charged = $request->refresh()->chargeToUsers;

    expect($charged)->toHaveCount(1)
        ->and($charged->first()->id)->toBe($maria->id)
        ->and((float) $charged->first()->pivot->amount)->toBe(900.0);
});
