<?php

use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;

beforeEach(function () {
    config()->set('simgateway.callback.token', 'secret-token');
});

it('rejects a callback with a bad token', function () {
    $this->postJson('/gateway/callback/dlr?token=wrong', [
        'type' => 'status-report',
        'rpts' => [],
    ])->assertStatus(401);
});

it('returns 503 when no callback token is configured', function () {
    config()->set('simgateway.callback.token', '');

    $this->postJson('/gateway/callback/dlr?token=anything', [
        'type' => 'status-report',
        'rpts' => [],
    ])->assertStatus(503);
});

it('marks a message sent from a delivery report', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $sim = Sim::factory()->create(['workspace_id' => $workspace->id]);
    $sms = SmsMessage::factory()->create([
        'workspace_id' => $workspace->id,
        'sim_id' => $sim->id,
        'provider_message_id' => 'yxgp:12345',
        'status' => 'queued',
    ]);

    $this->postJson('/gateway/callback/dlr?token=secret-token', [
        'type' => 'status-report',
        'rpt_num' => 1,
        'rpts' => [
            ['tid' => '12345', 'sent' => 1, 'failed' => 0, 'sending' => 0],
        ],
    ])->assertOk();

    expect($sms->fresh()->status->value)->toBe('sent');
});

it('stores an inbound SMS from a receive callback', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    $sim = Sim::factory()->create([
        'workspace_id' => $workspace->id,
        'port_number' => 5,
    ]);

    $this->postJson('/gateway/callback/sms?token=secret-token', [
        'type' => 'recv-sms',
        'sms_num' => 1,
        'sms' => [
            [0, '5.01', 1700000000, '639171234567', $sim->phone_number, base64_encode('Hello inbound')],
        ],
    ])->assertOk();

    expect(
        SmsMessage::where('sim_id', $sim->id)
            ->where('direction', 'inbound')
            ->where('message', 'Hello inbound')
            ->exists()
    )->toBeTrue();
});
