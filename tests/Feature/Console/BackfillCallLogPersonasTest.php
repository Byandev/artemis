<?php

use App\Jobs\BackfillCallLogPersonasForDay;
use App\Models\CallLog;
use App\Models\Order as AppOrder;
use App\Models\Page;
use App\Models\ShippingAddress;
use App\Models\Workspace;
use App\Support\CallLogPersona;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;

/**
 * Re-running the persona match over calls that synced before the data they
 * match against had landed.
 *
 * The rules are the sync's own, in the sync's own order — a delivery loaded that
 * day first, and only what is left over offered to the orders that day took in
 * or confirmed — so a row this fills in is indistinguishable from one stamped at
 * sync time.
 *
 * The command only picks the workspace-days worth doing and hands them to the
 * queue; the matching is the job's, so that is where it is tested.
 */
function backfillOrder(Workspace $workspace, mixed $confirmedAt = null, ?string $phone = null, mixed $insertedAt = null): AppOrder
{
    $page = Page::factory()->forWorkspace($workspace)->create();

    // inserted_at is the other stamp the verification rule reads, so it is left
    // on the factory's now() — a day of its own — unless a test wants it in the
    // day being backfilled.
    $order = AppOrder::factory()->forPage($page)->create(array_filter([
        'confirmed_at' => $confirmedAt,
        'inserted_at' => $insertedAt,
    ]));

    if ($phone !== null) {
        ShippingAddress::factory()->create([
            'order_id' => $order->id,
            'phone_number' => $phone,
        ]);
    }

    return $order;
}

function backfillDelivery(Workspace $workspace, AppOrder $order, mixed $date, array $attributes = []): int
{
    return DB::table('pancake_order_for_delivery')->insertGetId([
        'order_id' => $order->id,
        'shop_id' => 1,
        'workspace_id' => $workspace->id,
        'status' => 'delivered',
        'rider_name' => 'Rider',
        'rider_phone' => '09990000000',
        'delivery_date' => Carbon::parse($date)->toDateString(),
        'created_at' => now(),
        'updated_at' => now(),
        ...$attributes,
    ]);
}

function backfillCall(Workspace $workspace, string $phone, mixed $date, array $attributes = []): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'phone_number' => $phone,
        'call_date' => Carbon::parse($date)->toDateString(),
        'order_id' => null,
        'order_for_delivery_id' => null,
        'persona' => null,
        ...$attributes,
    ]);
}

function backfillDay(Workspace $workspace, string $date = '2026-09-02', string $rule = 'all', bool $dryRun = false): array
{
    return (new BackfillCallLogPersonasForDay($workspace->id, $date, $rule, $dryRun))->handle();
}

it('stamps a call to a number on that day\'s delivery as a customer call', function () {
    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    $delivery = backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    expect(backfillDay($workspace))->toMatchArray(['customer' => 1, 'rider' => 0, 'verification' => 0]);

    expect($call->refresh())
        ->persona->toBe(CallLogPersona::CUSTOMER)
        ->order_id->toBe($order->id)
        ->order_for_delivery_id->toBe($delivery);
});

it('stamps a call to the rider on that day\'s delivery as a rider call', function () {
    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    $delivery = backfillDelivery($workspace, $order, '2026-09-02', [
        'customer_phone' => '09171234567',
        'rider_phone' => '09181112222',
    ]);

    $call = backfillCall($workspace, '09181112222', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh())
        ->persona->toBe(CallLogPersona::RIDER)
        ->order_id->toBe($order->id)
        ->order_for_delivery_id->toBe($delivery);
});

it('calls a number that is both a customer and a rider that day a customer call', function () {
    $workspace = Workspace::factory()->create();

    $riderOrder = backfillOrder($workspace);
    backfillDelivery($workspace, $riderOrder, '2026-09-02', ['rider_phone' => '09171234567']);

    $customerOrder = backfillOrder($workspace);
    $customerDelivery = backfillDelivery($workspace, $customerOrder, '2026-09-02', [
        'customer_phone' => '09171234567',
        'rider_phone' => '09990000001',
    ]);

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh())
        ->persona->toBe(CallLogPersona::CUSTOMER)
        ->order_for_delivery_id->toBe($customerDelivery);
});

it('stamps a call to an order confirmed that day, spelled either way, as verification', function () {
    $workspace = Workspace::factory()->create();

    // Pancake was given the international spelling; the handset reported the
    // local one. Both have to land on the same key.
    $order = backfillOrder($workspace, Carbon::parse('2026-09-02 14:05'), '+639171234567');

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh())
        ->persona->toBe(CallLogPersona::VERIFICATION)
        ->order_id->toBe($order->id)
        ->order_for_delivery_id->toBeNull();
});

it('stamps a call to an order that came in that day but was never confirmed', function () {
    $workspace = Workspace::factory()->create();

    // Nothing on confirmed_at to match on, so on that stamp alone this call
    // stays unmatched however often the backfill is re-run.
    $order = backfillOrder($workspace, null, '09171234567', Carbon::parse('2026-09-02 07:30'));

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    expect(backfillDay($workspace))->toMatchArray(['verification' => 1]);

    expect($call->refresh())
        ->persona->toBe(CallLogPersona::VERIFICATION)
        ->order_id->toBe($order->id)
        ->order_for_delivery_id->toBeNull();
});

it('gives a number on an order taken in that day and one confirmed that day to the earlier stamp', function () {
    $workspace = Workspace::factory()->create();

    // Came in that morning; nobody has confirmed it.
    $earliest = backfillOrder($workspace, null, '09171234567', Carbon::parse('2026-09-02 08:00'));
    // Came in days before and was confirmed that afternoon.
    backfillOrder($workspace, Carbon::parse('2026-09-02 16:00'), '09171234567');

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh()->order_id)->toBe($earliest->id);
});

it('prefers the delivery in front of it over an order confirmed the same day', function () {
    $workspace = Workspace::factory()->create();

    // Confirmed and loaded for delivery on the same day: both rules would match,
    // and the delivery is what the call was actually about.
    $order = backfillOrder($workspace, Carbon::parse('2026-09-02 09:00'), '09171234567');
    $delivery = backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    expect(backfillDay($workspace))->toMatchArray(['customer' => 1, 'verification' => 0]);

    expect($call->refresh())
        ->persona->toBe(CallLogPersona::CUSTOMER)
        ->order_for_delivery_id->toBe($delivery);
});

it('gives a number confirmed on two orders that day to the earliest confirmation', function () {
    $workspace = Workspace::factory()->create();

    // Both came in on days of their own, so the confirmation is the only stamp
    // either has in this day and the earlier of the two takes the call.
    $earliest = backfillOrder($workspace, Carbon::parse('2026-09-02 08:00'), '09171234567');
    backfillOrder($workspace, Carbon::parse('2026-09-02 16:00'), '09171234567');

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh()->order_id)->toBe($earliest->id);
});

it('never matches a call to another workspace\'s delivery or order', function () {
    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();

    // Same number, same day, everything — but all of it belongs to someone else.
    $otherDelivery = backfillOrder($other);
    backfillDelivery($other, $otherDelivery, '2026-09-02', ['customer_phone' => '09171234567']);
    backfillOrder($other, Carbon::parse('2026-09-02 10:00'), '09171234567');

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh())
        ->persona->toBeNull()
        ->order_id->toBeNull();
});

it('leaves a call on another day and a call already carrying a persona alone', function () {
    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);
    backfillDelivery($workspace, $order, '2026-09-03', [
        'customer_phone' => '09171234567',
        'rider_phone' => '09990000009',
    ]);

    $wrongDay = backfillCall($workspace, '09171234567', '2026-09-03');
    $alreadyStamped = backfillCall($workspace, '09171234567', '2026-09-02', [
        'persona' => CallLogPersona::VERIFICATION,
        'order_id' => $order->id,
    ]);

    backfillDay($workspace, '2026-09-02');

    expect($wrongDay->refresh()->persona)->toBeNull();
    expect($alreadyStamped->refresh())
        ->persona->toBe(CallLogPersona::VERIFICATION)
        ->order_for_delivery_id->toBeNull();
});

it('applies only the rule it is given', function () {
    $workspace = Workspace::factory()->create();

    $confirmed = backfillOrder($workspace, Carbon::parse('2026-09-02 10:00'), '09171234567');
    $verificationCall = backfillCall($workspace, '09171234567', '2026-09-02');

    $delivered = backfillOrder($workspace);
    backfillDelivery($workspace, $delivered, '2026-09-02', ['customer_phone' => '09180000000']);
    $deliveryCall = backfillCall($workspace, '09180000000', '2026-09-02');

    backfillDay($workspace, '2026-09-02', 'delivery');

    expect($verificationCall->refresh()->persona)->toBeNull();
    expect($deliveryCall->refresh()->persona)->toBe(CallLogPersona::CUSTOMER);

    backfillDay($workspace, '2026-09-02', 'verification');

    expect($verificationCall->refresh())
        ->persona->toBe(CallLogPersona::VERIFICATION)
        ->order_id->toBe($confirmed->id);
});

it('ignores a number too short to normalize rather than padding it into a match', function () {
    $workspace = Workspace::factory()->create();

    // Nine digits. Zero-padded to ten it would collide with a real subscriber,
    // which is exactly what CallLogPersona::normalize returns null to prevent.
    backfillOrder($workspace, Carbon::parse('2026-09-02 10:00'), '917123456');

    $call = backfillCall($workspace, '917123456', '2026-09-02');

    backfillDay($workspace);

    expect($call->refresh()->persona)->toBeNull();
});

it('writes nothing on a dry run but still counts what it would have stamped', function () {
    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    expect(backfillDay($workspace, '2026-09-02', 'all', dryRun: true))
        ->toMatchArray(['customer' => 1]);

    expect($call->refresh())
        ->persona->toBeNull()
        ->order_id->toBeNull();
});

it('logs its totals, since a queued backfill has nowhere else to report', function () {
    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);

    backfillCall($workspace, '09171234567', '2026-09-02');

    Log::spy();

    backfillDay($workspace);

    Log::shouldHaveReceived('info')->once()->withArgs(
        fn ($message, $context) => $message === 'Backfilled call log personas'
            && $context['workspace_id'] === $workspace->id
            && $context['date'] === '2026-09-02'
            && $context['customer'] === 1
    );
});

it('hands the queue one job per workspace-day that has work', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();
    $other = Workspace::factory()->create();

    backfillCall($workspace, '09171234567', '2026-09-01');
    backfillCall($workspace, '09171234567', '2026-09-02');
    // A second call on a day already covered does not earn a second job.
    backfillCall($workspace, '09180000000', '2026-09-02');
    backfillCall($other, '09171234567', '2026-09-02');
    // Already stamped, so its day is not work.
    backfillCall($workspace, '09171234567', '2026-09-05', ['persona' => CallLogPersona::CUSTOMER]);

    $this->artisan('call-logs:backfill-personas')
        ->expectsOutputToContain('Queued 3 jobs')
        ->assertSuccessful();

    Queue::assertPushed(BackfillCallLogPersonasForDay::class, 3);

    Queue::assertPushed(
        BackfillCallLogPersonasForDay::class,
        fn ($job) => $job->workspaceId === $workspace->id
            && $job->date === '2026-09-02'
            && $job->rule === 'all'
            && $job->dryRun === false
            && $job->queue === 'analytics'
    );

    Queue::assertNotPushed(
        BackfillCallLogPersonasForDay::class,
        fn ($job) => $job->date === '2026-09-05'
    );
});

it('queues only the days inside the range it is given', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();

    backfillCall($workspace, '09171234567', '2026-09-01');
    backfillCall($workspace, '09171234567', '2026-09-02');
    backfillCall($workspace, '09171234567', '2026-09-03');

    $this->artisan('call-logs:backfill-personas', [
        '--workspace' => $workspace->id,
        '--since' => '2026-09-02',
        '--until' => '2026-09-02',
    ])->assertSuccessful();

    Queue::assertPushed(BackfillCallLogPersonasForDay::class, 1);
    Queue::assertPushed(
        BackfillCallLogPersonasForDay::class,
        fn ($job) => $job->date === '2026-09-02'
    );
});

it('passes the chosen rule through to the jobs', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();
    backfillCall($workspace, '09171234567', '2026-09-02');

    $this->artisan('call-logs:backfill-personas', [
        '--workspace' => $workspace->id,
        '--date' => '2026-09-02',
        '--rule' => 'verification',
    ])->assertSuccessful();

    Queue::assertPushed(
        BackfillCallLogPersonasForDay::class,
        fn ($job) => $job->rule === 'verification'
    );
});

it('runs the days itself instead of queueing when told to', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    $this->artisan('call-logs:backfill-personas', [
        '--workspace' => $workspace->id,
        '--sync' => true,
    ])->assertSuccessful();

    Queue::assertNothingPushed();

    expect($call->refresh()->persona)->toBe(CallLogPersona::CUSTOMER);
});

it('keeps a dry run out of the queue, since a worker has nowhere to report to', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();
    $order = backfillOrder($workspace);
    backfillDelivery($workspace, $order, '2026-09-02', ['customer_phone' => '09171234567']);

    $call = backfillCall($workspace, '09171234567', '2026-09-02');

    $this->artisan('call-logs:backfill-personas', [
        '--workspace' => $workspace->id,
        '--dry-run' => true,
    ])->expectsOutputToContain('Dry run')->assertSuccessful();

    Queue::assertNothingPushed();

    expect($call->refresh()->persona)->toBeNull();
});

it('says so and queues nothing when every call in range is stamped', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();
    backfillCall($workspace, '09171234567', '2026-09-02', ['persona' => CallLogPersona::CUSTOMER]);

    $this->artisan('call-logs:backfill-personas', ['--workspace' => $workspace->id])
        ->expectsOutputToContain('Nothing to backfill')
        ->assertSuccessful();

    Queue::assertNothingPushed();
});

it('accepts a workspace slug and rejects an unknown one', function () {
    Queue::fake();

    $workspace = Workspace::factory()->create();
    backfillCall($workspace, '09171234567', '2026-09-02');

    $this->artisan('call-logs:backfill-personas', ['--workspace' => $workspace->slug])
        ->assertSuccessful();

    Queue::assertPushed(BackfillCallLogPersonasForDay::class, 1);

    $this->artisan('call-logs:backfill-personas', ['--workspace' => 'nope'])->assertFailed();
});

it('refuses a rule it does not have and a range that runs backwards', function () {
    Queue::fake();

    $this->artisan('call-logs:backfill-personas', ['--rule' => 'sideways'])->assertFailed();

    $this->artisan('call-logs:backfill-personas', [
        '--since' => '2026-09-05',
        '--until' => '2026-09-01',
    ])->assertFailed();

    Queue::assertNothingPushed();
});
