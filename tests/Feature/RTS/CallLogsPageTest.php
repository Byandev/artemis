<?php

use App\Enums\Permission;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\Shop;
use App\Models\Team;
use App\Support\CallLogPersona;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The workspace-wide Call Logs page.
 *
 * The register behind the sidebar's RTS > Call Logs item: the same rows the RMO
 * modal shows one number at a time, listed whole and filterable.
 */
function logCall($workspace, array $attributes = []): CallLog
{
    return CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'call_date' => '2026-07-20',
        ...$attributes,
    ]);
}

/**
 * An order on a shop assigned to the given teams.
 *
 * A call reaches a team only through its order, and an order through its shop
 * (team_shop) — the path Modules\Pancake\Models\Order scopes on. Pass no teams
 * for an order no team owns.
 *
 * @param  array<int, Team>  $teams
 */
function orderForTeams($workspace, array $teams = []): Order
{
    $shop = Shop::factory()->forWorkspace($workspace)->create();

    if ($teams) {
        $shop->teams()->attach(collect($teams)->pluck('id')->all());
    }

    return Order::factory()->forWorkspace($workspace)->create(['shop_id' => $shop->id]);
}

/** A member holding View Call Logs but not View All Workspace Data, in the given teams. */
function callLogViewer($workspace, array $teams = [])
{
    $user = makeMemberWithPermissions($workspace, [Permission::ViewCallLogs->value], 'RTS');

    foreach ($teams as $team) {
        $user->teams()->attach($team);
    }

    return $user;
}

it('lists the workspace calls with the caller, persona and order on each row', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $caller = PancakeUser::create(['name' => 'Jose Cruz']);
    $order = Order::factory()->forWorkspace($workspace)->create(['order_number' => 'ORD-42']);

    logCall($workspace, [
        'user_id' => $caller->id,
        'persona' => CallLogPersona::CUSTOMER,
        'order_id' => $order->id,
        'type' => 'outgoing',
        'duration' => 72,
    ]);

    $row = $this->actingAs($owner)->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'][0];

    expect($row)->toMatchArray([
        'called_by' => 'Jose Cruz',
        'persona' => 'customer',
        'type' => 'outgoing',
        'duration' => 72,
        // The order is carried as pancake_orders.id: that is what the Order
        // column shows and what its link filters the Orders list by.
        'order_id' => $order->id,
    ]);
});

it('leaves another workspace calls out', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();
    ['workspace' => $other] = makeWorkspaceWithOwner();

    logCall($workspace, ['phone_number' => '09170000001']);
    logCall($other, ['phone_number' => '09170000002']);

    $rows = $this->actingAs($owner)->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['phone_number'])->toBe('09170000001');
});

it('narrows to one persona, and to the calls that matched nothing', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    logCall($workspace, ['phone_number' => '09170000001', 'persona' => CallLogPersona::CUSTOMER]);
    logCall($workspace, ['phone_number' => '09170000002', 'persona' => CallLogPersona::RIDER]);
    logCall($workspace, ['phone_number' => '09170000003', 'persona' => null]);

    $filtered = fn (string $persona) => $this->actingAs($owner)
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace, 'filter' => ['persona' => $persona]]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect(collect($filtered('rider'))->pluck('phone_number')->all())->toBe(['09170000002'])
        // "Unmatched" is a filterable answer, not the absence of one.
        ->and(collect($filtered('unmatched'))->pluck('phone_number')->all())->toBe(['09170000003']);
});

it('searches on the phone number and on the id of the order behind the call', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // A fixed id, well clear of the digits in either phone number, so the two
    // halves of the search can be told apart.
    $order = Order::factory()->forWorkspace($workspace)->create([
        'id' => 987654,
        'order_number' => 'ORD-99',
    ]);

    logCall($workspace, ['phone_number' => '09170000001', 'order_id' => $order->id]);
    logCall($workspace, ['phone_number' => '09180000002']);

    $search = fn (string $term) => collect(
        $this->actingAs($owner)
            ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace, 'filter' => ['search' => $term]]))
            ->assertOk()
            ->viewData('page')['props']['logs']['data']
    )->pluck('phone_number')->all();

    expect($search('987654'))->toBe(['09170000001'])
        ->and($search('0918'))->toBe(['09180000002'])
        // The order number is not what the page shows any more, so it is not
        // what the box searches on either.
        ->and($search('ORD-99'))->toBe([]);
});

it('keeps the day the calls were placed on', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    logCall($workspace, ['phone_number' => '09170000001', 'call_date' => '2026-07-19']);
    logCall($workspace, ['phone_number' => '09170000002', 'call_date' => '2026-07-21']);

    $rows = $this->actingAs($owner)
        ->get(route('workspaces.rts.call-logs', [
            'workspace' => $workspace,
            'filter' => ['start_date' => '2026-07-20', 'end_date' => '2026-07-22'],
        ]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect(collect($rows)->pluck('phone_number')->all())->toBe(['09170000002']);
});

it('is behind the View Call Logs permission', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $this->actingAs(makeWorkspaceMember($workspace))
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertForbidden();

    $this->actingAs(makeMemberWithPermissions($workspace, [Permission::ViewCallLogs->value], 'RTS'))
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk();
});

it('leads with the most recent call, day then time', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    // call_time is a time, not a timestamp, so the day has to lead the sort —
    // ordering on time alone would float the late call from the earlier day.
    logCall($workspace, ['phone_number' => '09170000001', 'call_date' => '2026-07-19', 'call_time' => '23:00:00']);
    logCall($workspace, ['phone_number' => '09170000002', 'call_date' => '2026-07-20', 'call_time' => '09:00:00']);
    logCall($workspace, ['phone_number' => '09170000003', 'call_date' => '2026-07-20', 'call_time' => '15:00:00']);

    $rows = $this->actingAs($owner)
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect(collect($rows)->pluck('phone_number')->all())
        ->toBe(['09170000003', '09170000002', '09170000001']);
});

it('limits a scoped viewer to the calls on their own teams orders', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $mine = Team::factory()->create(['workspace_id' => $workspace->id]);
    $theirs = Team::factory()->create(['workspace_id' => $workspace->id]);

    logCall($workspace, ['phone_number' => '09170000001', 'persona' => CallLogPersona::CUSTOMER, 'order_id' => orderForTeams($workspace, [$mine])->id]);
    logCall($workspace, ['phone_number' => '09170000002', 'persona' => CallLogPersona::CUSTOMER, 'order_id' => orderForTeams($workspace, [$theirs])->id]);

    $rows = $this->actingAs(callLogViewer($workspace, [$mine]))
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect(collect($rows)->pluck('phone_number')->all())->toBe(['09170000001']);
});

it('hides the unmatched calls from a scoped viewer, along with the filter for them', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    logCall($workspace, ['phone_number' => '09170000001', 'persona' => CallLogPersona::CUSTOMER, 'order_id' => orderForTeams($workspace, [$team])->id]);
    // No order, so no team: nothing says whose call this was.
    logCall($workspace, ['phone_number' => '09170000002', 'persona' => null, 'order_id' => null]);

    $props = $this->actingAs(callLogViewer($workspace, [$team]))
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['logs']['data'])->pluck('phone_number')->all())->toBe(['09170000001'])
        // The filter goes too — it could only ever come back empty.
        ->and($props['personas'])->not->toContain('unmatched');
});

it('leaves a scoped viewer in no team with nothing', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    logCall($workspace, ['phone_number' => '09170000001', 'order_id' => orderForTeams($workspace, [$team])->id]);
    logCall($workspace, ['phone_number' => '09170000002', 'order_id' => null]);

    $rows = $this->actingAs(callLogViewer($workspace))
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect($rows)->toBeEmpty();
});

it('shows the whole register, unmatched calls included, to an unrestricted viewer', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $team = Team::factory()->create(['workspace_id' => $workspace->id]);

    logCall($workspace, ['phone_number' => '09170000001', 'order_id' => orderForTeams($workspace, [$team])->id]);
    logCall($workspace, ['phone_number' => '09170000002', 'order_id' => null]);

    $props = $this->actingAs($owner)
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace]))
        ->assertOk()
        ->viewData('page')['props'];

    expect(collect($props['logs']['data'])->pluck('phone_number')->all())
        ->toEqualCanonicalizing(['09170000001', '09170000002'])
        ->and($props['personas'])->toContain('unmatched');
});

it('narrows an unrestricted viewer to the team they are viewing as', function () {
    ['user' => $owner, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $teamA = Team::factory()->create(['workspace_id' => $workspace->id]);
    $teamB = Team::factory()->create(['workspace_id' => $workspace->id]);

    logCall($workspace, ['phone_number' => '09170000001', 'order_id' => orderForTeams($workspace, [$teamA])->id]);
    logCall($workspace, ['phone_number' => '09170000002', 'order_id' => orderForTeams($workspace, [$teamB])->id]);
    logCall($workspace, ['phone_number' => '09170000003', 'order_id' => null]);

    // "Viewing as team" is a view filter, so it narrows the owner too.
    $rows = $this->actingAs($owner)
        ->get(route('workspaces.rts.call-logs', ['workspace' => $workspace, 'team_id' => $teamA->id]))
        ->assertOk()
        ->viewData('page')['props']['logs']['data'];

    expect(collect($rows)->pluck('phone_number')->all())->toBe(['09170000001']);
});
