<?php

use Modules\SimGateway\Enums\ScheduledMessageStatus;
use Modules\SimGateway\Jobs\ProcessScheduledMessagesJob;
use Modules\SimGateway\Models\ScheduledMessage;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;
use Modules\SimGateway\Services\Gateway\GatewayInterface;

it('schedules a message for later', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);

    $this->post("/workspaces/{$workspace->slug}/sms/scheduled", [
        'sim_id' => $sim->id,
        'to_number' => '09171234567',
        'message' => 'send me later',
        'scheduled_at' => now()->addDay()->format('Y-m-d H:i:s'),
    ])->assertRedirect();

    expect(
        ScheduledMessage::where('workspace_id', $workspace->id)
            ->where('status', ScheduledMessageStatus::Pending)
            ->count()
    )->toBe(1);
});

it('sends due scheduled messages and marks them sent', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);
    $scheduled = ScheduledMessage::factory()->create([
        'workspace_id' => $workspace->id,
        'sim_id' => $sim->id,
        'scheduled_at' => now()->subMinute(),
        'status' => ScheduledMessageStatus::Pending,
    ]);

    (new ProcessScheduledMessagesJob)->handle(app(GatewayInterface::class));

    expect($scheduled->fresh()->status)->toBe(ScheduledMessageStatus::Sent);
    expect(
        SmsMessage::where('workspace_id', $workspace->id)
            ->where('to_number', $scheduled->to_number)
            ->exists()
    )->toBeTrue();
});

it('does not send scheduled messages that are not yet due', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);
    $scheduled = ScheduledMessage::factory()->create([
        'workspace_id' => $workspace->id,
        'sim_id' => $sim->id,
        'scheduled_at' => now()->addHour(),
        'status' => ScheduledMessageStatus::Pending,
    ]);

    (new ProcessScheduledMessagesJob)->handle(app(GatewayInterface::class));

    expect($scheduled->fresh()->status)->toBe(ScheduledMessageStatus::Pending);
});

it('cancels a scheduled message', function () {
    ['workspace' => $workspace] = actingAsWorkspaceOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id, 'status' => 'active']);
    $scheduled = ScheduledMessage::factory()->create([
        'workspace_id' => $workspace->id,
        'sim_id' => $sim->id,
        'status' => ScheduledMessageStatus::Pending,
    ]);

    $this->delete("/workspaces/{$workspace->slug}/sms/scheduled/{$scheduled->id}")
        ->assertRedirect();

    expect($scheduled->fresh()->status)->toBe(ScheduledMessageStatus::Cancelled);
});
