<?php

use App\Models\User;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;

it('lets a workspace owner send an SMS', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);

    $this->post("/workspaces/{$workspace->slug}/sms/send", [
        'sim_id' => $sim->id,
        'to' => '09171234567',
        'message' => 'Hello there',
    ])->assertRedirect("/workspaces/{$workspace->slug}/sms/outbox");

    expect(
        SmsMessage::where('workspace_id', $workspace->id)
            ->where('to_number', '09171234567')
            ->where('direction', 'outbound')
            ->exists()
    )->toBeTrue();
});

it('rejects an invalid Philippine number', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);

    $this->from("/workspaces/{$workspace->slug}/sms/send")
        ->post("/workspaces/{$workspace->slug}/sms/send", [
            'sim_id' => $sim->id,
            'to' => '12345',
            'message' => 'Hi',
        ])
        ->assertSessionHasErrors('to');
});

it('forbids a non-member from sending', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);

    // Assert as JSON so the 403 short-circuits to a JSON response instead of the
    // Inertia error page (which would require a built Vite manifest).
    $this->actingAs(User::factory()->create())
        ->postJson("/workspaces/{$workspace->slug}/sms/send", [
            'sim_id' => $sim->id,
            'to' => '09171234567',
            'message' => 'Hi',
        ])
        ->assertForbidden();
});

it('will not send through a SIM from another workspace', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();
    $foreignSim = Sim::factory()->create(['workspace_id' => $other->id, 'status' => 'active']);

    $this->post("/workspaces/{$workspace->slug}/sms/send", [
        'sim_id' => $foreignSim->id,
        'to' => '09171234567',
        'message' => 'Hi',
    ])->assertNotFound();
});
