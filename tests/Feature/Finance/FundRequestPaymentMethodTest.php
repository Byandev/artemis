<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

/** A request charged to the signed-in user, with the given payment fields. */
function paymentPayload(array $payment): array
{
    return [
        'particulars' => [['name' => 'Item', 'quantity' => 1, 'unit_price' => 500]],
        'charge_to' => [['user_id' => test()->user->id]],
        ...$payment,
    ];
}

$account = ['bank_name' => 'BDO', 'account_name' => 'Juan dela Cruz', 'account_number' => '001234567890'];

test('a request needs a known payment method', function (?string $method) {
    $this->actingAs($this->user)
        ->post($this->url, paymentPayload(['payment_method' => $method]))
        ->assertSessionHasErrors('payment_method');

    expect(FundRequest::count())->toBe(0);
})->with([null, 'bitcoin']);

test('online banking and e-wallets need the account details', function (string $method) {
    $this->actingAs($this->user)
        ->post($this->url, paymentPayload(['payment_method' => $method]))
        ->assertSessionHasErrors(['bank_name', 'account_name', 'account_number']);

    expect(FundRequest::count())->toBe(0);
})->with(['online_banking', 'e_wallet']);

test('online banking and e-wallets store the account details', function (string $method) use ($account) {
    $this->actingAs($this->user)
        ->post($this->url, paymentPayload(['payment_method' => $method, ...$account]))
        ->assertSessionHasNoErrors();

    expect(FundRequest::sole()->only(['payment_method', 'bank_name', 'account_name', 'account_number']))
        ->toBe(['payment_method' => $method, ...$account]);
})->with(['online_banking', 'e_wallet']);

test('cheque and cash need no account, and drop any sent', function (string $method) use ($account) {
    $this->actingAs($this->user)
        ->post($this->url, paymentPayload(['payment_method' => $method, ...$account]))
        ->assertSessionHasNoErrors();

    expect(FundRequest::sole()->only(['payment_method', 'bank_name', 'account_name', 'account_number']))
        ->toBe(['payment_method' => $method, 'bank_name' => null, 'account_name' => null, 'account_number' => null]);
})->with(['cheque', 'cash']);

test('switching to cash clears the saved account', function () use ($account) {
    $this->actingAs($this->user)->post($this->url, paymentPayload(['payment_method' => 'online_banking', ...$account]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", paymentPayload(['payment_method' => 'cash']))
        ->assertSessionHasNoErrors();

    expect($request->fresh()->account_number)->toBeNull();
});

test('the form carries the payment methods, and the edit page the saved account', function () use ($account) {
    $this->actingAs($this->user)->post($this->url, paymentPayload(['payment_method' => 'e_wallet', ...$account]));
    $request = FundRequest::sole();

    $this->actingAs($this->user)
        ->get("{$this->url}/{$request->id}/edit")
        ->assertInertia(fn ($p) => $p
            ->has('paymentMethods', 4)
            ->where('paymentMethods.0', ['value' => 'online_banking', 'label' => 'Online Banking', 'needs_account' => true])
            ->where('paymentMethods.3.needs_account', false)
            ->where('requestFund.payment_method', 'e_wallet')
            ->where('requestFund.account_number', '001234567890'));
});
