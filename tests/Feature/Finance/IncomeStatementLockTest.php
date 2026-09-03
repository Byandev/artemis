<?php

use Modules\Finance\Models\IncomeStatement;
use Modules\GencysERP\Models\GencysDailySalesOrder;

/**
 * Locking a month.
 *
 * A statement is a snapshot of data that keeps moving, so anything that would
 * rewrite it — regenerate, a save over the top, deleting it, a loss carryover —
 * stops at a locked statement until someone deliberately unlocks.
 */
function lock_url($workspace, string $path = ''): string
{
    return "/workspaces/{$workspace->slug}/finance/income-statements{$path}";
}

/** A delivered May order. Delivered revenue is all these tests need to move. */
function lock_order($workspace, float $amount, string $day): void
{
    static $id = 900000;

    GencysDailySalesOrder::create([
        'id' => $id++,
        'workspace_id' => $workspace->id,
        'parcel_status' => 'DELIVERED',
        'parcel_updated_date' => "2026-05-{$day} 09:00:00",
        'shipped_out_date' => "2026-05-{$day}",
        'price_final' => $amount,
        'shipping_fee' => 0,
    ]);
}

/** May 2026 seeded at 10,000 delivered, then saved. */
function lock_saved_may($user, $workspace): IncomeStatement
{
    // The flag selects the order source, so gencys fixtures need it set.
    $workspace->update(['is_gencys_partner' => true]);

    lock_order($workspace, 6000, '10');
    lock_order($workspace, 4000, '20');

    test()->actingAs($user)
        ->post(lock_url($workspace), ['month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12])
        ->assertRedirect();

    return IncomeStatement::where('workspace_id', $workspace->id)->firstOrFail();
}

function lock_it($user, $workspace, IncomeStatement $statement): void
{
    test()->actingAs($user)
        ->post(lock_url($workspace, "/{$statement->id}/lock"))
        ->assertRedirect();
}

test('locking stamps when the month was closed and who closed it', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);

    expect($statement->isLocked())->toBeFalse();

    lock_it($user, $workspace, $statement);

    $statement->refresh();
    expect($statement->isLocked())->toBeTrue()
        ->and($statement->locked_by)->toBe($user->id);
});

test('a locked statement cannot be regenerated', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);
    lock_it($user, $workspace, $statement);

    // A late order lands — exactly the change a lock exists to keep out.
    lock_order($workspace, 5000, '28');

    $this->actingAs($user)
        ->post(lock_url($workspace, "/{$statement->id}/regenerate"))
        ->assertRedirect()
        ->assertSessionHas('error');

    expect((float) $statement->fresh()->total_delivered)->toBe(10000.0);
});

test('a locked statement cannot be saved over', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);
    $generatedAt = $statement->generated_at;
    lock_it($user, $workspace, $statement);

    lock_order($workspace, 5000, '28');

    $this->actingAs($user)
        ->post(lock_url($workspace), ['month' => '2026-05', 'cod_rate' => 0.02, 'vat_rate' => 0.12])
        ->assertRedirect()
        ->assertSessionHas('error');

    $statement->refresh();
    expect((float) $statement->total_delivered)->toBe(10000.0)
        ->and($statement->generated_at->toIso8601String())->toBe($generatedAt->toIso8601String());
});

test('a locked statement cannot be deleted', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);
    lock_it($user, $workspace, $statement);

    $this->actingAs($user)
        ->delete(lock_url($workspace, "/{$statement->id}"))
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseHas('finance_income_statements', ['id' => $statement->id]);
});

test('a locked statement refuses a loss carryover, which would re-snapshot its slices', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);
    lock_it($user, $workspace, $statement);

    $this->actingAs($user)
        ->post(lock_url($workspace, "/{$statement->id}/loss-carryovers"), ['user_id' => null, 'amount' => 500])
        ->assertRedirect()
        ->assertSessionHas('error');

    $this->assertDatabaseCount('finance_income_loss_carryovers', 0);
});

test('unlocking reopens the month for regenerating', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);
    lock_it($user, $workspace, $statement);

    $this->actingAs($user)
        ->delete(lock_url($workspace, "/{$statement->id}/lock"))
        ->assertRedirect();

    $statement->refresh();
    expect($statement->isLocked())->toBeFalse()
        ->and($statement->locked_by)->toBeNull();

    lock_order($workspace, 5000, '28');

    $this->actingAs($user)
        ->post(lock_url($workspace, "/{$statement->id}/regenerate"))
        ->assertRedirect();

    expect((float) $statement->fresh()->total_delivered)->toBe(15000.0);
});

test('locking an already-locked statement keeps who closed it first', function () {
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $statement = lock_saved_may($user, $workspace);

    lock_it($user, $workspace, $statement);
    $first = $statement->fresh()->locked_at;

    lock_it($user, $workspace, $statement);

    expect($statement->fresh()->locked_at->toIso8601String())->toBe($first->toIso8601String());
});
