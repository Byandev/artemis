<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\TransactionType;
use Modules\Products\Models\Product;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";

    $this->perProduct = TransactionType::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Ad Spent',
        'nature' => 'debit',
        'fund_requestable' => true,
        'fund_requestable_per_product' => true,
    ]);

    $this->widget = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'owner_id' => $this->user->id,
        'name' => 'Widget',
    ]);
    $this->gadget = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'owner_id' => $this->user->id,
        'name' => 'Gadget',
    ]);
});

/** A request of the per-product type charged to the signed-in user, with the given particulars. */
function perProductPayload(array $particulars, array $extra = []): array
{
    return [
        'transaction_type_id' => test()->perProduct->id,
        'payment_method' => 'cash',
        'particulars' => $particulars,
        'charge_to' => [['user_id' => test()->user->id]],
        ...$extra,
    ];
}

test('per-product particulars are each for a product, named after it', function () {
    $this->actingAs($this->user)
        ->post($this->url, perProductPayload([
            ['product_id' => $this->widget->id, 'quantity' => 1, 'unit_price' => 500],
            ['product_id' => $this->gadget->id, 'quantity' => 2, 'unit_price' => 100],
        ]))
        ->assertSessionHasNoErrors();

    $particulars = FundRequest::sole()->particulars;

    expect($particulars[0]->product_id)->toBe($this->widget->id)
        ->and($particulars[0]->name)->toBe('Widget')
        ->and($particulars[1]->product_id)->toBe($this->gadget->id)
        ->and($particulars[1]->name)->toBe('Gadget');
});

test('the product shares of a per-product request are its particulars summed per product', function () {
    $this->actingAs($this->user)
        ->post($this->url, perProductPayload([
            ['product_id' => $this->widget->id, 'quantity' => 1, 'unit_price' => 500],
            ['product_id' => $this->gadget->id, 'quantity' => 2, 'unit_price' => 100],
            ['product_id' => $this->widget->id, 'quantity' => 1, 'unit_price' => 300],
        ], [
            // Whatever the client sends is ignored.
            'products' => [['product_id' => $this->gadget->id, 'amount' => 1000]],
        ]))
        ->assertSessionHasNoErrors();

    $shares = FundRequest::sole()->productShares;

    expect($shares)->toHaveCount(2)
        ->and($shares[0]->product_id)->toBe($this->widget->id)
        ->and($shares[0]->product_label)->toBe('Widget')
        ->and((float) $shares[0]->amount)->toBe(800.0)
        ->and($shares[1]->product_id)->toBe($this->gadget->id)
        ->and((float) $shares[1]->amount)->toBe(200.0);
});

test('every per-product particular needs a product of this workspace', function () {
    $foreign = Product::factory()->create([
        'workspace_id' => Workspace::factory()->create(['owner_id' => $this->user->id])->id,
        'owner_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post($this->url, perProductPayload([
            ['name' => 'Typed name', 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $foreign->id, 'quantity' => 1, 'unit_price' => 100],
        ]))
        ->assertSessionHasErrors(['particulars.0.product_id', 'particulars.1.product_id']);

    expect(FundRequest::count())->toBe(0);
});

test('a type without the flag keeps typed particulars and drops any product, whatever its name', function () {
    $this->perProduct->update(['fund_requestable_per_product' => false]);

    $this->actingAs($this->user)
        ->post($this->url, perProductPayload([
            ['name' => 'Bond paper', 'product_id' => $this->widget->id, 'quantity' => 1, 'unit_price' => 100],
        ]))
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();

    expect($request->particulars[0]->name)->toBe('Bond paper')
        ->and($request->particulars[0]->product_id)->toBeNull()
        ->and($request->productShares)->toHaveCount(0);
});

test('the form is told which types are per product', function () {
    $this->actingAs($this->user)
        ->get("{$this->url}/create")
        ->assertInertia(fn ($p) => $p
            ->where('transactionTypes.0.id', $this->perProduct->id)
            ->where('transactionTypes.0.fund_requestable_per_product', true));
});

test('the flag is set on the transaction type, and only kept on a requestable one', function () {
    $url = "/workspaces/{$this->workspace->slug}/finance/transaction-types";

    $this->actingAs($this->user)
        ->post($url, ['name' => 'Boosting', 'nature' => 'debit', 'fund_requestable' => true, 'fund_requestable_per_product' => true])
        ->assertSessionHasNoErrors();
    $this->actingAs($this->user)
        ->post($url, ['name' => 'Rent', 'nature' => 'debit', 'fund_requestable' => false, 'fund_requestable_per_product' => true])
        ->assertSessionHasNoErrors();

    expect(TransactionType::where('name', 'Boosting')->sole()->fund_requestable_per_product)->toBeTrue()
        ->and(TransactionType::where('name', 'Rent')->sole()->fund_requestable_per_product)->toBeFalse();

    $boosting = TransactionType::where('name', 'Boosting')->sole();

    $this->actingAs($this->user)
        ->put("{$url}/{$boosting->id}", ['name' => 'Boosting', 'nature' => 'debit', 'fund_requestable' => true, 'fund_requestable_per_product' => false])
        ->assertSessionHasNoErrors();

    expect($boosting->fresh()->fund_requestable_per_product)->toBeFalse();
});
