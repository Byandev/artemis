<?php

use App\Models\Product;
use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\Transaction;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->account = Account::create(['workspace_id' => $this->workspace->id, 'name' => 'Cash']);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/transactions";
});

/** An approved request in the given workspace, charged wholly to one user. */
function approvedRequest(Workspace $workspace, User $user, array $attrs = []): FundRequest
{
    $request = FundRequest::create(array_merge([
        'workspace_id' => $workspace->id,
        'template' => 'blank',
        'request_date' => '2026-05-10',
        'reference_no' => 'RF-00001',
        'requested_by' => $user->id,
        'purpose' => 'Office supplies',
        'amount_requested' => 900,
        'status' => 'approved',
    ], $attrs));

    $request->chargeToUsers()->sync([$user->id => ['amount' => $request->amount_requested]]);

    return $request;
}

/** A transaction payload with everything but the fund request filled in. */
function txnPayload(Account $account, array $attrs = []): array
{
    return array_merge([
        'account_id' => $account->id,
        'date' => '2026-05-12',
        'description' => 'Supplies',
        'type' => 'out',
        'amount' => 900,
    ], $attrs);
}

test('a transaction can reference a fund request', function () {
    $request = approvedRequest($this->workspace, $this->user);

    $this->actingAs($this->user)
        ->post($this->url, txnPayload($this->account, ['fund_request_id' => $request->id]))
        ->assertRedirect();

    expect(Transaction::first()->fund_request_id)->toBe($request->id);
});

test('the reference is optional', function () {
    $this->actingAs($this->user)
        ->post($this->url, txnPayload($this->account))
        ->assertRedirect();

    expect(Transaction::first()->fund_request_id)->toBeNull();
});

test('a fund request from another workspace cannot be attached', function () {
    $stranger = User::factory()->create();
    $foreign = approvedRequest(
        Workspace::factory()->create(['owner_id' => $stranger->id]),
        $stranger,
    );

    $this->actingAs($this->user)
        ->post($this->url, txnPayload($this->account, ['fund_request_id' => $foreign->id]))
        ->assertSessionHasErrors('fund_request_id');

    expect(Transaction::count())->toBe(0);
});

test('deleting the fund request leaves the transaction with an empty link', function () {
    $request = approvedRequest($this->workspace, $this->user);

    $this->actingAs($this->user)
        ->post($this->url, txnPayload($this->account, ['fund_request_id' => $request->id]));

    $request->delete();

    expect(Transaction::first()->fund_request_id)->toBeNull();
});

test('several transactions may settle one request — the link is loose', function () {
    $request = approvedRequest($this->workspace, $this->user);

    foreach ([600, 300] as $amount) {
        $this->actingAs($this->user)
            ->post($this->url, txnPayload($this->account, [
                'amount' => $amount,
                'fund_request_id' => $request->id,
            ]))
            ->assertRedirect();
    }

    expect($request->transactions()->count())->toBe(2);
});

test('the form offers approved requests with the shares it fills in from', function () {
    $product = Product::factory()->create([
        'workspace_id' => $this->workspace->id,
        'owner_id' => $this->user->id,
        'name' => 'Widget',
    ]);

    $request = approvedRequest($this->workspace, $this->user);
    $request->productShares()->create([
        'product_id' => $product->id,
        'product_label' => 'Widget',
        'amount' => 900,
        'sort_order' => 0,
    ]);

    // A pending request is not something to settle yet, so it stays out.
    approvedRequest($this->workspace, $this->user, [
        'reference_no' => 'RF-00002',
        'status' => 'pending',
    ]);

    $this->actingAs($this->user)
        ->get("{$this->url}/create")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/finance/transactions/create')
            ->has('fundRequests', 1)
            ->where('fundRequests.0.reference_no', 'RF-00001')
            ->where('fundRequests.0.amount_requested', fn ($v) => (float) $v === 900.0)
            ->where('fundRequests.0.charge_to.0.user_id', $this->user->id)
            ->where('fundRequests.0.charge_to.0.amount', fn ($v) => (float) $v === 900.0)
            ->where('fundRequests.0.products.0.product_label', 'Widget')
            ->etc()
        );
});

test('the product picker offers a tag already saved on the entry being edited', function () {
    $this->actingAs($this->user)->post($this->url, txnPayload($this->account, [
        // A catalog name pulled from a fund request, which the Gencys
        // suggestions need not contain.
        'products' => [['product' => 'Happy Heart Gel', 'amount' => 900]],
    ]));

    $transaction = Transaction::first();

    $this->actingAs($this->user)
        ->get("{$this->url}/{$transaction->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('products', fn ($products) => collect($products)->contains('Happy Heart Gel'))
            ->etc()
        );
});
