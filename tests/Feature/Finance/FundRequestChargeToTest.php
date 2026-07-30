<?php

use App\Models\Department;
use App\Models\User;
use App\Models\Workspace;
use Modules\Finance\Models\FundRequest;
use Modules\Finance\Models\TransactionType;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->workspace = Workspace::factory()->create(['owner_id' => $this->user->id]);
    $this->url = "/workspaces/{$this->workspace->slug}/finance/request-funds";
});

/**
 * A blank request payload with everything but the charge-to rows filled in. The
 * date and requester are stamped by the server, so they are not sent.
 */
function fundRequestPayload(array $attrs = []): array
{
    return array_merge([
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

    $this->assertDatabaseHas('finance_fund_request_user_shares', [
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

test('the date and requester are stamped from creation, not the client', function () {
    $other = fundMember($this->workspace);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            // A client-supplied date and requester are ignored.
            'request_date' => '2020-01-01',
            'requested_by' => $other->id,
            'charge_to' => [['user_id' => $this->user->id]],
        ]))
        ->assertRedirect();

    $request = FundRequest::first();

    expect($request->requested_by)->toBe($this->user->id)
        ->and($request->request_date->toDateString())->toBe(now()->toDateString());
});

test('a request stores its transaction type and department', function () {
    $type = TransactionType::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'expenses',
    ]);
    $department = Department::create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Marketing',
        'is_active' => true,
    ]);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'transaction_type_id' => $type->id,
            'department_id' => $department->id,
            'charge_to' => [['user_id' => $this->user->id]],
        ]))
        ->assertRedirect();

    $request = FundRequest::first();

    expect($request->transaction_type_id)->toBe($type->id)
        ->and($request->department_id)->toBe($department->id);
});

test('a type or department from another workspace is rejected', function () {
    $otherWorkspace = Workspace::factory()->create();
    $foreignType = TransactionType::create([
        'workspace_id' => $otherWorkspace->id,
        'name' => 'expenses',
    ]);

    $this->actingAs($this->user)
        ->post($this->url, fundRequestPayload([
            'transaction_type_id' => $foreignType->id,
            'charge_to' => [['user_id' => $this->user->id]],
        ]))
        ->assertSessionHasErrors('transaction_type_id');

    expect(FundRequest::count())->toBe(0);
});
