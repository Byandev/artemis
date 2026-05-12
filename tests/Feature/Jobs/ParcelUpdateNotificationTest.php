<?php

use App\Jobs\CheckParcelUpdateNotification;
use App\Jobs\SendParcelUpdateNotification;
use App\Models\Order;
use App\Models\Page;
use App\Models\Workspace;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\ParcelJourneyNotification;

function makeNotification(array $overrides = []): ParcelJourneyNotification
{
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create([
        'infotxt_token' => 'INFOTOKEN',
        'infotxt_user_id' => 'INFOUSER',
        'botcake_token' => 'BOTCAKE-TOKEN',
        'parcel_journey_custom_field_id' => 1,
    ]);
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
