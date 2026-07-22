<?php

use App\Models\User;
use App\Models\Workspace;
use Modules\SimGateway\Enums\SimStatus;
use Modules\SimGateway\Models\Sim;

it('lists SIMs for a super admin', function () {
    $admin = User::factory()->superAdmin()->create();
    $sim = Sim::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.sims.index'))
        ->assertOk();

    expect(Sim::whereKey($sim->id)->exists())->toBeTrue();
});

it('forbids a non-admin from managing SIMs', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.sims.index'))
        ->assertRedirect(route('dashboard'));
});

it('creates a SIM assigned to a workspace and stamps activation when active', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($admin)
        ->post(route('admin.sims.store'), [
            'workspace_id' => $workspace->id,
            'phone_number' => '09171234567',
            'carrier' => 'globe',
            'port_number' => 7,
            'label' => 'Support line',
            'status' => SimStatus::Active->value,
            'notes' => null,
        ])
        ->assertRedirect(route('admin.sims.index'));

    $sim = Sim::where('phone_number', '09171234567')->first();
    expect($sim)->not->toBeNull()
        ->and($sim->workspace_id)->toBe($workspace->id)
        ->and($sim->status)->toBe(SimStatus::Active)
        ->and($sim->activated_at)->not->toBeNull();
});

it('rejects a duplicate hardware port', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();
    Sim::factory()->create(['port_number' => 3]);

    $this->actingAs($admin)
        ->from(route('admin.sims.create'))
        ->post(route('admin.sims.store'), [
            'workspace_id' => $workspace->id,
            'phone_number' => '09181234567',
            'carrier' => 'smart',
            'port_number' => 3,
            'status' => SimStatus::Received->value,
        ])
        ->assertSessionHasErrors('port_number');
});

it('rejects an invalid Philippine number', function () {
    $admin = User::factory()->superAdmin()->create();
    $workspace = Workspace::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.sims.create'))
        ->post(route('admin.sims.store'), [
            'workspace_id' => $workspace->id,
            'phone_number' => '12345',
            'carrier' => 'globe',
            'status' => SimStatus::Received->value,
        ])
        ->assertSessionHasErrors('phone_number');
});

it('updates a SIM', function () {
    $admin = User::factory()->superAdmin()->create();
    $sim = Sim::factory()->create(['label' => 'old', 'status' => SimStatus::Received->value]);

    $this->actingAs($admin)
        ->put(route('admin.sims.update', $sim), [
            'workspace_id' => $sim->workspace_id,
            'phone_number' => $sim->phone_number,
            'carrier' => $sim->carrier->value,
            'port_number' => $sim->port_number,
            'label' => 'new label',
            'status' => SimStatus::Suspended->value,
            'notes' => null,
        ])
        ->assertRedirect(route('admin.sims.index'));

    expect($sim->fresh()->label)->toBe('new label')
        ->and($sim->fresh()->status)->toBe(SimStatus::Suspended);
});

it('soft-deletes a SIM', function () {
    $admin = User::factory()->superAdmin()->create();
    $sim = Sim::factory()->create();

    $this->actingAs($admin)
        ->delete(route('admin.sims.destroy', $sim))
        ->assertRedirect(route('admin.sims.index'));

    expect(Sim::whereKey($sim->id)->exists())->toBeFalse()
        ->and(Sim::withTrashed()->whereKey($sim->id)->exists())->toBeTrue();
});
