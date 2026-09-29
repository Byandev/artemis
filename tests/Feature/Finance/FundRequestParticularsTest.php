<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

/** A request charged to the signed-in user, with the given particulars. */
function particularsPayload(array $particulars, array $extra = []): array
{
    return [
        'particulars' => $particulars,
        'charge_to' => [['user_id' => test()->user->id]],
        ...$extra,
    ];
}

test('particulars are stored in order, each amount being quantity × unit price', function () {
    $this->actingAs($this->user)
        ->post($this->url, particularsPayload([
            ['name' => 'Bond paper', 'quantity' => 3, 'unit_price' => 250.5],
            ['name' => 'Ink', 'quantity' => 1.5, 'unit_price' => 100],
        ]))
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();
    $particulars = $request->particulars;

    expect($particulars)->toHaveCount(2)
        ->and($particulars[0]->name)->toBe('Bond paper')
        ->and((float) $particulars[0]->amount)->toBe(751.5)
        ->and($particulars[1]->name)->toBe('Ink')
        ->and((float) $particulars[1]->amount)->toBe(150.0);
});

test('the amount requested is the particulars\' total, whatever the client sends', function () {
    $this->actingAs($this->user)
        ->post($this->url, particularsPayload([
            ['name' => 'Bond paper', 'quantity' => 2, 'unit_price' => 100, 'amount' => 1],
            ['name' => 'Ink', 'quantity' => 1, 'unit_price' => 50],
        ], ['amount_requested' => 99999]))
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();

    expect((float) $request->amount_requested)->toBe(250.0)
        ->and((float) $request->particulars[0]->amount)->toBe(200.0)
        ->and((float) $request->chargeToUsers()->sole()->pivot->amount)->toBe(250.0);
});

test('a request needs at least one particular', function () {
    $this->actingAs($this->user)
        ->post($this->url, particularsPayload([]))
        ->assertSessionHasErrors('particulars');

    expect(FundRequest::count())->toBe(0);
});

test('every particular needs a name, a positive quantity and a unit price', function () {
    $this->actingAs($this->user)
        ->post($this->url, particularsPayload([
            ['name' => '', 'quantity' => 0, 'unit_price' => -1],
        ]))
        ->assertSessionHasErrors(['particulars.0.name', 'particulars.0.quantity', 'particulars.0.unit_price']);

    expect(FundRequest::count())->toBe(0);
});

test('an edit replaces the particulars and re-totals the request', function () {
    $this->actingAs($this->user)->post($this->url, particularsPayload([
        ['name' => 'Bond paper', 'quantity' => 1, 'unit_price' => 100],
        ['name' => 'Ink', 'quantity' => 1, 'unit_price' => 50],
    ]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", particularsPayload([
            ['name' => 'Toner', 'quantity' => 2, 'unit_price' => 400],
        ]))
        ->assertSessionHasNoErrors();

    $request->refresh();

    expect($request->particulars)->toHaveCount(1)
        ->and($request->particulars[0]->name)->toBe('Toner')
        ->and((float) $request->amount_requested)->toBe(800.0);
});

test('the edit page carries the particulars', function () {
    $this->actingAs($this->user)->post($this->url, particularsPayload([
        ['name' => 'Bond paper', 'quantity' => 2, 'unit_price' => 100],
    ]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->get("{$this->url}/{$request->id}/edit")
        ->assertInertia(fn ($p) => $p
            ->where('requestFund.particulars.0.name', 'Bond paper')
            ->where('requestFund.particulars.0.amount', '200.00'));
});

test('deleting a request deletes its particulars', function () {
    $this->actingAs($this->user)->post($this->url, particularsPayload([
        ['name' => 'Bond paper', 'quantity' => 1, 'unit_price' => 100],
    ]));

    $this->actingAs($this->user)->delete("{$this->url}/".FundRequest::sole()->id);

    $this->assertDatabaseCount('finance_fund_request_particulars', 0);
});
