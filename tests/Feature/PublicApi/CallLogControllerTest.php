<?php

use App\Models\CallLog;
use Carbon\Carbon;

test('sync upserts call logs scoped to the workspace', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $userId = fake()->unique()->numberBetween(1, 999999);

    $payload = [
        'user_id' => $userId,
        'call_logs' => [
            [
                'phone_number' => '+639170000001',
                'type' => 'outgoing',
                'duration' => 45,
                'timestamp' => '2026-04-30T10:15:00+00:00',
            ],
            [
                'phone_number' => '+639170000002',
                'type' => 'incoming',
                'duration' => 12,
                'timestamp' => '2026-04-30T11:00:00+00:00',
            ],
        ],
    ];

    $this->postJson('/api/v1/public/call-logs/sync', $payload, [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertOk()
        ->assertJson(['total' => 2]);

    expect(CallLog::where('workspace_id', $workspace->id)->count())->toBe(2);
});

test('sync validates payload', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->postJson('/api/v1/public/call-logs/sync', [], [
        'Authorization' => 'Bearer '.$raw,
    ])->assertStatus(422);
});

test('list returns only the requested user logs in the workspace', function () {
    ['workspace' => $workspaceA] = makeWorkspaceWithOwner();
    ['workspace' => $workspaceB] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspaceA);

    $userId = fake()->unique()->numberBetween(1, 999999);
    $otherUser = fake()->unique()->numberBetween(1, 999999);

    CallLog::factory()->create(['workspace_id' => $workspaceA->id, 'user_id' => $userId, 'phone_number' => '+1']);
    CallLog::factory()->create(['workspace_id' => $workspaceA->id, 'user_id' => $userId, 'phone_number' => '+2']);
    CallLog::factory()->create(['workspace_id' => $workspaceA->id, 'user_id' => $otherUser, 'phone_number' => '+3']);
    CallLog::factory()->create(['workspace_id' => $workspaceB->id, 'user_id' => $userId, 'phone_number' => '+4']);

    $response = $this->getJson("/api/v1/public/call-logs/list?user_id={$userId}", [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($response->json('total'))->toBe(2);
});

test('summary aggregates per phone number', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $userId = fake()->unique()->numberBetween(1, 999999);

    CallLog::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $userId, 'phone_number' => '+1', 'duration' => 30]);
    CallLog::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $userId, 'phone_number' => '+1', 'duration' => 70]);
    CallLog::factory()->create(['workspace_id' => $workspace->id, 'user_id' => $userId, 'phone_number' => '+2', 'duration' => 15]);

    $response = $this->getJson("/api/v1/public/call-logs/summary?user_id={$userId}", [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($response->json('total_calls'))->toBe(3)
        ->and($response->json('total_duration'))->toBe(115);
});

test('rejects unauthenticated request', function () {
    $this->getJson('/api/v1/public/call-logs/list')->assertStatus(401);
});

test('sync rejects payload with malformed call_logs entries', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => 'u-1',
        'call_logs' => [['phone_number' => '+1', 'type' => 'x', 'duration' => -5, 'timestamp' => 'not-a-date']],
    ], ['Authorization' => 'Bearer '.$raw])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['call_logs.0.duration', 'call_logs.0.timestamp']);
});

test('sync rejects empty call_logs array', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => 'u-1',
        'call_logs' => [],
    ], ['Authorization' => 'Bearer '.$raw])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['call_logs']);
});

test('sync upserts on duplicate phone+date+time keys (idempotency)', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $userId = fake()->unique()->numberBetween(1, 999999);

    $payload = [
        'user_id' => $userId,
        'call_logs' => [
            ['phone_number' => '+1', 'type' => 'outgoing', 'duration' => 30, 'timestamp' => '2026-04-30T10:15:00+00:00'],
        ],
    ];

    $this->postJson('/api/v1/public/call-logs/sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();
    // Same payload again — should not duplicate
    $this->postJson('/api/v1/public/call-logs/sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect(CallLog::where('workspace_id', $workspace->id)->count())->toBe(1);
});

test('list filters by since timestamp (epoch ms)', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $userId = fake()->unique()->numberBetween(1, 999999);

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $userId,
        'call_date' => '2026-04-01',
        'call_time' => '10:00:00',
    ]);
    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $userId,
        'call_date' => '2026-04-30',
        'call_time' => '12:00:00',
    ]);

    $sinceMs = (int) (Carbon::parse('2026-04-15')->timestamp * 1000);

    $response = $this->getJson("/api/v1/public/call-logs/list?user_id={$userId}&since={$sinceMs}", [
        'Authorization' => 'Bearer '.$raw,
    ])->assertOk();

    expect($response->json('total'))->toBe(1);
});

test('list validates user_id must be an integer', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/call-logs/list?user_id=not-an-integer', [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('summary validates user_id must be an integer', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/call-logs/summary?user_id=invalid', [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('kpi validates user_id must be an integer', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $this->getJson('/api/v1/public/call-logs/kpi?user_id=invalid', [
        'Authorization' => 'Bearer '.$raw,
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['user_id']);
});

test('sync skips calls with no phone number and syncs the rest', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);
    $userId = fake()->uuid();

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => $userId,
        'call_logs' => [
            // Number withheld by the handset.
            ['phone_number' => null, 'type' => 'incoming', 'duration' => 20, 'timestamp' => '2026-04-30T10:15:00+00:00'],
            // Key missing from the entry altogether.
            ['type' => 'incoming', 'duration' => 25, 'timestamp' => '2026-04-30T11:15:00+00:00'],
            ['phone_number' => '+639170000001', 'type' => 'outgoing', 'duration' => 45, 'timestamp' => '2026-04-30T12:15:00+00:00'],
        ],
    ], ['Authorization' => 'Bearer '.$raw])
        ->assertOk()
        ->assertJson(['total' => 1]);

    expect(CallLog::where('workspace_id', $workspace->id)->pluck('phone_number')->all())
        ->toBe(['+639170000001']);
});
