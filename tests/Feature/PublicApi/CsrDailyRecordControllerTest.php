<?php

use App\Models\PancakeUserErpDailyReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedPancakeUser(): string
{
    $id = (string) Str::uuid();
    DB::table('pancake_users')->insert([
        'id' => $id,
        'name' => 'Pancake CSR',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $id;
}

test('store creates a new ERP daily report record on first call', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $csrId = seedPancakeUser();

    $this->postJson('/api/v1/public/csr-daily-records', [
        'csr_id' => $csrId,
        'date' => '2026-04-30',
        'total_orders' => 10,
        'total_sales' => 5000,
        'returning' => 1,
        'delivered' => 9,
        'rts_rate' => 10.0,
    ], [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertStatus(201)
        ->assertJsonPath('created', true);

    expect(PancakeUserErpDailyReport::where('workspace_id', $workspace->id)
        ->where('pancake_user_id', $csrId)
        ->where('date', '2026-04-30')
        ->count())->toBe(1);
});

test('store updates existing record (idempotent on workspace+csr+date)', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $csrId = seedPancakeUser();

    $payload = [
        'csr_id' => $csrId,
        'date' => '2026-04-30',
        'total_orders' => 10,
        'total_sales' => 5000,
        'returning' => 1,
        'delivered' => 9,
        'rts_rate' => 10.0,
    ];

    $this->postJson('/api/v1/public/csr-daily-records', $payload, [
        'Authorization' => 'Bearer '.$raw,
    ])->assertStatus(201);

    $payload['total_orders'] = 22;

    $this->postJson('/api/v1/public/csr-daily-records', $payload, [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertStatus(200)
        ->assertJsonPath('created', false);

    $record = PancakeUserErpDailyReport::where('workspace_id', $workspace->id)
        ->where('pancake_user_id', $csrId)
        ->where('date', '2026-04-30')
        ->first();

    expect((int) $record->total_orders)->toBe(22);
    expect(PancakeUserErpDailyReport::count())->toBe(1);
});

test('store fails validation when required fields are missing', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->postJson('/api/v1/public/csr-daily-records', [], [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['csr_id', 'date', 'total_orders', 'total_sales', 'returning', 'delivered', 'rts_rate']);
});

test('rejects unauthenticated request', function () {
    $this->postJson('/api/v1/public/csr-daily-records', [])->assertStatus(401);
});

test('store rejects negative numeric values', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $csrId = seedPancakeUser();

    $this->postJson('/api/v1/public/csr-daily-records', [
        'csr_id' => $csrId,
        'date' => '2026-04-30',
        'total_orders' => -1,
        'total_sales' => -500,
        'returning' => -1,
        'delivered' => -1,
        'rts_rate' => -1,
    ], ['Authorization' => 'Bearer '.$raw])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['total_orders', 'total_sales', 'returning', 'delivered', 'rts_rate']);
});

test('store fails when csr_id does not match a pancake_user', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->postJson('/api/v1/public/csr-daily-records', [
        'csr_id' => 'non-existent-uuid-1234',
        'date' => '2026-04-30',
        'total_orders' => 1,
        'total_sales' => 1,
        'returning' => 0,
        'delivered' => 1,
        'rts_rate' => 0,
    ], ['Authorization' => 'Bearer '.$raw])
        ->assertStatus(500); // FK violation propagates
});

test('a different workspace cannot read another workspace records via store/upsert', function () {
    $a = makeWorkspaceWithOwner();
    $b = makeWorkspaceWithOwner();

    $csrId = seedPancakeUser();
    ['raw' => $rawA] = makeApiKey($a['workspace']);
    ['raw' => $rawB] = makeApiKey($b['workspace']);

    $this->postJson('/api/v1/public/csr-daily-records', [
        'csr_id' => $csrId, 'date' => '2026-04-30',
        'total_orders' => 1, 'total_sales' => 100, 'returning' => 0, 'delivered' => 1, 'rts_rate' => 0,
    ], ['Authorization' => 'Bearer '.$rawA])->assertStatus(201);

    // B's API key creates its OWN row for the same csr+date. Two rows expected, one per workspace.
    $this->postJson('/api/v1/public/csr-daily-records', [
        'csr_id' => $csrId, 'date' => '2026-04-30',
        'total_orders' => 1, 'total_sales' => 200, 'returning' => 0, 'delivered' => 1, 'rts_rate' => 0,
    ], ['Authorization' => 'Bearer '.$rawB])->assertStatus(201);

    expect(\App\Models\PancakeUserErpDailyReport::where('workspace_id', $a['workspace']->id)->count())->toBe(1);
    expect(\App\Models\PancakeUserErpDailyReport::where('workspace_id', $b['workspace']->id)->count())->toBe(1);
});
