<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\WelleDailyRecord;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| Day by Day
|--------------------------------------------------------------------------
|
| The same days the calendar draws, read one column further out: which of the
| three pillars was ticked, not just how many. That is the one question a grid
| coloured by a count cannot answer.
|
| Every day of the month that has happened is listed — the 1st through today, or
| all of a month already past — whether or not Welle stored anything for it. A
| day with nothing done has no row in the table, so it comes back with its three
| pillars false rather than being left out and letting the log skip over it.
| Days still to come are not listed.
|
| The access rules are the page's, re-applied: an endpoint under /api can be
| reached without going through the page that shows it.
*/

beforeEach(function () {
    // The 16th: sixteen days lived this month, a full thirty-one last month.
    freezeWelleToday();

    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['welle_module_enabled' => true]);

    $this->url = route('api.workspaces.welle.stats.daily-log', [
        'workspace' => $this->workspace->slug,
    ]);

    $this->firstOfMonth = Carbon::today()->startOfMonth();
});

it('says which pillars a day carried, not just how many', function () {
    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => true, 'learning' => true]);
    welleDay($this->workspace->id, $this->owner->id, 3, false, ['meditation' => true]);

    // Sixteen rows for sixteen days lived, three of them with something on them.
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(16, 'days')
        ->assertJson([
            'total_days' => 16,
            'month' => $this->firstOfMonth->format('Y-m'),
            'month_label' => $this->firstOfMonth->format('F Y'),
            'connected' => false,
            'days' => [
                [
                    'date' => $this->firstOfMonth->toDateString(),
                    'movement' => true,
                    'meditation' => true,
                    'learning' => true,
                    'is_esc' => true,
                ],
                [
                    'date' => $this->firstOfMonth->copy()->addDay()->toDateString(),
                    'movement' => true,
                    'meditation' => false,
                    'learning' => true,
                    'is_esc' => false,
                ],
                [
                    'date' => $this->firstOfMonth->copy()->addDays(2)->toDateString(),
                    'movement' => false,
                    'meditation' => true,
                    'learning' => false,
                    'is_esc' => false,
                ],
            ],
        ]);
});

it('tells the three pillars apart, column for column', function () {
    // One day per pillar, so no two columns can be reading the same field.
    welleDay($this->workspace->id, $this->owner->id, 1, false, ['movement' => true]);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['meditation' => true]);
    welleDay($this->workspace->id, $this->owner->id, 3, false, ['learning' => true]);

    $days = array_slice(
        $this->actingAs($this->owner)->getJson($this->url)->assertOk()->json('days'),
        0,
        3,
    );

    expect(array_column($days, 'movement'))->toBe([true, false, false])
        ->and(array_column($days, 'meditation'))->toBe([false, true, false])
        ->and(array_column($days, 'learning'))->toBe([false, false, true]);
});

it('sends the days in date order, whatever order they were written in', function () {
    foreach ([7, 1, 4] as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, true);
    }

    $days = $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->json('days');

    $expected = collect(range(0, 15))
        ->map(fn (int $offset) => $this->firstOfMonth->copy()->addDays($offset)->toDateString())
        ->all();

    expect(array_column($days, 'date'))->toBe($expected)
        // The three that were written land on the days they were written for,
        // in spite of the order they arrived in.
        ->and(array_column($days, 'is_esc'))->toBe([
            true, false, false, true, false, false, true,
            false, false, false, false, false, false, false, false, false,
        ]);
});

it('lists every day of the month up to today, whether or not anything was done', function () {
    // One day in the middle of the month, and nothing else — the fifteen days
    // around it happened too and belong in the log as the blanks they were.
    welleDay($this->workspace->id, $this->owner->id, 9, true);

    $days = $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(16, 'days')
        ->json('days');

    expect($days[0])->toBe([
        'date' => $this->firstOfMonth->toDateString(),
        'movement' => false,
        'meditation' => false,
        'learning' => false,
        'is_esc' => false,
    ])
        // The last row is today's, never a day still to come.
        ->and($days[15]['date'])->toBe(Carbon::today()->toDateString())
        ->and(array_column($days, 'is_esc'))->toContain(true);
});

it('reads only the signed-in user rows, in this workspace, in this month', function () {
    $other = makeWorkspaceMember($this->workspace);
    ['workspace' => $otherWorkspace] = makeWorkspaceWithOwner();

    welleDay($this->workspace->id, $this->owner->id, 1, true);

    // Someone else's day, the same day in another workspace, and a day of the
    // month before — none of them belong in this table.
    welleDay($this->workspace->id, $other->id, 2, true);
    welleDay($otherWorkspace->id, $this->owner->id, 2, true);

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $this->firstOfMonth->copy()->subDay()->toDateString(),
        'movement' => true,
        'meditation' => true,
        'learning' => true,
        'pillars_completed' => 3,
        'is_esc' => true,
    ]);

    // Sixteen rows either way, but only the owner's own day is ticked: the
    // second is there as a blank rather than carrying someone else's record.
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(16, 'days')
        ->assertJson([
            'total_days' => 16,
            'days' => [
                ['date' => $this->firstOfMonth->toDateString(), 'is_esc' => true],
                [
                    'date' => $this->firstOfMonth->copy()->addDay()->toDateString(),
                    'movement' => false,
                    'meditation' => false,
                    'learning' => false,
                    'is_esc' => false,
                ],
            ],
        ]);
});

it('reads the month asked for', function () {
    $lastMonth = $this->firstOfMonth->copy()->subMonth();

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $lastMonth->toDateString(),
        'meditation' => true,
        'pillars_completed' => 1,
    ]);

    // August, and all thirty-one of its days — a month already past is listed
    // to its end rather than to today.
    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJsonCount(31, 'days')
        ->assertJson([
            'month' => $lastMonth->format('Y-m'),
            'total_days' => 31,
            'days' => [[
                'date' => $lastMonth->toDateString(),
                'meditation' => true,
                'movement' => false,
                'is_esc' => false,
            ]],
        ])
        ->assertJsonPath(
            'days.30.date',
            $lastMonth->copy()->endOfMonth()->toDateString(),
        );
});

it('lists the month as blank days while nothing is synced', function () {
    // No rows, but the month still happened — the table's advice comes from
    // `connected` and `synced`, not from an empty day count.
    $days = $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(16, 'days')
        ->assertJson(['total_days' => 16, 'connected' => false, 'synced' => false])
        ->json('days');

    expect(array_column($days, 'is_esc'))->each->toBeFalse();
});

it('has no days at all for a month that has not started', function () {
    $nextMonth = $this->firstOfMonth->copy()->addMonth();

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$nextMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['total_days' => 0, 'days' => []]);
});

it('says whether a welle account is connected at all', function () {
    connectWelleAccount($this->owner);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['connected' => true]);
});

it('answers a member holding the grant', function () {
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    welleDay($this->workspace->id, $member->id, 1, true);

    $this->actingAs($member)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(16, 'days')
        ->assertJson([
            'total_days' => 16,
            'days' => [['date' => $this->firstOfMonth->toDateString(), 'is_esc' => true]],
        ]);
});

it('is gone while the welle module is switched off', function () {
    $this->workspace->update(['welle_module_enabled' => false]);

    $this->actingAs($this->owner)->getJson($this->url)->assertNotFound();
});

it('forbids a member without the grant', function () {
    $member = makeWorkspaceMember($this->workspace);

    $this->actingAs($member)->getJson($this->url)->assertForbidden();
});

it('forbids someone who is not in the workspace', function () {
    $this->actingAs(User::factory()->create())->getJson($this->url)->assertForbidden();
});

it('turns a guest away', function () {
    $this->getJson($this->url)->assertUnauthorized();
});
