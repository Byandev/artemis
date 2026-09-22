<?php

use App\Models\CallLog;
use App\Models\User;

/**
 * A rejected call is worth no talk time.
 *
 * The handset reports one anyway — Android hands back the seconds the phone
 * spent ringing — and stored as it comes, that time is summed into talk-time
 * totals and pushes the call past the three seconds RmoDailyStats counts as
 * connected. The sync zeroes it instead.
 */
test('the v1 sync stores a rejected call with no duration', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $user = User::factory()->create();

    $this->postJson('/api/v1/public/call-logs/sync', [
        'user_id' => $user->id,
        'call_logs' => [
            [
                'phone_number' => '09171234567',
                'type' => 'REJECTED',
                'duration' => 10,
                'timestamp' => '2026-09-21T09:00:00+08:00',
            ],
            [
                'phone_number' => '09179876543',
                'type' => 'OUTGOING',
                'duration' => 42,
                'timestamp' => '2026-09-21T09:05:00+08:00',
            ],
        ],
    ], ['Authorization' => 'Bearer '.$raw])->assertOk();

    expect(CallLog::where('phone_number', '09171234567')->value('duration'))->toBe(0)
        // Every other type is stored exactly as the handset reported it.
        ->and(CallLog::where('phone_number', '09179876543')->value('duration'))->toBe(42);
});

test('rejected is the only type the rule touches, whatever case it arrives in', function () {
    // The v2 sync applies the same rule through this helper. It is covered here
    // rather than over its endpoint because that endpoint cannot insert at all
    // under strict mode — it never supplies call_logs.user_id, which is NOT NULL.
    expect(CallLog::durationFor('REJECTED', 10))->toBe(0)
        ->and(CallLog::durationFor('rejected', 10))->toBe(0)
        ->and(CallLog::durationFor('Rejected', 10))->toBe(0)
        ->and(CallLog::durationFor('OUTGOING', 10))->toBe(10)
        ->and(CallLog::durationFor('INCOMING', 10))->toBe(10)
        ->and(CallLog::durationFor('MISSED', 10))->toBe(10);
});

test('re-syncing a rejected call does not restore the duration it was reported with', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();
    ['raw' => $raw] = makeApiKey($workspace);

    $user = User::factory()->create();

    $payload = [
        'user_id' => $user->id,
        'call_logs' => [[
            'phone_number' => '09171234567',
            'type' => 'REJECTED',
            'duration' => 10,
            'timestamp' => '2026-09-21T09:00:00+08:00',
        ]],
    ];

    $this->postJson('/api/v1/public/call-logs/sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();
    $this->postJson('/api/v1/public/call-logs/sync', $payload, ['Authorization' => 'Bearer '.$raw])->assertOk();

    // The upsert overwrites duration, so the second post is the one that would
    // put the ring time back if the rule only applied to inserts.
    expect(CallLog::where('phone_number', '09171234567')->count())->toBe(1)
        ->and(CallLog::where('phone_number', '09171234567')->value('duration'))->toBe(0);
});

test('the backfill zeroes the rejected calls that were synced before the rule', function () {
    ['workspace' => $workspace] = makeWorkspaceWithOwner();

    $rejected = CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => 'REJECTED',
        'duration' => 10,
    ]);

    $outgoing = CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'type' => 'OUTGOING',
        'duration' => 42,
    ]);

    (require base_path('database/migrations/2026_09_21_000000_zero_duration_on_rejected_call_logs.php'))->up();

    expect($rejected->fresh()->duration)->toBe(0)
        ->and($outgoing->fresh()->duration)->toBe(42);
});
