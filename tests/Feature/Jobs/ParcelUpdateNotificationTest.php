<?php

use App\Jobs\CheckParcelUpdateNotification;
use App\Jobs\SendParcelUpdateNotification;
use App\Models\Order;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\Pancake\Models\ParcelJourneyNotification;
use Modules\SimGateway\Models\Sim;
use Modules\SimGateway\Models\SmsMessage;

function makeNotification(array $overrides = [], array $pageOverrides = []): ParcelJourneyNotification
{
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create(array_merge([
        'infotxt_token' => 'INFOTOKEN',
        'infotxt_user_id' => 'INFOUSER',
        'botcake_token' => 'BOTCAKE-TOKEN',
        'parcel_journey_custom_field_id' => 1,
    ], $pageOverrides));
    $order = Order::factory()->forPage($page)->create([
        'workspace_id' => $workspace->id,
        'fb_id' => '12345_67890',
    ]);

    $journeyId = DB::table('parcel_journeys')->insertGetId([
        'order_id' => $order->id,
        'status' => 'in_transit',
        'note' => 'test',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return ParcelJourneyNotification::create(array_merge([
        'order_id' => $order->id,
        'parcel_journey_id' => $journeyId,
        'type' => 'sms',
        'status' => 'pending',
        'receiver_name' => 'Bob',
        'receiver_identity' => '+639170000000',
        'message' => 'Your parcel is on the way',
    ], $overrides));
}

beforeEach(function () {
    config()->set('settings.parcel_journey_notification_enabled', true);
});

test('SMS notification updates sms_id and dispatches a status check on success', function () {
    Bus::fake();
    Http::fake([
        'api.myinfotxt.com/v2/send.php*' => Http::response(['status' => '00', 'smsid' => 'SMS-9'], 200),
    ]);

    $notif = makeNotification();
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->sms_id)->toBe('SMS-9');
    Bus::assertDispatched(CheckParcelUpdateNotification::class);
});

test('SMS notification stores remarks when API returns non-success status', function () {
    Bus::fake();
    Http::fake(['api.myinfotxt.com/*' => Http::response(['status' => '99', 'reason' => 'bad number'], 200)]);

    $notif = makeNotification();
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->sms_id)->toBeNull();
    expect($notif->fresh()->remarks)->toContain('bad number');
    Bus::assertNotDispatched(CheckParcelUpdateNotification::class);
});

test('SMS notification marks failed when HTTP request fails', function () {
    Bus::fake();
    Http::fake(['api.myinfotxt.com/*' => Http::response([], 502)]);

    $notif = makeNotification();
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('failed');
    expect($notif->fresh()->remarks)->toBe('Request failed');
});

test('Chat notification uses Botcake service and marks sent on success', function () {
    Http::fake([
        'botcake.io/*' => Http::response(['success' => true], 200),
    ]);

    $notif = makeNotification(['type' => 'chat']);
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('sent');
});

test('Chat notification marks failed and stores error when Botcake throws', function () {
    Http::fake(['botcake.io/*' => Http::response([], 500)]);

    $notif = makeNotification(['type' => 'chat']);
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('failed');
    expect($notif->fresh()->remarks)->not->toBeNull();
});

test('does nothing when notifications are globally disabled', function () {
    config()->set('settings.parcel_journey_notification_enabled', false);
    Http::fake();

    $notif = makeNotification();
    (new SendParcelUpdateNotification($notif))->handle();

    Http::assertNothingSent();
    expect($notif->fresh()->status)->toBe('pending');
});

// SendGate provider

test('SendGate SMS marks sent immediately on a successful send (no status polling)', function () {
    Bus::fake();
    config()->set('services.sendgate.base_url', 'https://sg.test');
    Http::fake(['sg.test/*' => Http::response(['id' => 'MSG-123', 'status' => 'queued'], 201)]);

    $notif = makeNotification([], [
        'sms_provider' => 'sendgate',
        'sendgate_api_key' => 'sg_live_test',
        'sendgate_sim_id' => '1',
    ]);
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('sent');
    expect($notif->fresh()->sms_id)->toBe('MSG-123');
    Bus::assertNotDispatched(CheckParcelUpdateNotification::class);
});

test('SendGate SMS sends a bearer-authed JSON POST with sim_id, to and message', function () {
    Bus::fake();
    config()->set('services.sendgate.base_url', 'https://sg.test');
    Http::fake(['sg.test/*' => Http::response(['id' => 'MSG-1'], 200)]);

    $notif = makeNotification([], [
        'sms_provider' => 'sendgate',
        'sendgate_api_key' => 'sg_live_abc',
        'sendgate_sim_id' => '7',
    ]);
    (new SendParcelUpdateNotification($notif))->handle();

    Http::assertSent(function ($request) {
        return str_contains($request->url(), '/api/v1/messages')
            && $request->hasHeader('Authorization', 'Bearer sg_live_abc')
            && $request['sim_id'] === '7'
            && $request['to'] === '+639170000000'
            && $request['message'] === 'Your parcel is on the way';
    });
});

test('SendGate SMS marks failed when the send request fails', function () {
    Bus::fake();
    config()->set('services.sendgate.base_url', 'https://sg.test');
    Http::fake(['sg.test/*' => Http::response(['message' => 'Unauthorized'], 401)]);

    $notif = makeNotification([], [
        'sms_provider' => 'sendgate',
        'sendgate_api_key' => 'sg_live_bad',
        'sendgate_sim_id' => '1',
    ]);
    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('failed');
    Bus::assertNotDispatched(CheckParcelUpdateNotification::class);
});

// Artemis SIM Gateway provider

test('SIM Gateway SMS stays pending with the provider id, awaiting the delivery callback', function () {
    Bus::fake();

    $notif = makeNotification([], ['sms_provider' => 'sim_gateway']);
    $page = $notif->order->page;
    $sim = Sim::factory()->create([
        'workspace_id' => $page->workspace_id,
        'status' => 'active',
    ]);
    $page->update(['sim_gateway_sim_id' => $sim->id]);

    (new SendParcelUpdateNotification($notif))->handle();

    // Not marked sent yet — the device pushes the final status to our callback.
    expect($notif->fresh()->status)->toBe('pending');
    expect($notif->fresh()->sms_id)->not->toBeNull();
    expect(
        SmsMessage::where('sim_id', $sim->id)
            ->where('direction', 'outbound')
            ->where('to_number', '+639170000000')
            ->exists()
    )->toBeTrue();
    Bus::assertNotDispatched(CheckParcelUpdateNotification::class);
});

test('a SIM Gateway delivery report marks the linked parcel notification sent', function () {
    config()->set('simgateway.callback.token', 'secret-token');

    // A page that sent via the SIM Gateway stored the provider id as sms_id.
    $notif = makeNotification();
    $notif->update(['sms_id' => 'yxgp:555']);

    SmsMessage::factory()->create([
        'direction' => 'outbound',
        'provider_message_id' => 'yxgp:555',
        'status' => 'queued',
    ]);

    $this->postJson('/gateway/callback/dlr?token=secret-token', [
        'type' => 'status-report',
        'rpts' => [['tid' => '555', 'sent' => 1, 'failed' => 0, 'sending' => 0]],
    ])->assertOk();

    expect($notif->fresh()->status)->toBe('sent');
});

test('SIM Gateway SMS marks failed when no SIM is selected', function () {
    $notif = makeNotification([], ['sms_provider' => 'sim_gateway', 'sim_gateway_sim_id' => null]);

    (new SendParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('failed');
    expect($notif->fresh()->remarks)->toContain('No SIM selected');
});

// CheckParcelUpdateNotification

test('CheckParcelUpdateNotification marks sent on status 1', function () {
    Http::fake(['api.myinfotxt.com/*' => Http::response(['status' => '1'], 200)]);

    $notif = makeNotification(['sms_id' => 'SMS-1']);
    (new CheckParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('sent');
});

test('CheckParcelUpdateNotification marks failed on status 2', function () {
    Http::fake(['api.myinfotxt.com/*' => Http::response(['status' => '2', 'detail' => 'rejected'], 200)]);

    $notif = makeNotification(['sms_id' => 'SMS-2']);
    (new CheckParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('failed');
    expect($notif->fresh()->remarks)->toContain('rejected');
});

test('CheckParcelUpdateNotification re-dispatches on status 0 (pending)', function () {
    Bus::fake();
    Http::fake(['api.myinfotxt.com/*' => Http::response(['status' => '0'], 200)]);

    $notif = makeNotification(['sms_id' => 'SMS-3']);
    (new CheckParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('pending');
    Bus::assertDispatched(CheckParcelUpdateNotification::class);
});

test('CheckParcelUpdateNotification marks failed when HTTP request fails', function () {
    Http::fake(['api.myinfotxt.com/*' => Http::response([], 503)]);

    $notif = makeNotification(['sms_id' => 'SMS-4']);
    (new CheckParcelUpdateNotification($notif))->handle();

    expect($notif->fresh()->status)->toBe('failed');
    expect($notif->fresh()->remarks)->toBe('Checking Request failed');
});
