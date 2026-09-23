<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\Page;
use App\Models\User;
use App\Support\CallLogPersona;
use App\Support\RmoDailyStats;
use Modules\Pancake\Models\OrderForDelivery;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The five call-log stat cards on the public RMO page.
 *
 * They used to report the workspace's whole day flat, so the identity picker up
 * top and the assignee filter moved the table while the call figures sat still.
 * They now follow the caller — call_logs.user_id, the CSR who dialled — because
 * that is what "my call logs" means. Cutting them by the assignee's *orders*
 * instead still looks unfiltered in practice: a CSR spends the day ringing
 * numbers that are not on the orders assigned to them.
 *
 * They also count RMO calls only: the rows CallLogPersona stamped as reaching a
 * customer or a rider on one of the day's deliveries. A verification call or an
 * unmatched number is a real call but not RMO work, and counting it left the
 * total sitting above the sum of the breakdown modal's own two RMO tabs.
 */
const STAT_DATE = '2026-07-21';

function statRow($workspace, $assigneeId, string $customerPhone, ?string $conferrerId = null): OrderForDelivery
{
    $order = Order::factory()->forWorkspace($workspace)->create();

    return OrderForDelivery::create([
        'order_id' => $order->id,
        'page_id' => $order->page_id,
        'shop_id' => $order->shop_id,
        'workspace_id' => $workspace->id,
        'status' => 'PENDING',
        'parcel_status' => 'on delivery',
        'customer_name' => 'Cx',
        'customer_phone' => $customerPhone,
        'rider_name' => 'Rider',
        'rider_phone' => '0918'.substr($customerPhone, -7),
        'assignee_id' => $assigneeId,
        'conferrer_id' => $conferrerId,
        'delivery_date' => STAT_DATE,
    ]);
}

/**
 * One call. Stamped as an RMO call to a customer by default — that is what the
 * sync does for a number on one of the day's deliveries, and what the cards
 * count. Pass a persona (or null, for a number nothing matched) to make a call
 * the cards should leave out.
 */
function statCall(string $phone, $workspace, $callerId, int $duration, ?string $persona = CallLogPersona::CUSTOMER): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $callerId,
        'phone_number' => $phone,
        'call_date' => STAT_DATE,
        'duration' => $duration,
        'persona' => $persona,
    ]);
}

/**
 * The stat-card endpoint, as a super admin (who bypasses the password gate).
 *
 * The cards no longer ride along with the page render — they are six aggregates
 * over the whole day that don't change when you sort or turn a page, so they get
 * their own request.
 */
function rmoStats(array $params, $workspace)
{
    return test()->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->getJson(route('public-page.rmo-management.stats', ['workspace' => $workspace->slug, ...$params]));
}

/** The public RMO page itself, same identity. */
function rmoPage(array $params, $workspace)
{
    return test()->actingAs(User::factory()->create(['is_super_admin' => true]))
        ->get(route('public-page.rmo-management', ['workspace' => $workspace->slug, ...$params]));
}

beforeEach(function () {
    ['workspace' => $this->workspace] = makeWorkspaceWithOwner();
    subscribeWorkspace($this->workspace);

    $this->csrA = PancakeUser::create(['name' => 'CSR A']);
    $this->csrB = PancakeUser::create(['name' => 'CSR B']);

    // CSR A's order: two calls, one of them too short to have connected.
    statRow($this->workspace, $this->csrA->id, '09170000001', $this->csrA->id);
    statCall('09170000001', $this->workspace, $this->csrA->id, 60);
    statCall('09170000001', $this->workspace, $this->csrA->id, 2);

    // CSR B's order: one connected call.
    statRow($this->workspace, $this->csrB->id, '09170000002', $this->csrB->id);
    statCall('09170000002', $this->workspace, $this->csrB->id, 90);
});

test('with no identity set the cards report the whole workspace day', function () {
    rmoStats(['delivery_date' => STAT_DATE], $this->workspace)
        ->assertOk()
        ->assertJson([
            'total_call_logs_count' => 3,
            'total_call_duration' => 152,
            'connected_call_logs_count' => 2,
        ]);
});

test('the cards report the calls the identified CSR placed', function () {
    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJson([
            'total_call_logs_count' => 2,
            'total_call_duration' => 62,
            // The 2-second call is logged but nobody spoke.
            'connected_call_logs_count' => 1,
        ]);
});

test('a call counts for whoever placed it, not whoever owns the order', function () {
    // CSR B rings a customer on an order assigned to CSR A. It is CSR B who was
    // on the phone, so it is CSR B's figures that move — scoping by the order's
    // assignee would have credited this to CSR A.
    statCall('09170000001', $this->workspace, $this->csrB->id, 40);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJson(['total_call_logs_count' => 2]);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrB->id,
    ], $this->workspace)
        ->assertJson([
            'total_call_logs_count' => 2,
            'total_call_duration' => 130,
        ]);
});

test('a call that matched no delivery is left out of the figures', function () {
    // A number nothing on the day's deliveries carries: still the CSR's call,
    // but not RMO work, so it must not move the RMO cards.
    statCall('09990000000', $this->workspace, $this->csrA->id, 30, null);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJson([
            'total_call_logs_count' => 2,
            'total_call_duration' => 62,
        ]);
});

test('a verification call is left out of the figures too', function () {
    // Placed the day the order was confirmed, before it was ever loaded for
    // delivery. The breakdown modal keeps it out of both RMO tabs, so the cards
    // that sit above the modal keep it out as well.
    statCall('09170000003', $this->workspace, $this->csrA->id, 45, CallLogPersona::VERIFICATION);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJson([
            'total_call_logs_count' => 2,
            'total_call_duration' => 62,
        ]);
});

test('a rider call counts alongside the customer calls', function () {
    statCall('09180000001', $this->workspace, $this->csrA->id, 20, CallLogPersona::RIDER);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJson([
            'total_call_logs_count' => 3,
            'total_call_duration' => 82,
            'connected_call_logs_count' => 2,
        ]);
});

test('a CSR who placed no calls reports zero rather than the whole day', function () {
    $idle = PancakeUser::create(['name' => 'CSR C']);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $idle->id,
    ], $this->workspace)
        ->assertJson([
            'total_call_logs_count' => 0,
            'total_call_duration' => 0,
            'connected_call_logs_count' => 0,
        ]);
});

test('the page filter still narrows the cards on top of the caller', function () {
    $otherPage = Page::factory()->create(['workspace_id' => $this->workspace->id]);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
        'filter' => ['page_id' => (string) $otherPage->id],
    ], $this->workspace)
        // CSR A placed two calls, but neither reached an order on that page.
        ->assertJson(['total_call_logs_count' => 0]);
});

test('the order figures still follow the assignee filter', function () {
    rmoStats(['delivery_date' => STAT_DATE], $this->workspace)
        ->assertJson(['total_for_delivery_today' => 2]);

    rmoStats([
        'delivery_date' => STAT_DATE,
        'assignee_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJson(['delivered_count' => 0, 'called_count' => 0]);
});

test('the endpoint answers with every card the page draws', function () {
    rmoStats(['delivery_date' => STAT_DATE], $this->workspace)
        ->assertJsonStructure([
            'total_for_delivery_today',
            'called_count',
            'delivered_count',
            'returning_count',
            'problematic_count',
            'total_call_logs_count',
            'total_call_duration',
            'connected_call_logs_count',
            'avg_call_duration',
            'hit_rate',
        ]);
});

test('average duration and hit rate are computed server-side', function () {
    // CSR A: two calls, 62s of talk time, one of them connected. Asserted as
    // ints because JSON renders a whole float without its decimal point.
    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertJsonPath('avg_call_duration', 62)
        ->assertJsonPath('hit_rate', 50);
});

test('both are null rather than zero when nobody has called', function () {
    $idle = PancakeUser::create(['name' => 'CSR C']);

    // 0% would read as "everyone hung up", which is not the same as
    // "nobody has picked up the phone yet".
    rmoStats([
        'delivery_date' => STAT_DATE,
        'caller_id' => $idle->id,
    ], $this->workspace)
        ->assertJsonPath('avg_call_duration', null)
        ->assertJsonPath('hit_rate', null);
});

test('the endpoint and the daily report agree on the derived figures', function () {
    $day = RmoDailyStats::for($this->workspace, STAT_DATE);

    $stats = rmoStats(['delivery_date' => STAT_DATE], $this->workspace);

    expect((float) $stats->json('avg_call_duration'))->toBe($day['avg_call_duration'])
        ->and((float) $stats->json('hit_rate'))->toBe($day['hit_rate']);
});

test('the page render no longer carries the stat figures', function () {
    rmoPage(['delivery_date' => STAT_DATE], $this->workspace)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('total_call_logs_count')
            ->missing('total_for_delivery_today')
        );
});

test('the stats endpoint is behind the same password gate as the page', function () {
    $this->get(route('public-page.rmo-management.stats', [
        'workspace' => $this->workspace->slug,
        'delivery_date' => STAT_DATE,
    ]))->assertForbidden();
});

test('the page echoes the assignee, confirmee and caller back so the pickers render', function () {
    rmoPage([
        'delivery_date' => STAT_DATE,
        'assignee_id' => $this->csrA->id,
        'confirmee_id' => $this->csrB->id,
        'caller_id' => $this->csrA->id,
    ], $this->workspace)
        ->assertInertia(fn ($page) => $page
            ->where('query.assignee_id', $this->csrA->id)
            ->where('query.confirmee_id', $this->csrB->id)
            ->where('query.caller_id', $this->csrA->id)
        );
});
