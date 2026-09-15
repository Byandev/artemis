<?php

use App\Models\CallLog;
use App\Models\Order;
use App\Models\PancakeUserDailyCallReport;
use App\Models\PancakeUserPosDailyReport;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Support\RmoDailyStats;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\OrderPhoneNumberReport;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The CSR dashboard's stat cards.
 *
 * The analytics page reports the workspace; this reports one person. Same
 * nightly POS rollup, same previous-period comparison, narrowed to the pancake
 * accounts linked to the signed-in user — so a CSR's card is their own row of
 * the analytics breakdown and nobody else's.
 */
const DASH_FROM = '2026-08-08';
const DASH_TO = '2026-08-14';
// The equally long stretch ending the day before DASH_FROM, which is what the
// card's arrow is measured against.
const DASH_PREV = '2026-08-05';

beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->workspace->update(['csr_dashboard_module_enabled' => true]);
    $this->shop = Shop::factory()->create(['workspace_id' => $this->workspace->id]);
});

/** A pancake account on this workspace's shops, optionally linked to a user. */
function dashCsr(int $shopId, string $name, ?User $systemUser = null): PancakeUser
{
    $csr = PancakeUser::create([
        'name' => $name,
        'user_id' => $systemUser?->id,
    ]);

    // The pivot carries a uuid primary key of its own, hence the explicit insert.
    DB::table('pancake_shop_users')->insert([
        'id' => (string) Str::uuid(),
        'shop_id' => $shopId,
        'user_id' => $csr->id,
    ]);

    return $csr;
}

/**
 * A day of the POS rollup.
 *
 * `$settled` carries the parcel money the RTS rate is read from —
 * `returning` and `delivered` — which the sales tests have no use for.
 *
 * @param  array<string, float>  $settled
 */
function dashPos(Workspace $workspace, int $shopId, PancakeUser $csr, string $date, float $sales, int $orders, array $settled = []): void
{
    PancakeUserPosDailyReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shopId,
        'date' => $date,
        'total_sales' => $sales,
        'total_orders' => $orders,
        ...$settled,
    ]);
}

/**
 * A day of the nightly call rollup.
 *
 * Every call card reads this table, so the tests below write the columns they
 * care about and leave the rest at their defaults.
 *
 * @param  array<string, int>  $figures
 */
function dashCall(Workspace $workspace, int $shopId, PancakeUser $csr, string $date, array $figures): void
{
    PancakeUserDailyCallReport::create([
        'workspace_id' => $workspace->id,
        'pancake_user_id' => $csr->id,
        'shop_id' => $shopId,
        'date' => $date,
        ...$figures,
    ]);
}

/** One dashboard card, over the range every test here uses. */
function dashCard(User $actor, Workspace $workspace, string $card)
{
    return test()->actingAs($actor)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/dashboard-{$card}?from=".DASH_FROM.'&to='.DASH_TO
    );
}

function dashSales(User $actor, Workspace $workspace)
{
    return dashCard($actor, $workspace, 'sales');
}

function dashRts(User $actor, Workspace $workspace)
{
    return dashCard($actor, $workspace, 'rts');
}

/** A member of the workspace with a pancake account of their own. */
function dashMember(Workspace $workspace, int $shopId): array
{
    $member = User::factory()->create();
    $workspace->users()->attach($member->id);

    return [$member, dashCsr($shopId, 'Mine', $member)];
}

it('sums only the signed-in user\'s own pancake accounts', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $mine = dashCsr($this->shop->id, 'Mine', $member);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1500, 3);
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-12', 500, 2);
    dashPos($this->workspace, $this->shop->id, $theirs, '2026-08-11', 9000, 40);

    $response = dashSales($member, $this->workspace);

    $response->assertOk()
        ->assertJsonPath('value', 2000)
        ->assertJsonPath('orders', 5);
});

it('adds up every pancake account linked to the same user', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $first = dashCsr($this->shop->id, 'First seat', $member);
    $second = dashCsr($this->shop->id, 'Second seat', $member);

    dashPos($this->workspace, $this->shop->id, $first, '2026-08-10', 700, 1);
    dashPos($this->workspace, $this->shop->id, $second, '2026-08-10', 300, 2);

    dashSales($member, $this->workspace)->assertJsonPath('value', 1000);
});

it('measures the change against the period before the range', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $mine = dashCsr($this->shop->id, 'Mine', $member);

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1200, 4);
    dashPos($this->workspace, $this->shop->id, $mine, DASH_PREV, 600, 2);

    dashSales($member, $this->workspace)
        ->assertJsonPath('value', 1200)
        ->assertJsonPath('previous_value', 600)
        ->assertJsonPath('previous_orders', 2)
        ->assertJsonPath('change', 100)
        ->assertJsonPath('previous_period', ['from' => '2026-08-01', 'to' => '2026-08-07']);
});

it('reports no change rather than a flat one when the previous period is empty', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $mine = dashCsr($this->shop->id, 'Mine', $member);
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 400, 1);

    dashSales($member, $this->workspace)->assertJsonPath('change', null);
});

it('reads zero for a user with no pancake account linked', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $theirs = dashCsr($this->shop->id, 'Theirs');
    dashPos($this->workspace, $this->shop->id, $theirs, '2026-08-10', 9000, 40);

    dashSales($member, $this->workspace)
        ->assertOk()
        ->assertJsonPath('value', 0)
        ->assertJsonPath('orders', 0);
});

it('ignores a pancake account attached to another workspace\'s shops', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    ['workspace' => $other] = makeWorkspaceWithOwner();
    $otherShop = Shop::factory()->create(['workspace_id' => $other->id]);

    $elsewhere = dashCsr($otherShop->id, 'Elsewhere', $member);
    dashPos($this->workspace, $this->shop->id, $elsewhere, '2026-08-10', 5000, 10);

    dashSales($member, $this->workspace)->assertJsonPath('value', 0);
});

it('refuses a user who is not a member of the workspace', function () {
    $outsider = User::factory()->create();

    dashSales($outsider, $this->workspace)->assertForbidden();
});

it('is gone when the CSR dashboard module is off', function () {
    $this->workspace->update(['csr_dashboard_module_enabled' => false]);

    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    dashSales($member, $this->workspace)->assertNotFound();
});

/**
 * The RTS Rate card.
 *
 * Returning over everything that settled, summed across the whole range rather
 * than averaged per day — and over the signed-in CSR's own rows only, like
 * the Sales card beside it.
 */
it('reads the rate off the user\'s own settled parcels', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4, [
        'returning' => 200,
        'delivered' => 800,
    ]);
    // A stellar period for somebody else must not pull the card down.
    dashPos($this->workspace, $this->shop->id, $theirs, '2026-08-10', 9000, 40, [
        'returning' => 0,
        'delivered' => 9000,
    ]);

    dashRts($member, $this->workspace)
        ->assertOk()
        ->assertJsonPath('value', 20)
        ->assertJsonPath('returning_amount', 200);
});

it('divides once over the range rather than averaging the days', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // A day of one returned parcel in two, and a day of one in two hundred.
    // Averaging the two days would read 25%; the range's own rate is 1.5%.
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 100, 1, [
        'returning' => 1,
        'delivered' => 1,
    ]);
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-12', 20000, 200, [
        'returning' => 2,
        'delivered' => 198,
    ]);

    dashRts($member, $this->workspace)->assertJsonPath('value', 1.49);
});

it('reports the rate move in percentage points', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4, [
        'returning' => 150,
        'delivered' => 850,
    ]);
    dashPos($this->workspace, $this->shop->id, $mine, DASH_PREV, 1000, 4, [
        'returning' => 120,
        'delivered' => 880,
    ]);

    dashRts($member, $this->workspace)
        ->assertJsonPath('value', 15)
        ->assertJsonPath('previous_value', 12)
        // 12% to 15% is "+3 pts", not "+25%".
        ->assertJsonPath('change', 3);
});

it('has no rate at all when nothing in the range settled', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // Orders taken, none of them delivered or returned yet: a 0% would read as
    // a flawless period rather than an unfinished one.
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4);

    dashRts($member, $this->workspace)
        ->assertOk()
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null);
});

it('has no rate for a user with no pancake account linked', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $theirs = dashCsr($this->shop->id, 'Theirs');
    dashPos($this->workspace, $this->shop->id, $theirs, '2026-08-10', 9000, 40, [
        'returning' => 900,
        'delivered' => 8100,
    ]);

    dashRts($member, $this->workspace)
        ->assertOk()
        ->assertJsonPath('value', null)
        ->assertJsonPath('returning_amount', 0);
});

it('gates the RTS card the same way as the sales card', function () {
    dashRts(User::factory()->create(), $this->workspace)->assertForbidden();

    $this->workspace->update(['csr_dashboard_module_enabled' => false]);
    [$member] = dashMember($this->workspace, $this->shop->id);

    dashRts($member, $this->workspace)->assertNotFound();
});

/*
|--------------------------------------------------------------------------
| The call cards
|--------------------------------------------------------------------------
|
| All nine read pancake_user_daily_call_reports, which is keyed by
| pancake_user_id — so the same identity narrowing as the sales cards, over a
| different rollup. `all` is every call placed; `rmo` and `verification` are
| the two halves of it.
*/

/** A day's call rollup for the signed-in CSR, with every column these tests read. */
function dashCallDay(Workspace $workspace, int $shopId, PancakeUser $csr, string $date = '2026-08-10'): void
{
    dashCall($workspace, $shopId, $csr, $date, [
        'total_called' => 50,
        'total_call_time' => 3000,
        'total_rmo_called' => 30,
        'total_rmo_call_time' => 2400,
        'total_rmo_orders' => 10,
        'total_rmo_real_called' => 12,
        'total_verification_called' => 20,
        'total_verification_call_time' => 600,
        'total_verified_orders' => 8,
    ]);
}

it('reports every call the CSR placed, of any kind', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    dashCallDay($this->workspace, $this->shop->id, $mine);

    dashCard($member, $this->workspace, 'rmo-called')
        ->assertOk()
        ->assertJsonPath('value', 50);

    dashCard($member, $this->workspace, 'rmo-time')
        ->assertJsonPath('value', 3000)
        ->assertJsonPath('calls', 50)
        // 3000 seconds over 50 calls.
        ->assertJsonPath('average_seconds', 60);
});

it('splits RMO calls from verification calls', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    dashCallDay($this->workspace, $this->shop->id, $mine);

    dashCard($member, $this->workspace, 'total-rmo-called')
        ->assertJsonPath('value', 30)
        ->assertJsonPath('seconds', 2400)
        // The deliveries behind them — a parcel rung three times is one order.
        ->assertJsonPath('orders', 10);

    dashCard($member, $this->workspace, 'rmo-call-time')
        ->assertJsonPath('value', 2400)
        ->assertJsonPath('calls', 30)
        ->assertJsonPath('average_seconds', 80);

    dashCard($member, $this->workspace, 'calls-placed')
        ->assertJsonPath('value', 20)
        // The orders behind them — an order rung three times is one order.
        ->assertJsonPath('orders', 8);

    dashCard($member, $this->workspace, 'real-conversations')
        ->assertJsonPath('value', 600)
        ->assertJsonPath('calls', 20)
        ->assertJsonPath('average_seconds', 30);
});

it('reads the RMO conversations and the hit rate off the same two columns', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    dashCallDay($this->workspace, $this->shop->id, $mine);

    dashCard($member, $this->workspace, 'rmo-real-conversations')
        ->assertJsonPath('value', 12)
        ->assertJsonPath('calls', 30)
        ->assertJsonPath('rate', 40);

    dashCard($member, $this->workspace, 'rmo-hit-rate')
        ->assertJsonPath('value', 40)
        ->assertJsonPath('conversations', 12)
        ->assertJsonPath('calls', 30);
});

it('leaves another CSR\'s calls out of every call card', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    dashCallDay($this->workspace, $this->shop->id, $mine);
    dashCall($this->workspace, $this->shop->id, $theirs, '2026-08-10', [
        'total_called' => 900,
        'total_rmo_called' => 500,
        'total_verification_called' => 400,
        'total_verified_orders' => 300,
    ]);

    dashCard($member, $this->workspace, 'rmo-called')->assertJsonPath('value', 50);
    dashCard($member, $this->workspace, 'total-rmo-called')->assertJsonPath('value', 30);
    dashCard($member, $this->workspace, 'calls-placed')
        ->assertJsonPath('value', 20)
        ->assertJsonPath('orders', 8);
    dashCard($member, $this->workspace, 'verified-orders')->assertJsonPath('orders', 8);
});

it('measures the call cards against the period before the range', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', ['total_called' => 60]);
    dashCall($this->workspace, $this->shop->id, $mine, DASH_PREV, ['total_called' => 40]);

    dashCard($member, $this->workspace, 'rmo-called')
        ->assertJsonPath('value', 60)
        ->assertJsonPath('previous_value', 40)
        // A count moves relatively: 40 to 60 is "+50%".
        ->assertJsonPath('change', 50);
});

it('reports the hit rate move in percentage points', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', [
        'total_rmo_called' => 100,
        'total_rmo_real_called' => 45,
    ]);
    dashCall($this->workspace, $this->shop->id, $mine, DASH_PREV, [
        'total_rmo_called' => 100,
        'total_rmo_real_called' => 40,
    ]);

    dashCard($member, $this->workspace, 'rmo-hit-rate')
        ->assertJsonPath('value', 45)
        ->assertJsonPath('previous_value', 40)
        // 40% to 45% is "+5 pts", not "+12.5%".
        ->assertJsonPath('change', 5);
});

it('has no hit rate when no RMO call was placed', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // Verification work only: a 0% hit rate would read as "everyone hung up",
    // which is not "nobody called".
    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', [
        'total_called' => 20,
        'total_verification_called' => 20,
    ]);

    dashCard($member, $this->workspace, 'rmo-hit-rate')
        ->assertOk()
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null);

    // No RMO call placed, so no per-call average either. The all-calls card
    // beside it does have calls to divide by, and answers 0 rather than null:
    // twenty calls with no recorded time is a reading, not a missing one.
    dashCard($member, $this->workspace, 'rmo-call-time')
        ->assertJsonPath('calls', 0)
        ->assertJsonPath('average_seconds', null);

    dashCard($member, $this->workspace, 'rmo-time')
        ->assertJsonPath('calls', 20)
        ->assertJsonPath('average_seconds', 0);
});

it('reads zero on every call card for a user with no pancake account linked', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $theirs = dashCsr($this->shop->id, 'Theirs');
    dashCallDay($this->workspace, $this->shop->id, $theirs);

    foreach (['rmo-called', 'rmo-time', 'total-rmo-called', 'rmo-call-time', 'calls-placed', 'real-conversations'] as $card) {
        dashCard($member, $this->workspace, $card)
            ->assertOk()
            ->assertJsonPath('value', 0);
    }

    dashCard($member, $this->workspace, 'rmo-real-conversations')->assertJsonPath('value', 0);
    // Both rate cards read as "no rate", not as a rate of nothing.
    dashCard($member, $this->workspace, 'rmo-hit-rate')->assertJsonPath('value', null);
    dashCard($member, $this->workspace, 'verified-orders')->assertJsonPath('value', null);
});

it('gates every card the same way', function () {
    $cards = [
        'sales', 'rts', 'rmo-called', 'rmo-time', 'total-rmo-called',
        'rmo-call-time', 'rmo-real-conversations', 'rmo-hit-rate',
        'calls-placed', 'real-conversations', 'confirmed-risky-orders',
        'verified-orders',
    ];

    $outsider = User::factory()->create();

    foreach ($cards as $card) {
        dashCard($outsider, $this->workspace, $card)->assertForbidden();
    }

    $this->workspace->update(['csr_dashboard_module_enabled' => false]);
    [$member] = dashMember($this->workspace, $this->shop->id);

    foreach ($cards as $card) {
        dashCard($member, $this->workspace, $card)->assertNotFound();
    }
});

/*
|--------------------------------------------------------------------------
| Confirmed Risky Orders, and the coverage read against it
|--------------------------------------------------------------------------
|
| The analytics card counts the workspace's risky orders off the nightly
| page_order_report_breakdown_daily_records rollup, which is bucketed by shop
| and customer history with no CSR column. This counts the same orders off
| pancake_orders instead, keyed by who confirmed them — same two rules, same
| `initial` snapshot, same status and confirmed_at filters.
*/

/**
 * An order confirmed by a CSR, with the customer history it arrived with.
 *
 * `$fail`/`$success` are the `initial` phone-number report. Passing null for
 * both writes no report at all, which is one of the two ways an order counts
 * as risky.
 */
function dashOrder(Workspace $workspace, PancakeUser $csr, string $date, ?int $fail = null, ?int $success = null): Order
{
    $order = Order::factory()->forWorkspace($workspace)->create([
        'status' => 1,
        'confirmed_at' => $date.' 09:00:00',
        'confirmed_by' => $csr->id,
    ]);

    if ($fail !== null || $success !== null) {
        OrderPhoneNumberReport::create([
            'order_id' => $order->id,
            'phone_number' => '09'.fake()->unique()->numerify('########'),
            'order_fail' => $fail ?? 0,
            'order_success' => $success ?? 0,
            'warning' => 0,
            'type' => 'initial',
        ]);
    }

    return $order;
}

it('counts the CSR\'s own confirmed risky orders, both reasons apart', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // No report at all — nothing known about the customer.
    dashOrder($this->workspace, $mine, '2026-08-10');
    dashOrder($this->workspace, $mine, '2026-08-10');
    // 3 back of 7, which is 43% over the 6-order floor: risky.
    dashOrder($this->workspace, $mine, '2026-08-11', fail: 3, success: 4);
    // 4 back of 10 — exactly 40%, which the threshold includes.
    dashOrder($this->workspace, $mine, '2026-08-11', fail: 4, success: 6);
    // 2 back of 10 is only 20%: a customer with a record of taking delivery.
    dashOrder($this->workspace, $mine, '2026-08-12', fail: 2, success: 8);
    // 1 back of 1 is 100%, but on too short a history to read as a habit.
    dashOrder($this->workspace, $mine, '2026-08-12', fail: 1, success: 0);

    dashCard($member, $this->workspace, 'confirmed-risky-orders')
        ->assertOk()
        ->assertJsonPath('value', 4)
        ->assertJsonPath('no_report', 2)
        ->assertJsonPath('high_rts', 2)
        // Every order confirmed in the range, risky or not.
        ->assertJsonPath('orders', 6);
});

it('treats a report with no prior orders on it as no report', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // A number Pancake had seen, with nothing on it — indistinguishable from
    // one it had never seen, and deliberately so.
    dashOrder($this->workspace, $mine, '2026-08-10', fail: 0, success: 0);

    dashCard($member, $this->workspace, 'confirmed-risky-orders')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('no_report', 1);
});

it('leaves another CSR\'s risky orders out', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    dashOrder($this->workspace, $mine, '2026-08-10');
    dashOrder($this->workspace, $theirs, '2026-08-10');
    dashOrder($this->workspace, $theirs, '2026-08-10', fail: 5, success: 1);

    dashCard($member, $this->workspace, 'confirmed-risky-orders')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('orders', 1);
});

it('leaves out cancelled orders and ones never confirmed', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashOrder($this->workspace, $mine, '2026-08-10');

    // Status 6 and 7 are cancelled/removed — out everywhere orders are counted.
    foreach ([6, 7] as $status) {
        Order::factory()->forWorkspace($this->workspace)->create([
            'status' => $status,
            'confirmed_at' => '2026-08-10 09:00:00',
            'confirmed_by' => $mine->id,
        ]);
    }

    // Never confirmed: no day to land on, and no CSR to land with.
    Order::factory()->forWorkspace($this->workspace)->create([
        'status' => 1,
        'confirmed_at' => null,
        'confirmed_by' => $mine->id,
    ]);

    dashCard($member, $this->workspace, 'confirmed-risky-orders')
        ->assertJsonPath('value', 1)
        ->assertJsonPath('orders', 1);
});

it('reads the initial snapshot and ignores the latest one', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // 2 back of 10 at confirmation time: not risky, whatever happened after.
    $order = dashOrder($this->workspace, $mine, '2026-08-10', fail: 2, success: 8);

    OrderPhoneNumberReport::create([
        'order_id' => $order->id,
        'phone_number' => '09'.fake()->unique()->numerify('########'),
        'order_fail' => 9,
        'order_success' => 1,
        'warning' => 0,
        'type' => 'latest',
    ]);

    dashCard($member, $this->workspace, 'confirmed-risky-orders')
        ->assertJsonPath('value', 0)
        ->assertJsonPath('orders', 1);
});

it('reads the verified coverage against the CSR\'s own risky orders', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    // Eight orders verified, out of ten risky ones they confirmed.
    dashCallDay($this->workspace, $this->shop->id, $mine);
    foreach (range(1, 10) as $ignored) {
        dashOrder($this->workspace, $mine, '2026-08-10');
    }

    dashCard($member, $this->workspace, 'verified-orders')
        ->assertOk()
        ->assertJsonPath('value', 80)
        ->assertJsonPath('orders', 8)
        ->assertJsonPath('calls', 20)
        ->assertJsonPath('risky_orders', 10);
});

it('has no coverage rate when nothing in the range was risky', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashCallDay($this->workspace, $this->shop->id, $mine);

    // A dash, not 0%: there is no rate to report rather than a rate of none.
    dashCard($member, $this->workspace, 'verified-orders')
        ->assertOk()
        ->assertJsonPath('value', null)
        ->assertJsonPath('change', null)
        ->assertJsonPath('orders', 8);
});

it('reports the coverage move in percentage points', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', ['total_verified_orders' => 6]);
    dashCall($this->workspace, $this->shop->id, $mine, DASH_PREV, ['total_verified_orders' => 4]);

    foreach (range(1, 10) as $ignored) {
        dashOrder($this->workspace, $mine, '2026-08-10');
        dashOrder($this->workspace, $mine, DASH_PREV);
    }

    dashCard($member, $this->workspace, 'verified-orders')
        ->assertJsonPath('value', 60)
        ->assertJsonPath('previous_value', 40)
        // 40% to 60% is "+20 pts", not "+50%".
        ->assertJsonPath('change', 20);
});

it('reads zero risky orders for a user with no pancake account linked', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $theirs = dashCsr($this->shop->id, 'Theirs');
    dashOrder($this->workspace, $theirs, '2026-08-10');

    dashCard($member, $this->workspace, 'confirmed-risky-orders')
        ->assertOk()
        ->assertJsonPath('value', 0)
        ->assertJsonPath('orders', 0);
});
/*
|--------------------------------------------------------------------------
| Effort against results
|--------------------------------------------------------------------------
|
| The two call cards' totals spread across the days that made them, and again
| across the hours of the day — narrowed to the CSR's own calls like everything
| else on the page. The daily chart reads the nightly rollup; the hourly one
| reads the call log that rollup is built from, since the rollup keeps no hour.
*/

/** A logged call of $seconds at $time, RMO work unless told otherwise. */
function dashLoggedCall(
    Workspace $workspace,
    PancakeUser $csr,
    string $date,
    string $time,
    int $seconds,
    bool $rmo = true,
    bool $ordered = true,
): void {
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $csr->id,
        'phone_number' => '09170000009',
        'call_date' => $date,
        'call_time' => $time,
        'duration' => $seconds,
        'order_id' => $ordered
            ? Order::factory()->forWorkspace($workspace)->create()->id
            : null,
        'order_for_delivery_id' => $rmo ? 1 : null,
    ]);
}

it('spreads the CSR\'s own calls across the days of the range', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', [
        'total_called' => 12,
        'total_rmo_called' => 7,
        'total_rmo_real_called' => 4,
        'total_verification_called' => 5,
        'total_verification_real_called' => 3,
    ]);
    dashCall($this->workspace, $this->shop->id, $theirs, '2026-08-10', [
        'total_called' => 900,
        'total_rmo_called' => 500,
    ]);

    $response = dashCard($member, $this->workspace, 'daily-effort')->assertOk();

    // Every day in the range, zeros included — a chart that skipped the quiet
    // days would draw a week the CSR did not work.
    expect($response->json('days'))->toHaveCount(7);
    expect($response->json('range'))->toBe(['from' => DASH_FROM, 'to' => DASH_TO]);

    $day = collect($response->json('days'))->firstWhere('date', '2026-08-10');

    expect($day)->toMatchArray([
        'total_calls' => 12,
        'calls' => 7,
        'real' => 4,
        'verification_calls' => 5,
        'verification_real' => 3,
    ]);

    // The range's totals are the days added up, and carry nobody else's work.
    $response->assertJsonPath('totals.total_calls', 12)
        ->assertJsonPath('totals.calls', 7)
        ->assertJsonPath('totals.verification_calls', 5);
});

it('folds the CSR\'s own calls into one round of the clock', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    $long = RmoDailyStats::CONNECTED_CALL_MIN_SECONDS + 10;

    // Two days' 9am, added into a single 9am.
    dashLoggedCall($this->workspace, $mine, '2026-08-10', '09:15:00', $long);
    dashLoggedCall($this->workspace, $mine, '2026-08-11', '09:45:00', $long);
    // A hello and a hang-up: effort, but not a conversation.
    dashLoggedCall($this->workspace, $mine, '2026-08-11', '09:50:00', 0);
    // Verification work in the same hour — the other kind of call.
    dashLoggedCall($this->workspace, $mine, '2026-08-12', '09:05:00', $long, rmo: false);
    // Somebody else's hour entirely.
    dashLoggedCall($this->workspace, $theirs, '2026-08-10', '09:30:00', $long);

    $response = dashCard($member, $this->workspace, 'hourly-effort')->assertOk();

    expect($response->json('hours'))->toHaveCount(24);
    $response->assertJsonPath('day_count', 7);

    $nine = collect($response->json('hours'))->firstWhere('hour', 9);

    expect($nine)->toMatchArray([
        'total_calls' => 4,
        'calls' => 3,
        'real' => 2,
        'verification_calls' => 1,
        'verification_real' => 1,
    ]);
});

it('leaves a call matched to no order out, as the nightly sync does', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashLoggedCall($this->workspace, $mine, '2026-08-10', '10:00:00', 30);
    // No order beside it, so the rollup never counts it — nor does this.
    dashLoggedCall($this->workspace, $mine, '2026-08-10', '10:30:00', 30, ordered: false);

    dashCard($member, $this->workspace, 'hourly-effort')
        ->assertJsonPath('totals.total_calls', 1);
});

it('draws empty charts for a user with no pancake account linked', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $theirs = dashCsr($this->shop->id, 'Theirs');
    dashCall($this->workspace, $this->shop->id, $theirs, '2026-08-10', ['total_called' => 90]);
    dashLoggedCall($this->workspace, $theirs, '2026-08-10', '09:00:00', 30);

    // The buckets are still there — the chart draws a flat range, not nothing.
    $daily = dashCard($member, $this->workspace, 'daily-effort')->assertOk();
    expect($daily->json('days'))->toHaveCount(7);
    $daily->assertJsonPath('totals.total_calls', 0);

    $hourly = dashCard($member, $this->workspace, 'hourly-effort')->assertOk();
    expect($hourly->json('hours'))->toHaveCount(24);
    $hourly->assertJsonPath('totals.total_calls', 0);
});

it('gates the effort charts the same way', function () {
    $outsider = User::factory()->create();

    foreach (['daily-effort', 'hourly-effort'] as $card) {
        dashCard($outsider, $this->workspace, $card)->assertForbidden();
    }

    $this->workspace->update(['csr_dashboard_module_enabled' => false]);
    [$member] = dashMember($this->workspace, $this->shop->id);

    foreach (['daily-effort', 'hourly-effort'] as $card) {
        dashCard($member, $this->workspace, $card)->assertNotFound();
    }
});

/*
|--------------------------------------------------------------------------
| The breakdown — the CSR's own days
|--------------------------------------------------------------------------
|
| Every figure of both nightly rollups, against a date instead of against a
| CSR's name. A CSR works more than one shop and both rollups are split by
| shop, so a day is the sum of that day's shop rows.
*/

/** One page of the breakdown. */
function dashBreakdown(User $actor, Workspace $workspace, array $query = [])
{
    $params = http_build_query(['from' => DASH_FROM, 'to' => DASH_TO, ...$query]);

    return test()->actingAs($actor)->getJson(
        "/api/workspaces/{$workspace->slug}/csrs/stats/dashboard-breakdown?{$params}"
    );
}

/** The dates the breakdown listed, in the order it listed them. */
function dashBreakdownDates(User $actor, Workspace $workspace, array $query = []): array
{
    return collect(dashBreakdown($actor, $workspace, $query)->json('data'))
        ->pluck('date')
        ->all();
}

it('lists one row per day, newest first, for the CSR alone', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $theirs = dashCsr($this->shop->id, 'Theirs');

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4);
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-12', 2000, 6);
    // Somebody else's day, which must not appear as a row of its own.
    dashPos($this->workspace, $this->shop->id, $theirs, '2026-08-13', 9000, 40);

    expect(dashBreakdownDates($member, $this->workspace))
        ->toBe(['2026-08-12', '2026-08-10']);
});

it('sums a day across the shops the CSR worked', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $second = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4);
    dashPos($this->workspace, $second->id, $mine, '2026-08-10', 700, 3);

    $row = collect(dashBreakdown($member, $this->workspace)->json('data'))->sole();

    expect((float) $row['total_sales'])->toBe(1700.0);
    expect($row['total_orders'])->toBe(7);
});

it('merges the two rollups on the date', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4, [
        'returning' => 200,
        'delivered' => 800,
    ]);
    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', [
        'total_called' => 50,
        'total_call_time' => 3000,
        'total_rmo_assigned_count' => 9,
        'total_rmo_confirmed_count' => 12,
        'total_rmo_called' => 30,
        'total_rmo_call_time' => 2400,
        'longest_rmo_call_time' => 180,
    ]);

    $row = collect(dashBreakdown($member, $this->workspace)->json('data'))->sole();

    expect($row)->toMatchArray([
        'total_orders' => 4,
        // The call report's own total_called, aliased away from the RMO
        // assignments that took the name.
        'total_all_called' => 50,
        'total_all_call_time' => 3000,
        'total_called' => 9,
        'total_confirmed' => 12,
        'total_rmo_call_attempts' => 30,
        'total_call_time' => 2400,
        'longest_rmo_call_time' => 180,
    ]);

    // 200 back of 1000 settled, and 9 assigned of 12 confirmed.
    expect((float) $row['rts_rate'])->toBe(20.0);
    expect((float) $row['rmo_percentage'])->toBe(75.0);
});

it('takes the longest call of a day rather than adding the shops up', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);
    $second = Shop::factory()->create(['workspace_id' => $this->workspace->id]);

    dashCall($this->workspace, $this->shop->id, $mine, '2026-08-10', ['longest_rmo_call_time' => 180]);
    dashCall($this->workspace, $second->id, $mine, '2026-08-10', ['longest_rmo_call_time' => 240]);

    $row = collect(dashBreakdown($member, $this->workspace)->json('data'))->sole();

    expect($row['longest_rmo_call_time'])->toBe(240);
});

it('leaves out a day where nothing moved', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 1000, 4);
    // A rollup row written with every figure at zero is not a row of the table:
    // the effort charts are where a quiet day still shows.
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-11', 0, 0);

    expect(dashBreakdownDates($member, $this->workspace))->toBe(['2026-08-10']);
});

it('sorts on any figure column, and falls back to the date', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-10', 3000, 2);
    dashPos($this->workspace, $this->shop->id, $mine, '2026-08-11', 1000, 9);

    expect(dashBreakdownDates($member, $this->workspace, ['sort' => '-total_sales']))
        ->toBe(['2026-08-10', '2026-08-11']);
    expect(dashBreakdownDates($member, $this->workspace, ['sort' => 'total_orders']))
        ->toBe(['2026-08-10', '2026-08-11']);
    expect(dashBreakdownDates($member, $this->workspace, ['sort' => 'date']))
        ->toBe(['2026-08-10', '2026-08-11']);

    // A hand-edited or stale sort key reads as the date, newest first, rather
    // than erroring.
    expect(dashBreakdownDates($member, $this->workspace, ['sort' => 'nonsense']))
        ->toBe(['2026-08-11', '2026-08-10']);
});

it('pages the days', function () {
    [$member, $mine] = dashMember($this->workspace, $this->shop->id);

    foreach (['2026-08-10', '2026-08-11', '2026-08-12'] as $date) {
        dashPos($this->workspace, $this->shop->id, $mine, $date, 1000, 4);
    }

    $first = dashBreakdown($member, $this->workspace, ['per_page' => 2]);

    $first->assertJsonPath('total', 3)
        ->assertJsonPath('per_page', 2)
        ->assertJsonPath('current_page', 1);

    expect($first->json('data'))->toHaveCount(2);

    expect(dashBreakdownDates($member, $this->workspace, ['per_page' => 2, 'page' => 2]))
        ->toBe(['2026-08-10']);
});

it('lists nothing for a user with no pancake account linked', function () {
    $member = User::factory()->create();
    $this->workspace->users()->attach($member->id);

    $theirs = dashCsr($this->shop->id, 'Theirs');
    dashPos($this->workspace, $this->shop->id, $theirs, '2026-08-10', 9000, 40);

    dashBreakdown($member, $this->workspace)
        ->assertOk()
        ->assertJsonPath('total', 0)
        ->assertJsonPath('data', []);
});

it('gates the breakdown the same way', function () {
    dashBreakdown(User::factory()->create(), $this->workspace)->assertForbidden();

    $this->workspace->update(['csr_dashboard_module_enabled' => false]);
    [$member] = dashMember($this->workspace, $this->shop->id);

    dashBreakdown($member, $this->workspace)->assertNotFound();
});
