<?php

use App\Models\Permission;
use App\Models\Product;
use App\Models\Role;
use App\Models\Shop;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Inventory\Models\InventoryItem;
use Modules\Inventory\Models\PurchasedOrder;
use Modules\Inventory\Models\PurchasedOrderItem;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

/** GET one of the PO-flow panels. */
function flow($user, $workspace, string $panel): array
{
    return test()->actingAs($user)
        ->getJson("/api/workspaces/{$workspace->slug}/inventory/dashboard/po-flow/{$panel}")
        ->assertOk()
        ->json();
}

/** An item reachable from the given teams, via product → shop → teams. */
function flowItem($workspace, string $sku, array $teams = [], float $dailyAverage = 10): InventoryItem
{
    $product = Product::factory()->create(['workspace_id' => $workspace->id]);
    $shop = Shop::factory()->forWorkspace($workspace)->create(['product_id' => $product->id]);

    if ($teams) {
        $shop->teams()->attach(collect($teams)->pluck('id')->all());
    }

    return InventoryItem::create([
        'workspace_id' => $workspace->id,
        'product_id' => $product->id,
        'sku' => $sku,
        'is_active' => true,
        'three_days_average' => $dailyAverage,
        'lead_time' => 10,
        'days_of_coverage' => 10,
    ]);
}

/** A purchase order at the given stage, owing $count units of $item. */
function flowOrder($workspace, InventoryItem $item, int $status, string $issuedAt, int $count, int $delivered = 0): PurchasedOrder
{
    $order = PurchasedOrder::create([
        'workspace_id' => $workspace->id,
        'control_no' => 'CN-'.uniqid(),
        'issue_date' => $issuedAt,
        'status' => $status,
    ]);

    $line = PurchasedOrderItem::create([
        'inventory_purchased_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'count' => $count,
    ]);

    if ($delivered > 0) {
        $line->deliveries()->create(['delivery_date' => $issuedAt, 'qty' => $delivered]);
    }

    return $order;
}

/** A member scoped to the given teams, holding the inventory read permissions. */
function flowMember($workspace, array $teams): User
{
    $user = User::factory()->create();
    $role = Role::factory()->create(['workspace_id' => $workspace->id]);

    // Read-only inventory access, and nothing that would make the user
    // unrestricted — the point of these tests is that scoping still bites.
    foreach (['View Inventory Items', 'View Purchased Orders'] as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'Inventory']);
        $role->permissions()->attach($permission->id);
    }

    $user->workspaces()->attach($workspace, ['role_id' => $role->id]);

    foreach ($teams as $team) {
        $user->teams()->attach($team);
    }

    return $user;
}

test('the pipeline splits open units by stage and by whether a supplier has them', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    flowOrder($workspace, $item, 1, now()->subDays(30)->toDateString(), 100);   // For Approval
    flowOrder($workspace, $item, 3, now()->subDays(2)->toDateString(), 200);    // To Pay
    flowOrder($workspace, $item, 6, now()->subDays(5)->toDateString(), 300);    // At supplier

    $data = flow($owner, $workspace, 'pipeline');

    expect($data['internal_units'])->toBe(300)   // 100 + 200
        ->and($data['supplier_units'])->toBe(300)
        ->and($data['total_units'])->toBe(600);

    // The 30-day-old For Approval order is past the 7-day target; the 2-day
    // To Pay order is not.
    $stages = collect($data['stages'])->keyBy('name');
    expect($stages['For Approval']['overdue_units'])->toBe(100)
        ->and($stages['To Pay']['overdue_units'])->toBe(0)
        // Supplier stages never count against the internal target.
        ->and($stages['Waiting For Delivery']['overdue_units'])->toBe(0);

    // Aging bands land where they should.
    expect($stages['For Approval']['buckets']['15-30d'])->toBe(100)
        ->and($stages['To Pay']['buckets']['0-7d'])->toBe(200);
});

test('fully delivered lines are not open to anyone', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    flowOrder($workspace, $item, 6, now()->subDays(3)->toDateString(), 100, delivered: 100);

    expect(flow($owner, $workspace, 'pipeline')['total_units'])->toBe(0);
});

test('the worklist ranks by time waiting and reports days of demand held up', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // 10 units a day, so 400 units is 40 days of demand.
    $slow = flowItem($workspace, 'SLOW', dailyAverage: 10);
    $fast = flowItem($workspace, 'FAST', dailyAverage: 500);

    flowOrder($workspace, $slow, 3, now()->subDays(60)->toDateString(), 400);
    flowOrder($workspace, $fast, 3, now()->subDays(2)->toDateString(), 5000);

    $lines = collect(flow($owner, $workspace, 'worklist')['lines']);

    // Longest wait first, regardless of size.
    expect($lines->pluck('item')->all())->toBe(['SLOW', 'FAST']);
    expect($lines->firstWhere('item', 'SLOW')['covers_days'])->toEqual(40.0)
        ->and($lines->firstWhere('item', 'SLOW')['overdue'])->toBeTrue()
        ->and($lines->firstWhere('item', 'FAST')['covers_days'])->toEqual(10.0)
        ->and($lines->firstWhere('item', 'FAST')['overdue'])->toBeFalse();
});

test('supplier deliveries separate the untouched from the merely stalled', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    flowOrder($workspace, $item, 6, now()->subDays(40)->toDateString(), 500);                    // nothing arrived
    flowOrder($workspace, $item, 6, now()->subDays(20)->toDateString(), 400, delivered: 100);    // stalled part-way
    flowOrder($workspace, $item, 6, now()->subDays(2)->toDateString(), 200);                     // too new to chase

    $data = flow($owner, $workspace, 'supplier-deliveries');

    expect($data['nothing_arrived']['orders'])->toBe(2)      // the 40-day and the 2-day
        ->and($data['nothing_arrived']['units'])->toBe(700)
        ->and($data['part_delivered']['orders'])->toBe(1)
        ->and($data['part_delivered']['units'])->toBe(300)
        // Past the 14-day delivery target: the 40-day and the 20-day.
        ->and($data['past_quote']['orders'])->toBe(2)
        ->and($data['past_quote']['units'])->toBe(800);

    $worst = collect($data['lines'])->first();
    expect($worst['age'])->toBe(40)
        ->and($worst['fill_pct'])->toBe(0)
        ->and($worst['last_delivery'])->toBeNull();
});

test('unfulfilled demand splits by whether the stock is actually on the shelf', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $covered = flowItem($workspace, 'COVERED', dailyAverage: 10);
    $bare = flowItem($workspace, 'BARE', dailyAverage: 10);

    $covered->update(['unfulfilled_count' => 100]);
    $bare->update(['unfulfilled_count' => 80]);

    // Stock only exists for the first item.
    $covered->transactions()->create([
        'workspace_id' => $workspace->id, 'date' => now()->toDateString(),
        'ref_no' => 'TX-1', 'remaining_qty' => 250,
    ]);

    $data = flow($owner, $workspace, 'unfulfilled-split');

    expect($data['here'])->toBe(100)     // all 100 shippable today
        ->and($data['gone'])->toBe(80)   // nothing behind the other 80
        ->and($data['total'])->toBe(180);

    // 100 units at 10/day is 10 days of demand — well past a picking queue.
    expect($data['sitting']['units'])->toBe(100)
        ->and($data['sitting']['skus'])->toBe(1)
        ->and($data['worst']['item'])->toBe('COVERED');
});

test('the bottleneck blames operations when stock never leaves the building', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    flowOrder($workspace, $item, 3, now()->subDays(40)->toDateString(), 5000);   // stuck in To Pay
    flowOrder($workspace, $item, 6, now()->subDays(1)->toDateString(), 100);     // supplier is fine

    $owners = collect(flow($owner, $workspace, 'bottleneck')['owners'])->keyBy('key');

    expect($owners['operations']['state'])->toBe('blocked')
        ->and($owners['operations']['value'])->toBe(5000)
        ->and($owners['supplier']['state'])->toBe('ok');
});

test('the bottleneck blames the supplier when the office is clear', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    // Released long ago, nothing delivered; nothing waiting internally.
    flowOrder($workspace, $item, 6, now()->subDays(60)->toDateString(), 5000);

    $owners = collect(flow($owner, $workspace, 'bottleneck')['owners'])->keyBy('key');

    expect($owners['supplier']['state'])->toBe('blocked')
        ->and($owners['operations']['state'])->toBe('ok');
});

test('every panel is scoped to the teams the user belongs to', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $mine = Team::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Team::factory()->create(['workspace_id' => $workspace->id]);

    $mineItem = flowItem($workspace, 'TEAM-MINE', [$mine], dailyAverage: 10);
    $theirItem = flowItem($workspace, 'TEAM-THEIRS', [$theirs], dailyAverage: 10);

    flowOrder($workspace, $mineItem, 3, now()->subDays(30)->toDateString(), 100);
    flowOrder($workspace, $theirItem, 3, now()->subDays(30)->toDateString(), 900);
    flowOrder($workspace, $mineItem, 6, now()->subDays(30)->toDateString(), 50);
    flowOrder($workspace, $theirItem, 6, now()->subDays(30)->toDateString(), 700);

    $mineItem->update(['unfulfilled_count' => 20]);
    $theirItem->update(['unfulfilled_count' => 600]);

    $user = flowMember($workspace, [$mine]);

    // Pipeline totals carry only this team's stock.
    $pipeline = flow($user, $workspace, 'pipeline');
    expect($pipeline['internal_units'])->toBe(100)
        ->and($pipeline['supplier_units'])->toBe(50);

    // Worklist and supplier panels list only this team's orders.
    expect(collect(flow($user, $workspace, 'worklist')['lines'])->pluck('item')->unique()->all())
        ->toBe(['TEAM-MINE']);
    expect(collect(flow($user, $workspace, 'supplier-deliveries')['lines'])->pluck('item')->unique()->all())
        ->toBe(['TEAM-MINE']);

    // The unfulfilled split never reveals the other team's demand.
    $split = flow($user, $workspace, 'unfulfilled-split');
    expect($split['total'])->toBe(20)
        ->and(collect($split['items'])->pluck('item')->all())->toBe(['TEAM-MINE']);

    // And the verdict is computed from the scoped figures.
    $owners = collect(flow($user, $workspace, 'bottleneck')['owners'])->keyBy('key');
    expect($owners['operations']['value'])->toBe(100);
});

test('a scoped user with no team sees nothing rather than everything', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $team = Team::factory()->create(['workspace_id' => $workspace->id]);
    $item = flowItem($workspace, 'SKU-1', [$team]);
    flowOrder($workspace, $item, 3, now()->subDays(9)->toDateString(), 500);

    $user = flowMember($workspace, []);

    expect(flow($user, $workspace, 'pipeline')['total_units'])->toBe(0)
        ->and(flow($user, $workspace, 'worklist')['lines'])->toBeEmpty()
        ->and(flow($user, $workspace, 'unfulfilled-split')['total'])->toBe(0);
});

test('a step that always completes instantly is not reported as a queue', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    $order = flowOrder($workspace, $item, 6, '2026-07-01', 100);
    // Approve took two days; the move to To Pay was the same click.
    $order->statusLogs()->create(['status' => 'Approve', 'logged_at' => '2026-07-03 09:00:00']);
    $order->statusLogs()->create(['status' => 'To Pay', 'logged_at' => '2026-07-03 09:00:04']);
    $order->statusLogs()->create(['status' => 'Paid', 'logged_at' => '2026-07-06 09:00:00']);

    $steps = collect(flow($owner, $workspace, 'stage-timings')['steps'])->pluck('label');

    expect($steps)->toContain('Raised → Approve')
        ->toContain('To Pay → Paid')
        ->not->toContain('Approve → To Pay');
});

test('fill levels are stamped by the delivery that crossed each one, from the issue date', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    // Issued 1 July. The clock runs door to door from here, not from release —
    // that is the number that decides whether stock arrives before you run out.
    $order = flowOrder($workspace, $item, 6, '2026-07-01', 100);

    $line = $order->items->first();
    $line->deliveries()->create(['delivery_date' => '2026-07-14', 'qty' => 40]);  // 40% on day 13
    $line->deliveries()->create(['delivery_date' => '2026-07-20', 'qty' => 55]);  // 95% on day 19
    $line->deliveries()->create(['delivery_date' => '2026-08-04', 'qty' => 5]);   // 100% on day 34

    $steps = collect(flow($owner, $workspace, 'stage-timings')['delivery_steps'])
        ->keyBy('label');

    // First delivery and 30% are both crossed by the day-13 delivery.
    expect($steps['Raised → first delivery']['p50'])->toEqual(13.0)
        ->and($steps['Raised → 30% delivered']['p50'])->toEqual(13.0)
        // 60% and 90% both land on the day-19 delivery.
        ->and($steps['Raised → 60% delivered']['p50'])->toEqual(19.0)
        ->and($steps['Raised → 90% delivered']['p50'])->toEqual(19.0)
        // The last 5% dribbles in a fortnight later — the shape a single
        // "delivered" figure would hide.
        ->and($steps['Raised → 100% delivered']['p50'])->toEqual(34.0);
});

test('a fill level nothing has reached is absent rather than reported as zero', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    $order = flowOrder($workspace, $item, 6, '2026-07-01', 100);
    $order->items->first()->deliveries()->create(['delivery_date' => '2026-07-05', 'qty' => 45]);

    $labels = collect(flow($owner, $workspace, 'stage-timings')['delivery_steps'])
        ->pluck('label');

    expect($labels)->toContain('Raised → 30% delivered')
        ->not->toContain('Raised → 60% delivered')
        ->not->toContain('Raised → 100% delivered');
});

test('the delivery curve covers orders with no status trail at all', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    $item = flowItem($workspace, 'SKU-1');

    // No status logs anywhere — the internal steps have nothing to measure, but
    // the delivery curve only needs an issue date and deliveries.
    $order = flowOrder($workspace, $item, 6, '2026-07-01', 100);
    $order->items->first()->deliveries()->create(['delivery_date' => '2026-07-08', 'qty' => 100]);

    $data = flow($owner, $workspace, 'stage-timings');

    expect($data['steps'])->toBeEmpty();
    expect(collect($data['delivery_steps'])->keyBy('label')['Raised → 100% delivered']['p50'])
        ->toEqual(7.0);
});
