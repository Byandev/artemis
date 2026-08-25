<?php

use App\Enums\Permission as PermissionEnum;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Modules\GencysERP\Models\GencysSyncRun;
use Modules\GencysERP\Support\BatchRunner;
use Modules\Inventory\Models\InventoryItem;

function syncRunsUrl($workspace, array $query = []): string
{
    $url = "/workspaces/{$workspace->slug}/gencys/sync-runs";

    return $query ? $url.'?'.http_build_query($query) : $url;
}

/** An ERP-connected workspace with active items, plus its owner. */
function makeErpWorkspaceForRuns(int $items = 2): array
{
    ['user' => $user, 'workspace' => $workspace] = makeWorkspaceWithOwner();

    $workspace->forceFill(['erp_username' => 'erp-user', 'erp_password' => 'erp-pass'])->save();
    makeApiKey($workspace);

    foreach (range(1, $items) as $n) {
        InventoryItem::create([
            'workspace_id' => $workspace->id,
            'sku' => "SKU-{$n}",
            'is_active' => true,
            'is_parent' => false,
        ]);
    }

    return ['user' => $user, 'workspace' => $workspace];
}

/** A workspace member whose role carries exactly $permissions. */
function gencysMemberWithPermissions($workspace, array $permissions): User
{
    $user = User::factory()->create();

    $role = Role::create(['workspace_id' => $workspace->id, 'name' => 'Role '.uniqid()]);

    foreach ($permissions as $name) {
        $permission = Permission::firstOrCreate(['name' => $name], ['category' => 'Gencys ERP']);
        DB::table('role_permissions')->insert([
            'role_id' => $role->id,
            'permission_id' => $permission->id,
        ]);
    }

    $workspace->users()->attach($user->id, ['role_id' => $role->id]);

    return $user;
}

beforeEach(function () {
    Http::fake(['*' => Http::response(['ok' => true], 200)]);
});

test('the page lists the batches that ran for this workspace, without loading their runs', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();

    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('workspaces/gencys/sync-runs/index')
            ->has('batches.data', 1)
            ->where('batches.data.0.run_total', 2)
            // Collapsed: the tallies are there, the runs themselves are not.
            ->where('batches.data.0.counts.pending', 2)
            ->where('batches.data.0.runs', null)
        );
});

test('expanding a batch loads that batch runs inline', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace, ['expanded' => [$batch->id]]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('batches.data.0.runs', 2)
            ->where('batches.data.0.runs.0.subject', 'SKU-1')
            ->where('batches.data.0.runs.0.status', GencysSyncRun::STATUS_PENDING)
            ->where('batches.data.0.runs_truncated', false)
        );
});

test('only the expanded batch has its runs loaded', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();

    $first = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/23/2026']]],
    );
    $second = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace, ['expanded' => [$second->id]]))
        ->assertOk()
        ->assertInertia(function ($page) use ($second) {
            $batches = collect($page->toArray()['props']['batches']['data']);

            $expanded = $batches->firstWhere('id', $second->id);
            $collapsed = $batches->firstWhere('id', '!=', $second->id);

            expect($expanded['runs'])->toHaveCount(2)
                ->and($collapsed['runs'])->toBeNull();
        });
});

test('the sync type filter narrows both the batches and their runs', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY, GencysSyncRun::TYPE_DAILY_SALES_TRACKER],
        [
            GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']],
            GencysSyncRun::TYPE_DAILY_SALES_TRACKER => ['dates' => ['08/24/2026']],
        ],
    );

    // Unfiltered: 2 transaction runs + 1 tracker run.
    $this->actingAs($user)
        ->get(syncRunsUrl($workspace, ['expanded' => [$batch->id]]))
        ->assertInertia(fn ($page) => $page->where('batches.data.0.run_total', 3));

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace, [
            'expanded' => [$batch->id],
            'sync_type' => GencysSyncRun::TYPE_DAILY_SALES_TRACKER,
        ]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('batches.data.0.run_total', 1)
            ->has('batches.data.0.runs', 1)
            ->where('batches.data.0.runs.0.sync_type', GencysSyncRun::TYPE_DAILY_SALES_TRACKER)
        );
});

test('the status filter hides batches with nothing matching', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();

    app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    // Everything is pending, so filtering to failed leaves nothing to show.
    $this->actingAs($user)
        ->get(syncRunsUrl($workspace, ['status' => GencysSyncRun::STATUS_FAILED]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('batches.data', 0));

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace, ['status' => GencysSyncRun::STATUS_PENDING]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('batches.data', 1));
});

test('a batch that did nothing for this workspace is not listed', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();
    ['workspace' => $other] = makeErpWorkspaceForRuns();

    // Pinned to the other workspace, so none of its runs belong here.
    app(BatchRunner::class)->queue(
        syncTypes: [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        parameters: [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
        workspaceId: $other->id,
    );

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('batches.data', 0));
});

test('a member needs View Gencys Sync for every sync page under Gencys', function () {
    ['workspace' => $workspace] = makeErpWorkspaceForRuns();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    // The pages moved out of Inventory, so inventory access no longer opens them.
    $without = gencysMemberWithPermissions($workspace, [
        PermissionEnum::ViewGencysPages->value,
        PermissionEnum::ViewInventoryItems->value,
    ]);
    $with = gencysMemberWithPermissions($workspace, [PermissionEnum::ViewGencysSync->value]);

    $base = "/workspaces/{$workspace->slug}/gencys";

    foreach ([
        "{$base}/sync-runs",
        "{$base}/sync-batches",
        "{$base}/sync-batches/{$batch->id}",
    ] as $url) {
        $this->actingAs($without)->get($url)->assertForbidden();
        $this->actingAs($with)->get($url)->assertOk();
    }
});

test('cancelling a batch is gated the same way', function () {
    ['workspace' => $workspace] = makeErpWorkspaceForRuns();

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    $without = gencysMemberWithPermissions($workspace, [PermissionEnum::ViewInventoryItems->value]);

    $this->actingAs($without)
        ->post("/workspaces/{$workspace->slug}/gencys/sync-batches/{$batch->id}/cancel")
        ->assertForbidden();

    expect($batch->fresh()->status)->not->toBe('cancelled');
});

test('someone outside the workspace cannot open it at all', function () {
    ['workspace' => $workspace] = makeErpWorkspaceForRuns();
    ['user' => $outsider] = makeWorkspaceWithOwner();

    $this->actingAs($outsider)->get(syncRunsUrl($workspace))->assertForbidden();
});

test('a batch progressing shows live tallies for this workspace', function () {
    ['user' => $user, 'workspace' => $workspace] = makeErpWorkspaceForRuns();
    ['raw' => $raw] = ['raw' => makeApiKey($workspace)['raw']];

    $batch = app(BatchRunner::class)->queue(
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY],
        [GencysSyncRun::TYPE_TRANSACTION_HISTORY => ['dates' => ['08/24/2026']]],
    );

    // Resolve the group that's in flight the way n8n would.
    $inFlight = $batch->runs()->pending()->get();

    $this->postJson('/api/v1/public/inventory-items/transactions/bulk-sync', [
        'items' => $inFlight->map(fn (GencysSyncRun $run) => [
            'id' => $run->inventory_item_id,
            'sync_run_id' => $run->id,
            'transactions' => [[
                'ref_no' => "TX-{$run->id}",
                'date' => '2026-08-24',
                'po_qty_in' => 1,
                'inventory_remaining_stock' => 1,
            ]],
        ])->values()->all(),
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    $this->actingAs($user)
        ->get(syncRunsUrl($workspace))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            // Both the scoped tallies the row prints...
            ->where('batches.data.0.counts.success', 2)
            ->where('batches.data.0.run_total', 2)
            // ...and the batch's own counters the bar reads from.
            ->where('batches.data.0.succeeded_runs', 2)
            ->where('batches.data.0.progress', 100)
        );
});
