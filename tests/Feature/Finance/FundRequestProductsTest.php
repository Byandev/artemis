<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";

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

/** A blank request charged wholly to the owner, ready for product rows. */
function productRequestPayload(array $attrs = []): array
{
    return array_merge([
        'template' => 'blank',
        'request_date' => '2026-05-14',
        'requested_by' => test()->user->id,
        'charge_to' => [['user_id' => test()->user->id]],
        'purpose' => 'Stock replenishment',
        'amount_requested' => 900,
    ], $attrs);
}

test('one product bears the whole amount and keeps a name snapshot', function () {
    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload([
            'products' => [['product_id' => $this->widget->id]],
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('finance_request_fund_products', [
        'fund_request_id' => FundRequest::first()->id,
        'product_id' => $this->widget->id,
        'product_label' => 'Widget',
        'amount' => 900,
    ]);
});

test('blank shares split the amount evenly between the products', function () {
    $sprocket = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'owner_id' => $this->user->id,
        'name' => 'Sprocket',
    ]);

    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload([
            'amount_requested' => 100,
            'products' => [
                ['product_id' => $this->widget->id],
                ['product_id' => $this->gadget->id],
                ['product_id' => $sprocket->id],
            ],
        ]))
        ->assertRedirect();

    // The odd centavos go to the first product rather than vanishing.
    $shares = FundRequest::first()->productShares
        ->pluck('amount', 'product_label')
        ->map(fn ($a) => (float) $a);

    expect($shares['Widget'])->toBe(33.34)
        ->and($shares['Gadget'])->toBe(33.33)
        ->and($shares['Sprocket'])->toBe(33.33)
        ->and(round($shares->sum(), 2))->toBe(100.0);
});

test('product shares can be set by hand when the split is not even', function () {
    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload([
            'products' => [
                ['product_id' => $this->widget->id, 'amount' => 600],
                ['product_id' => $this->gadget->id, 'amount' => 300],
            ],
        ]))
        ->assertRedirect();

    $shares = FundRequest::first()->productShares
        ->pluck('amount', 'product_label')
        ->map(fn ($a) => (float) $a);

    expect($shares['Widget'])->toBe(600.0)
        ->and($shares['Gadget'])->toBe(300.0);
});

test('product shares that do not add up to the amount are rejected', function () {
    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload([
            'products' => [
                ['product_id' => $this->widget->id, 'amount' => 100],
                ['product_id' => $this->gadget->id, 'amount' => 200],
            ],
        ]))
        ->assertSessionHasErrors('products');

    expect(FundRequest::count())->toBe(0);
});

test('a product from another workspace is rejected', function () {
    $stranger = User::factory()->create();
    $foreign = Product::factory()->create([
        'workspace_id' => Workspace::factory()->create(['owner_id' => $stranger->id])->id,
        'owner_id' => $stranger->id,
    ]);

    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload([
            'products' => [['product_id' => $foreign->id]],
        ]))
        ->assertSessionHasErrors('products.0.product_id');

    expect(FundRequest::count())->toBe(0);
});

test('the same product cannot be listed twice', function () {
    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload([
            'products' => [
                ['product_id' => $this->widget->id, 'amount' => 500],
                ['product_id' => $this->widget->id, 'amount' => 400],
            ],
        ]))
        ->assertSessionHasErrors('products.0.product_id');

    expect(FundRequest::count())->toBe(0);
});

test('products are optional — a request with none saves fine', function () {
    $this->actingAs($this->user)
        ->post($this->url, productRequestPayload())
        ->assertRedirect();

    expect(FundRequest::first()->productShares)->toHaveCount(0);
});

test('deleting the product leaves the row readable by its label', function () {
    $this->actingAs($this->user)->post($this->url, productRequestPayload([
        'products' => [['product_id' => $this->widget->id]],
    ]));

    $this->widget->delete();

    $share = FundRequest::first()->productShares->first();

    expect($share->product_id)->toBeNull()
        ->and($share->product_label)->toBe('Widget')
        ->and((float) $share->amount)->toBe(900.0);
});

test('editing a request replaces its product shares', function () {
    $this->actingAs($this->user)->post($this->url, productRequestPayload([
        'products' => [['product_id' => $this->widget->id]],
    ]));

    $request = FundRequest::first();

    $this->actingAs($this->user)
        ->put("{$this->url}/{$request->id}", productRequestPayload([
            'products' => [['product_id' => $this->gadget->id]],
        ]))
        ->assertRedirect();

    $shares = $request->refresh()->productShares;

    expect($shares)->toHaveCount(1)
        ->and($shares->first()->product_label)->toBe('Gadget');
});
