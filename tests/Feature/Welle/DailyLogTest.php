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
| Days still to come have no row, so the table ends at the last day lived.
|
| The access rules are the page's, re-applied: an endpoint under /api can be
| reached without going through the page that shows it.
*/

beforeEach(function () {
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
    welleDay($this->workspace->id, $this->owner->id, 3, false);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'total_days' => 3,
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
                    'meditation' => false,
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

    $days = $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->json('days');

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

    expect(array_column($days, 'date'))->toBe([
        $this->firstOfMonth->toDateString(),
        $this->firstOfMonth->copy()->addDays(3)->toDateString(),
        $this->firstOfMonth->copy()->addDays(6)->toDateString(),
    ]);
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
        'pillars_completed' => 3,
        'is_esc' => true,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'days')
        ->assertJson([
            'total_days' => 1,
            'days' => [['date' => $this->firstOfMonth->toDateString()]],
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

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson([
            'month' => $lastMonth->format('Y-m'),
            'total_days' => 1,
            'days' => [[
                'date' => $lastMonth->toDateString(),
                'meditation' => true,
                'movement' => false,
                'is_esc' => false,
            ]],
        ]);
});

it('has an empty month rather than an error while nothing is synced', function () {
    $this->actingAs($this->owner)
        ->getJson($this->url)
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
        ->assertJson(['total_days' => 1]);
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
