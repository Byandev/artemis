<?php

use App\Models\CallLog;
use App\Models\User;
use Illuminate\Support\Str;
use Modules\Pancake\Models\User as PancakeUser;

/**
 * The "By" column of the RMO call-log modal.
 *
 * The list is fetched by phone number and date alone, so everyone who rang that
 * number that day lands in it — which only reads correctly if each row names its
 * own caller rather than whoever happens to be looking at the page.
 *
 * `user_id` (the Pancake user) is on every row; `assignee_user_id` (the system
 * user) is only there when the newer mobile build sent it.
 */
function callLogModalContext(): array
{
    $ctx = makeWorkspaceWithOwner();

    return [$ctx['user'], $ctx['workspace']];
}

/** The modal's fetch, as the page makes it. */
function fetchCallLogs(User $viewer, $workspace, string $phone = '09170000001', string $date = '2026-07-20')
{
    // Asserted here so a route that stops resolving can't read as "no name
    // found" — a 404 and an unresolved caller both come back as null.
    return test()->actingAs($viewer)->getJson(route('workspaces.csr.rmo-management.callLogs', [
        'workspace' => $workspace,
        'phone_number' => $phone,
        'date' => $date,
    ]))->assertOk();
}

it('names the caller from the pancake user id every log carries', function () {
    [$viewer, $workspace] = callLogModalContext();

    $caller = PancakeUser::create(['name' => 'Jose Cruz']);

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $caller->id,
        'phone_number' => '09170000001',
        'call_date' => '2026-07-20',
    ]);

    $response = fetchCallLogs($viewer, $workspace);

    $response->assertOk();
    expect($response->json('0.called_by'))->toBe('Jose Cruz');
});

it('prefers the system user when the newer app sent one', function () {
    [$viewer, $workspace] = callLogModalContext();

    $pancake = PancakeUser::create(['name' => 'Stale Pancake Name']);
    $system = User::factory()->create(['name' => 'Maria Santos']);

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $pancake->id,
        'assignee_user_id' => $system->id,
        'phone_number' => '09170000001',
        'call_date' => '2026-07-20',
    ]);

    expect(fetchCallLogs($viewer, $workspace)->json('0.called_by'))->toBe('Maria Santos');
});

it('gives each call its own caller rather than one name for the whole list', function () {
    [$viewer, $workspace] = callLogModalContext();

    $morning = PancakeUser::create(['name' => 'Jose Cruz']);
    $afternoon = PancakeUser::create(['name' => 'Ana Reyes']);

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $morning->id,
        'phone_number' => '09170000001',
        'call_date' => '2026-07-20',
        'call_time' => '09:00:00',
    ]);

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => $afternoon->id,
        'phone_number' => '09170000001',
        'call_date' => '2026-07-20',
        'call_time' => '15:00:00',
    ]);

    // Newest first, so the afternoon call leads. Before this, both rows read as
    // whoever was signed in on the device looking at them.
    expect(fetchCallLogs($viewer, $workspace)->json('*.called_by'))
        ->toBe(['Ana Reyes', 'Jose Cruz']);
});

it('leaves the caller blank rather than guessing when the id resolves to nobody', function () {
    [$viewer, $workspace] = callLogModalContext();

    CallLog::factory()->create([
        'workspace_id' => $workspace->id,
        'user_id' => (string) Str::uuid(),
        'phone_number' => '09170000001',
        'call_date' => '2026-07-20',
    ]);

    expect(fetchCallLogs($viewer, $workspace)->json('0.called_by'))->toBeNull();
});
