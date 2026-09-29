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

    $this->adSpent = TransactionType::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Ad Spent',
        'nature' => 'debit',
        'fund_requestable' => true,
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

/** An ad-spend request charged to the signed-in user, with the given particulars. */
function adSpentPayload(array $particulars, array $extra = []): array
{
    return [
        'transaction_type_id' => test()->adSpent->id,
        'payment_method' => 'cash',
        'particulars' => $particulars,
        'charge_to' => [['user_id' => test()->user->id]],
        ...$extra,
    ];
}

test('ad-spend particulars are each for a product, named after it', function () {
    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload([
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

test('the product shares of an ad-spend request are its particulars summed per product', function () {
    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload([
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

test('every ad-spend particular needs a product of this workspace', function () {
    $foreign = Product::factory()->create([
        'workspace_id' => Workspace::factory()->create(['owner_id' => $this->user->id])->id,
        'owner_id' => $this->user->id,
    ]);

    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload([
            ['name' => 'Typed name', 'quantity' => 1, 'unit_price' => 100],
            ['product_id' => $foreign->id, 'quantity' => 1, 'unit_price' => 100],
        ]))
        ->assertSessionHasErrors(['particulars.0.product_id', 'particulars.1.product_id']);

    expect(FundRequest::count())->toBe(0);
});

test('other types keep typed particulars and drop any product', function () {
    $supplies = TransactionType::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Office Supplies',
        'nature' => 'debit',
        'fund_requestable' => true,
    ]);

    $this->actingAs($this->user)
        ->post($this->url, adSpentPayload([
            ['name' => 'Bond paper', 'product_id' => $this->widget->id, 'quantity' => 1, 'unit_price' => 100],
        ], ['transaction_type_id' => $supplies->id]))
        ->assertSessionHasNoErrors();

    $request = FundRequest::sole();

    expect($request->particulars[0]->name)->toBe('Bond paper')
        ->and($request->particulars[0]->product_id)->toBeNull()
        ->and($request->productShares)->toHaveCount(0);
});

test('the form is told which types are ad spend', function () {
    $this->actingAs($this->user)
        ->get("{$this->url}/create")
        ->assertInertia(fn ($p) => $p->where('adSpentTypeIds', [$this->adSpent->id]));
});
