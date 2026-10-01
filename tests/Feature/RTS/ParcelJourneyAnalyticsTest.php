<?php

use App\Models\Order;
use App\Models\ParcelJourney;
use App\Models\ParcelJourneyNotification;
use App\Models\ParcelJourneyNotificationLog;
use App\Models\Shop;
use App\Models\User;

/**
 * The parcel journey figures: the four stat cards and the per-shop table.
 *
 * They used to ride along with the page render, so the templates table waited
 * on seven aggregates before anything appeared, and sorting the shop table
 * re-rendered the whole page. Each now fetches on its own — same arithmetic as
 * before: the nightly rollup in parcel_journey_notification_logs for days
 * already closed out, plus the live notification rows for the days that have
 * not been rolled up yet.
 */
const PJ_WINDOW_START = '2026-07-01';
const PJ_WINDOW_END = '2026-07-31';

function pjNotification(Order $order, string $type, string $status, string $createdAt): ParcelJourneyNotification
{
    $journey = ParcelJourney::factory()->create(['order_id' => $order->id]);

    return ParcelJourneyNotification::create([
        'order_id' => $order->id,
        'parcel_journey_id' => $journey->id,
        'type' => $type,
        'status' => $status,
        'receiver_name' => 'Cx',
        'receiver_identity' => '09170000001',
        'message' => 'Update lang po.',
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ]);
}

function pjKpi(string $card, $workspace, User $user, array $params = [])
{
    return test()->actingAs($user)->getJson(route("api.workspaces.rts.parcel-journey.kpi.{$card}", [
        'workspace' => $workspace->slug,
        'start_date' => PJ_WINDOW_START,
        'end_date' => PJ_WINDOW_END,
        ...$params,
    ]));
}

function pjShops($workspace, User $user, array $params = [])
{
    return test()->actingAs($user)->getJson(route('api.workspaces.rts.parcel-journey.shops', [
        'workspace' => $workspace->slug,
        'start_date' => PJ_WINDOW_START,
        'end_date' => PJ_WINDOW_END,
        ...$params,
    ]));
}

beforeEach(function () {
    ['workspace' => $this->workspace, 'user' => $this->owner] = makeWorkspaceWithOwner();

    $shop = $this->shop = Shop::factory()->forWorkspace($this->workspace)->create(['name' => 'Alpha Shop']);

    // A day already rolled up.
    ParcelJourneyNotificationLog::create([
        'date' => '2026-07-05',
        'shop_id' => $shop->id,
        'tracked_orders' => 10,
        'sms_sent' => 6,
        'chat_sent' => 4,
    ]);

    // A day outside the window — none of the cards should see it.
    ParcelJourneyNotificationLog::create([
        'date' => '2026-08-05',
        'shop_id' => $shop->id,
        'tracked_orders' => 99,
        'sms_sent' => 99,
        'chat_sent' => 99,
    ]);

    // Live rows for days not yet rolled up: two notifications on one order, so
    // tracked orders counts the order once but both messages count.
    $order = Order::factory()->forWorkspace($this->workspace)->create(['shop_id' => $shop->id]);
    pjNotification($order, 'sms', 'sent', '2026-07-20 09:00:00');
    pjNotification($order, 'chat', 'delivered', '2026-07-20 10:00:00');

    // Queued and failed messages never left, so they don't count as sent.
    $other = Order::factory()->forWorkspace($this->workspace)->create(['shop_id' => $shop->id]);
    pjNotification($other, 'sms', 'pending', '2026-07-21 09:00:00');
    pjNotification($other, 'chat', 'failed', '2026-07-21 09:30:00');
});

test('tracked orders adds the rollup to the orders notified since', function () {
    // 10 rolled up + 2 distinct orders with live notifications.
    pjKpi('tracked-orders', $this->workspace, $this->owner)
        ->assertOk()
        ->assertJson(['value' => 12]);
});

test('sms and chat count only messages that actually left', function () {
    pjKpi('sms-sent', $this->workspace, $this->owner)->assertJson(['value' => 7]);
    pjKpi('chat-sent', $this->workspace, $this->owner)->assertJson(['value' => 5]);
});

test('total sent is the two channels together', function () {
    pjKpi('total-sent', $this->workspace, $this->owner)->assertJson(['value' => 12]);
});

test('the date range narrows every card', function () {
    // A window covering only the rolled-up day.
    $params = ['start_date' => '2026-07-01', 'end_date' => '2026-07-10'];

    pjKpi('tracked-orders', $this->workspace, $this->owner, $params)->assertJson(['value' => 10]);
    pjKpi('sms-sent', $this->workspace, $this->owner, $params)->assertJson(['value' => 6]);
    pjKpi('chat-sent', $this->workspace, $this->owner, $params)->assertJson(['value' => 4]);
    pjKpi('total-sent', $this->workspace, $this->owner, $params)->assertJson(['value' => 10]);
});

test('another workspace sees none of these figures', function () {
    ['workspace' => $otherWorkspace, 'user' => $otherOwner] = makeWorkspaceWithOwner();

    pjKpi('tracked-orders', $otherWorkspace, $otherOwner)->assertJson(['value' => 0]);
    pjKpi('total-sent', $otherWorkspace, $otherOwner)->assertJson(['value' => 0]);
});

test('a member without the view permission is refused', function () {
    $member = makeWorkspaceMember($this->workspace);

    pjKpi('tracked-orders', $this->workspace, $member)->assertForbidden();
});

test('the per-shop table breaks the same window down by shop', function () {
    $rows = pjShops($this->workspace, $this->owner)->assertOk()->json('data');

    expect($rows)->toHaveCount(1);
    expect($rows[0])->toMatchArray([
        'id' => $this->shop->id,
        'shop_name' => 'Alpha Shop',
        // 11, not the card's 12: the table counts tracked orders only from
        // messages that left, so the order whose two notifications are pending
        // and failed is missing here. Long-standing behaviour, kept as-is —
        // the two figures have always been able to disagree by an order whose
        // every message failed.
        'tracked_orders' => 11,
        'sms_sent' => 7,
        'chat_sent' => 5,
    ]);
    // The earliest activity in the window, rollup and live rows considered.
    expect($rows[0]['parcel_journey_started'])->toStartWith('2026-07-05');
});

test('shops with no journey in the window are left out', function () {
    Shop::factory()->forWorkspace($this->workspace)->create(['name' => 'Quiet Shop']);

    $rows = pjShops($this->workspace, $this->owner)->json('data');

    expect(collect($rows)->pluck('shop_name'))->not->toContain('Quiet Shop');
});

test('the table sorts and pages on its own request', function () {
    // A second active shop, so there is something to order.
    $second = Shop::factory()->forWorkspace($this->workspace)->create(['name' => 'Beta Shop']);
    ParcelJourneyNotificationLog::create([
        'date' => '2026-07-02',
        'shop_id' => $second->id,
        'tracked_orders' => 1,
        'sms_sent' => 1,
        'chat_sent' => 1,
    ]);

    $desc = pjShops($this->workspace, $this->owner, ['sort' => '-tracked_orders'])->json('data');
    expect(collect($desc)->pluck('shop_name')->all())->toBe(['Alpha Shop', 'Beta Shop']);

    $asc = pjShops($this->workspace, $this->owner, ['sort' => 'tracked_orders'])->json('data');
    expect(collect($asc)->pluck('shop_name')->all())->toBe(['Beta Shop', 'Alpha Shop']);

    $paged = pjShops($this->workspace, $this->owner, [
        'sort' => 'tracked_orders',
        'per_page' => 1,
        'page' => 2,
    ])->assertOk();

    expect($paged->json('data.0.shop_name'))->toBe('Alpha Shop');
    $paged->assertJson(['current_page' => 2, 'last_page' => 2, 'per_page' => 1, 'total' => 2]);
});

test('the per-shop table is gated by the same permission', function () {
    pjShops($this->workspace, makeWorkspaceMember($this->workspace))->assertForbidden();
});
