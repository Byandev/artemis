<?php

use App\Jobs\SendParcelUpdateNotification;
use App\Models\Page;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Modules\Pancake\Models\Order;
use Modules\Pancake\Models\ParcelJourney;
use Modules\Pancake\Models\ParcelJourneyNotification;
use Modules\Pancake\Notifications\ParcelJourneyNotifier;
use Modules\Pancake\Support\MessageRenderer;

beforeEach(function () {
    config()->set('settings.parcel_journey_notification_enabled', true);
});

function setupNotifiableJourney(): array
{
    $workspace = Workspace::factory()->create();
    $page = Page::factory()->forWorkspace($workspace)->create([
        'parcel_journey_enabled' => true,
        'botcake_token' => 'BOTCAKE-TOKEN',
        'parcel_journey_custom_field_id' => 1,
        'parcel_journey_flow_id' => 1,
    ]);
    // The app Order model owns the factory; the notifier consumes the module
    // Order. Both map to the `orders` table, so create with one and re-read as
    // the other.
    $orderId = App\Models\Order::factory()->forPage($page)->create([
        'fb_id' => '111_222',
    ])->id;
    $order = Order::findOrFail($orderId);

    ShippingAddress::factory()->create([
        'order_id' => $order->id,
        'full_name' => 'Juan Dela Cruz',
        'phone_number' => '+639170000000',
    ]);

    $journey = ParcelJourney::create([
        'order_id' => $order->id,
        'status' => 'Departure',
        'note' => 'left 【Manila Hub】 for 【Cavite Hub】',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return [$workspace, $page, $order->fresh(), $journey];
}

test('re-notifying the same journey does not create duplicate notifications', function () {
    Bus::fake();

    [$workspace, $page, $order, $journey] = setupNotifiableJourney();

    $notifier = new ParcelJourneyNotifier($page, $workspace, new MessageRenderer);

    // Two hourly syncs (or two concurrent SyncOrder runs) hit the same journey.
    $notifier->notify($order, $journey);
    $notifier->notify($order, $journey);

    // One row per channel survives — not two.
    expect(ParcelJourneyNotification::where('parcel_journey_id', $journey->id)->where('type', 'chat')->count())->toBe(1);
    expect(ParcelJourneyNotification::where('parcel_journey_id', $journey->id)->where('type', 'sms')->count())->toBe(1);

    // The model's `created` hook fired exactly once per channel, so each chat /
    // SMS is sent a single time regardless of how often the sync re-runs.
    Bus::assertDispatchedTimes(SendParcelUpdateNotification::class, 2);
});

test('the database rejects a duplicate notification for the same journey, type and recipient', function () {
    [$workspace, $page, $order, $journey] = setupNotifiableJourney();

    $row = [
        'order_id' => $order->id,
        'parcel_journey_id' => $journey->id,
        'type' => 'chat',
        'status' => 'pending',
        'receiver_name' => 'Juan Dela Cruz',
        'receiver_identity' => '111_222',
        'message' => 'hello',
        'created_at' => now(),
        'updated_at' => now(),
    ];

    DB::table('parcel_journey_notifications')->insert($row);

    expect(fn () => DB::table('parcel_journey_notifications')->insert($row))
        ->toThrow(QueryException::class);
});
