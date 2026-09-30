<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

/** A request charged to the signed-in user, with the given liquidation fields. */
function liquidationPayload(array $extra = []): array
{
    return [
        'payment_method' => 'cash',
        'particulars' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 500]],
        'charge_to' => [['user_id' => test()->user->id]],
        ...$extra,
    ];
}

test('a request needs no liquidation by default', function () {
    $this->actingAs($this->user)
        ->post($this->url, liquidationPayload())
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();

    expect($request->liquidation_required)->toBeFalse()
        ->and($request->liquidation_deadline)->toBeNull();
});

test('a request requiring liquidation stores its deadline', function () {
    $this->actingAs($this->user)
        ->post($this->url, liquidationPayload([
            'liquidation_required' => true,
            'liquidation_deadline' => '2026-10-15',
        ]))
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();

    expect($request->liquidation_required)->toBeTrue()
        ->and($request->liquidation_deadline->toDateString())->toBe('2026-10-15');
});

test('requiring liquidation needs a deadline', function () {
    $this->actingAs($this->user)
        ->post($this->url, liquidationPayload(['liquidation_required' => true]))
        ->assertSessionHasErrors('liquidation_deadline');

    expect(FundRequest::count())->toBe(0);
});

test('a deadline is dropped when liquidation is not required', function () {
    $this->actingAs($this->user)
        ->post($this->url, liquidationPayload([
            'liquidation_required' => true,
            'liquidation_deadline' => '2026-10-15',
        ]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", liquidationPayload([
            'liquidation_required' => false,
            'liquidation_deadline' => '2026-10-15',
        ]))
        ->assertSessionHasNoErrors();

    $request->refresh();

    expect($request->liquidation_required)->toBeFalse()
        ->and($request->liquidation_deadline)->toBeNull();
});

test('the edit page carries the liquidation fields', function () {
    $this->actingAs($this->user)->post($this->url, liquidationPayload([
        'liquidation_required' => true,
        'liquidation_deadline' => '2026-10-15',
    ]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->get("{$this->url}/{$request->id}/edit")
        ->assertInertia(fn ($p) => $p
            ->where('requestFund.liquidation_required', true)
            ->where('requestFund.liquidation_deadline', '2026-10-15'));
});
