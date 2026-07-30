<?php

use App\Models\User;
use Modules\Finance\Models\Account;
use Modules\Finance\Models\Transaction;

function ctUrl($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/transactions{$path}";
}

/** A transaction payload with everything but the charge-to rows filled in. */
function ctPayload($account, array $attrs = []): array
{
    return array_merge([
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Team lunch',
        'type' => 'out',
        'amount' => 900,
    ], $attrs);
}

test('a transaction can be charged to one user, who bears the whole amount', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($user)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [['user_id' => $user->id, 'amount' => null]],
        ]))
        ->assertRedirect();

    $this->assertDatabaseHas('finance_transaction_charge_to', [
        'transaction_id' => Transaction::first()->id,
        'user_id' => $user->id,
        'amount' => 900,
    ]);
});

test('blank shares split the amount evenly between the charged users', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);
    $cy = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [
                ['user_id' => $owner->id],
                ['user_id' => $ben->id],
                ['user_id' => $cy->id],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$owner->id])->toBe(300.0)
        ->and($shares[$ben->id])->toBe(300.0)
        ->and($shares[$cy->id])->toBe(300.0);
});

test('an uneven split keeps every centavo, the odd ones going to the first user', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);
    $cy = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'amount' => 100,
            'charge_to' => [
                ['user_id' => $owner->id],
                ['user_id' => $ben->id],
                ['user_id' => $cy->id],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$owner->id])->toBe(33.34)
        ->and($shares[$ben->id])->toBe(33.33)
        ->and($shares[$cy->id])->toBe(33.33)
        ->and(round($shares->sum(), 2))->toBe(100.0);
});

test('shares can be set by hand when the split is not even', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [
                ['user_id' => $owner->id, 'amount' => 600],
                ['user_id' => $ben->id, 'amount' => 300],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$owner->id])->toBe(600.0)
        ->and($shares[$ben->id])->toBe(300.0);
});

test('a blank share takes whatever the explicit shares left over', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [
                ['user_id' => $owner->id, 'amount' => 700],
                ['user_id' => $ben->id],
            ],
        ]))
        ->assertRedirect();

    $shares = Transaction::first()->chargeToUsers
        ->pluck('pivot.amount', 'id')
        ->map(fn ($a) => (float) $a);

    expect($shares[$ben->id])->toBe(200.0);
});

test('shares that do not add up to the amount are rejected', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [
                ['user_id' => $owner->id, 'amount' => 100],
                ['user_id' => $ben->id, 'amount' => 100],
            ],
        ]))
        ->assertSessionHasErrors('charge_to');

    $this->assertDatabaseCount('finance_transaction_charge_to', 0);
});

test('the same user cannot be charged twice on one transaction', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [
                ['user_id' => $owner->id, 'amount' => 450],
                ['user_id' => $owner->id, 'amount' => 450],
            ],
        ]))
        ->assertSessionHasErrors('charge_to.0.user_id');
});

test('a user outside the workspace cannot be charged', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $outsider = User::factory()->create();

    $this->actingAs($owner)
        ->post(ctUrl($workspace), ctPayload($account, [
            'charge_to' => [['user_id' => $outsider->id]],
        ]))
        ->assertSessionHasErrors('charge_to.0.user_id');
});

test('updating replaces the charged users and their shares', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Team lunch',
        'type' => 'out',
        'amount' => 900,
    ]);
    $txn->chargeToUsers()->sync([$owner->id => ['amount' => 900]]);

    $this->actingAs($owner)
        ->put(ctUrl($workspace, "/{$txn->id}"), ctPayload($account, [
            'charge_to' => [['user_id' => $ben->id]],
        ]))
        ->assertRedirect();

    $this->assertDatabaseMissing('finance_transaction_charge_to', [
        'transaction_id' => $txn->id,
        'user_id' => $owner->id,
    ]);
    $this->assertDatabaseHas('finance_transaction_charge_to', [
        'transaction_id' => $txn->id,
        'user_id' => $ben->id,
        'amount' => 900,
    ]);
});

test('clearing the charge-to list drops every share', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Team lunch',
        'type' => 'out',
        'amount' => 900,
    ]);
    $txn->chargeToUsers()->sync([$owner->id => ['amount' => 900]]);

    $this->actingAs($owner)
        ->put(ctUrl($workspace, "/{$txn->id}"), ctPayload($account))
        ->assertRedirect();

    $this->assertDatabaseCount('finance_transaction_charge_to', 0);
});

test('the edit page ships the charged users with their shares', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $account = Account::create(['workspace_id' => $workspace->id, 'name' => 'Cash']);
    $ben = makeWorkspaceMember($workspace);

    $txn = Transaction::create([
        'workspace_id' => $workspace->id,
        'account_id' => $account->id,
        'date' => '2026-05-10',
        'description' => 'Team lunch',
        'type' => 'out',
        'amount' => 900,
    ]);
    $txn->chargeToUsers()->sync([
        $owner->id => ['amount' => 600],
        $ben->id => ['amount' => 300],
    ]);

    $this->actingAs($owner)
        ->get(ctUrl($workspace, "/{$txn->id}/edit"))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('transaction.charge_to_users', 2)
            ->where('transaction.charge_to_users', fn ($rows) => collect($rows)->contains(
                fn ($r) => $r['id'] === $ben->id && (float) $r['pivot']['amount'] === 300.0
            ))
        );
});
