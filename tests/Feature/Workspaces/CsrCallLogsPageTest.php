<?php

use App\Enums\Permission;
use App\Models\CallLog;
use App\Models\Order;
use App\Models\Shop;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CallLogPersona;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The "My Calls" page — the signed-in CSR's own call register.
 *
 * The workspace register under RTS lists everyone and names a caller against
 * each row; this is the same table narrowed to one person, so what these tests
 * are mostly about is which rows are theirs. A call carries whichever id the
 * app that synced it knew about — a Pancake user id in `user_id` from the older
 * mobile build, the system user id in `assignee_user_id` from the newer one —
 * and both have to land here. See CSRController::callLogs().
 */
beforeEach(function () {
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();
    $this->workspace->update(['csr_dashboard_module_enabled' => true]);
    $this->shop = Shop::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Main Shop',
    ]);
});

/** A pancake account on the given shops, optionally linked to a system user. */
function callsCsr(string $name, ?User $systemUser = null, array $shopIds = []): PancakeUser
{
    $csr = PancakeUser::create(['name' => $name, 'user_id' => $systemUser?->id]);

    foreach ($shopIds as $shopId) {
        // The pivot carries a uuid primary key of its own, hence the insert.
        DB::table('pancake_shop_users')->insert([
            'id' => (string) Str::uuid(),
            'shop_id' => $shopId,
            'user_id' => $csr->id,
        ]);
    }

    return $csr;
}

/** A synced call, defaulted to something plausible so a test says only what it means. */
function loggedCall(Workspace $workspace, array $attributes = []): CallLog
{
    return CallLog::create([
        'workspace_id' => $workspace->id,
        // Neither id is defaulted to a real account: every test that cares
        // passes the one it is about, and a row belonging to nobody is the
        // control the scoping tests are measured against.
        'user_id' => (string) Str::uuid(),
        'phone_number' => '09170000001',
        'type' => 'outgoing',
        'duration' => 60,
        'call_date' => '2026-08-14',
        'call_time' => '09:30:00',
        ...$attributes,
    ]);
}

function visitMyCalls(User $user, Workspace $workspace, array $query = [])
{
    return test()->actingAs($user)->get(
        "/workspaces/{$workspace->slug}/csr/call-logs?".http_build_query($query)
    );
}

/** The page's props, asserted OK first so a failure names the status. */
function myCallsProps(User $user, Workspace $workspace, array $query = []): array
{
    $response = visitMyCalls($user, $workspace, $query);
    $response->assertOk();

    return $response->viewData('page')['props'];
}

/** @return array<int, array<string, mixed>> */
function myCallRows(User $user, Workspace $workspace, array $query = []): array
{
    return myCallsProps($user, $workspace, $query)['logs']['data'];
}

it('lists the calls placed under the signed-in user\'s pancake login', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => '09171111111']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => '09172222222']);

    expect(array_column(myCallRows($this->owner, $this->workspace), 'phone_number'))
        ->toEqualCanonicalizing(['09171111111', '09172222222']);
});

it('lists the calls the newer mobile build stamped with the system user id', function () {
    // No pancake login linked at all: the row's own user_id belongs to nobody,
    // which is how a V2 sync leaves it. assignee_user_id is the only link.
    loggedCall($this->workspace, [
        'assignee_user_id' => $this->owner->id,
        'phone_number' => '09173333333',
    ]);

    expect(array_column(myCallRows($this->owner, $this->workspace), 'phone_number'))
        ->toBe(['09173333333']);
});

it('leaves out another CSR\'s calls', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);
    $theirs = callsCsr('Ana Cruz', User::factory()->create(), [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => '09171111111']);
    loggedCall($this->workspace, ['user_id' => $theirs->id, 'phone_number' => '09179999999']);

    expect(array_column(myCallRows($this->owner, $this->workspace), 'phone_number'))
        ->toBe(['09171111111']);
});

it('leaves out the same user\'s calls in another workspace', function () {
    $otherWorkspace = Workspace::factory()->forOwner(User::factory()->create())->create();
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => '09171111111']);
    loggedCall($otherWorkspace, ['user_id' => $mine->id, 'phone_number' => '09179999999']);
    loggedCall($otherWorkspace, ['assignee_user_id' => $this->owner->id, 'phone_number' => '09178888888']);

    expect(array_column(myCallRows($this->owner, $this->workspace), 'phone_number'))
        ->toBe(['09171111111']);
});

it('leaves out a linked pancake login that works no shop of this workspace', function () {
    $elsewhere = Shop::factory()->create([
        'workspace_id' => Workspace::factory()->forOwner(User::factory()->create())->create()->id,
    ]);
    // Linked to the same person, but this workspace has never seen it — the
    // dashboard next door reads the roster the same way.
    $foreign = callsCsr('Zoe Elsewhere', $this->owner, [$elsewhere->id]);

    loggedCall($this->workspace, ['user_id' => $foreign->id, 'phone_number' => '09179999999']);

    expect(myCallRows($this->owner, $this->workspace))->toBe([]);
});

it('orders the newest call first, by day and then by time', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'first', 'call_date' => '2026-08-13', 'call_time' => '18:00:00']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'second', 'call_date' => '2026-08-14', 'call_time' => '08:00:00']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'third', 'call_date' => '2026-08-14', 'call_time' => '17:00:00']);

    expect(array_column(myCallRows($this->owner, $this->workspace), 'phone_number'))
        ->toBe(['third', 'second', 'first']);
});

it('sums calls, talk time and connected calls over the whole selection', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'duration' => 120]);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'duration' => 45, 'call_time' => '09:31:00']);
    // Under the connected threshold: the network logged it, nobody spoke.
    loggedCall($this->workspace, ['user_id' => $mine->id, 'duration' => 2, 'call_time' => '09:32:00']);

    // One row to a page, so a strip built from the page would read 1 / 120 / 1.
    $props = myCallsProps($this->owner, $this->workspace, ['per_page' => 1]);

    expect($props['logs']['data'])->toHaveCount(1);
    expect($props['totals'])->toBe(['calls' => 3, 'duration' => 167, 'connected' => 2]);
});

it('sums only what the filters left', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'duration' => 120, 'call_date' => '2026-08-14']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'duration' => 300, 'call_date' => '2026-08-20']);

    $props = myCallsProps($this->owner, $this->workspace, [
        'filter' => ['start_date' => '2026-08-14', 'end_date' => '2026-08-14'],
    ]);

    expect($props['totals'])->toBe(['calls' => 1, 'duration' => 120, 'connected' => 1]);
});

it('filters to one persona, and to the calls that matched nothing', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'customer', 'persona' => CallLogPersona::CUSTOMER]);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'rider', 'persona' => CallLogPersona::RIDER, 'call_time' => '09:31:00']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'unmatched', 'persona' => null, 'call_time' => '09:32:00']);

    $only = fn (string $persona) => array_column(
        myCallRows($this->owner, $this->workspace, ['filter' => ['persona' => $persona]]),
        'phone_number',
    );

    expect($only(CallLogPersona::CUSTOMER))->toBe(['customer']);
    expect($only('unmatched'))->toBe(['unmatched']);
});

it('filters by type and by date range', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'kept', 'type' => 'missed', 'call_date' => '2026-08-14']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'wrong-type', 'type' => 'outgoing', 'call_date' => '2026-08-14']);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => 'wrong-day', 'type' => 'missed', 'call_date' => '2026-08-20']);

    $rows = myCallRows($this->owner, $this->workspace, [
        'filter' => ['type' => 'missed', 'start_date' => '2026-08-13', 'end_date' => '2026-08-15'],
    ]);

    expect(array_column($rows, 'phone_number'))->toBe(['kept']);
});

it('searches by phone number and by whole order id', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);
    // An id of its own, sharing no run of digits with either number below: the
    // search is one clause over both, so a generated id would answer on the
    // phone `like` and prove nothing about the order half.
    $order = Order::factory()->forWorkspace($this->workspace)->create(['id' => 555444333]);

    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => '09170000022', 'order_id' => $order->id]);
    loggedCall($this->workspace, ['user_id' => $mine->id, 'phone_number' => '09179999911', 'call_time' => '09:31:00']);

    $search = fn ($value) => array_column(
        myCallRows($this->owner, $this->workspace, ['filter' => ['search' => $value]]),
        'phone_number',
    );

    expect($search('0022'))->toBe(['09170000022']);
    expect($search((string) $order->id))->toBe(['09170000022']);
});

it('shows no order for a call whose order has gone from the synced table', function () {
    $mine = callsCsr('Zoe Reyes', $this->owner, [$this->shop->id]);
    $order = Order::factory()->forWorkspace($this->workspace)->create();

    loggedCall($this->workspace, ['user_id' => $mine->id, 'order_id' => $order->id]);
    $order->delete();

    expect(myCallRows($this->owner, $this->workspace)[0]['order_id'])->toBeNull();
});

it('renders the call register page', function () {
    $response = visitMyCalls($this->owner, $this->workspace);

    $response->assertOk();
    expect($response->viewData('page')['component'])->toBe('workspaces/csr/call-logs');
    // "Unmatched" is on the list here, unlike the team-scoped RTS register:
    // nothing has removed the rows it selects.
    expect($response->viewData('page')['props']['personas'])
        ->toBe(['customer', 'rider', 'verification', 'unmatched']);
});

it('disappears with the CSR dashboard module', function () {
    $this->workspace->update(['csr_dashboard_module_enabled' => false]);

    visitMyCalls($this->owner, $this->workspace)->assertNotFound();
});

it('lets a member holding the dashboard permission through', function () {
    $member = makeMemberWithPermissions(
        $this->workspace,
        [Permission::ViewCsrDashboard->value],
        'CSR',
    );
    $theirs = callsCsr('Their Account', $member, [$this->shop->id]);

    loggedCall($this->workspace, ['user_id' => $theirs->id, 'phone_number' => '09171111111']);
    loggedCall($this->workspace, ['user_id' => callsCsr('Someone Else', $this->owner, [$this->shop->id])->id]);

    expect(array_column(myCallRows($member, $this->workspace), 'phone_number'))
        ->toBe(['09171111111']);
});

it('turns away a member without the dashboard permission', function () {
    visitMyCalls(makeWorkspaceMember($this->workspace), $this->workspace)->assertForbidden();
});

it('turns away a non-member', function () {
    visitMyCalls(User::factory()->create(), $this->workspace)->assertForbidden();
});
