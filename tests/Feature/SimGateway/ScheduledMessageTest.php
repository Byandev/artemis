<?php

use Modules\SimGateway\Enums\ScheduledMessageStatus;
use Modules\SimGateway\Jobs\ProcessScheduledMessagesJob;
use Modules\SimGateway\Models\ScheduledMessage;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;
use Modules\SimGateway\Services\Gateway\GatewayInterface;

// The scheduled-message UI is disabled for now; the backend job is still wired
// up, so these cover the processing path only.

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
