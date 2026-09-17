<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\WelleDailyRecord;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| ESC Calendar
|--------------------------------------------------------------------------
|
| The month day by day: one entry per day Welle has a record of, carrying how
| many of the three pillars were ticked on it — which is what the grid colours
| a day by.
|
| A day has a row only if something was done on it. Days still to come, days
| before the account was connected and days that went by unused all have none,
| and the grid draws them the same pale way — so the endpoint must not invent
| them, and `total_days` says how many of them were days that happened.
|
| The access rules are the page's, re-applied: an endpoint under /api can be
| reached without going through the page that shows it.
*/

beforeEach(function () {
    // The 16th: sixteen days lived this month, a full thirty-one last month.
    $this->today = freezeWelleToday();

    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['welle_module_enabled' => true]);

    $this->url = route('api.workspaces.welle.stats.calendar', [
        'workspace' => $this->workspace->slug,
    ]);

    // The clock is frozen above, so this is the same 1st on every run.
    $this->firstOfMonth = Carbon::today()->startOfMonth();
});

it('lists every recorded day with the pillars it carried', function () {
    // A full day, a two-pillar day and a one-pillar day — the three shades the
    // grid draws. There is no fourth: a day with none of them has no row.
    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => true, 'meditation' => true]);
    welleDay($this->workspace->id, $this->owner->id, 3, false, ['learning' => true]);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(3, 'days')
        ->assertJson([
            'total_days' => 16,
            'month' => $this->firstOfMonth->format('Y-m'),
            'month_label' => $this->firstOfMonth->format('F Y'),
            'connected' => false,
            'days' => [
                ['date' => $this->firstOfMonth->toDateString(), 'pillars_completed' => 3],
                ['date' => $this->firstOfMonth->copy()->addDay()->toDateString(), 'pillars_completed' => 2],
                ['date' => $this->firstOfMonth->copy()->addDays(2)->toDateString(), 'pillars_completed' => 1],
            ],
        ]);
});

it('sends the days in date order, whatever order they were written in', function () {
    foreach ([9, 2, 5] as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, true);
    }

    $days = $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->json('days');

    expect(array_column($days, 'date'))->toBe([
        $this->firstOfMonth->copy()->addDay()->toDateString(),
        $this->firstOfMonth->copy()->addDays(4)->toDateString(),
        $this->firstOfMonth->copy()->addDays(8)->toDateString(),
    ]);
});

it('leaves a day it has no record of out rather than sending it as empty', function () {
    // Two days with something on them out of sixteen lived: the rest are the
    // grid's business to draw pale, not this endpoint's to invent.
    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => true]);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(2, 'days')
        ->assertJson(['total_days' => 16]);
});

it('reads only the signed-in user rows, in this workspace, in this month', function () {
    $other = makeWorkspaceMember($this->workspace);
    ['workspace' => $otherWorkspace] = makeWorkspaceWithOwner();

    welleDay($this->workspace->id, $this->owner->id, 1, true);

    // Someone else's day, the same day in another workspace, and a day of the
    // month before — none of them belong on this grid.
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

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'days')
        ->assertJson([
            'total_days' => 16,
            'days' => [['date' => $this->firstOfMonth->toDateString(), 'pillars_completed' => 3]],
        ]);
});

it('draws the month asked for', function () {
    $lastMonth = $this->firstOfMonth->copy()->subMonth();

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $lastMonth->toDateString(),
        'movement' => true,
        'pillars_completed' => 1,
    ]);

    // August, and all thirty-one of its days.
    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson([
            'month' => $lastMonth->format('Y-m'),
            'total_days' => 31,
            'days' => [['date' => $lastMonth->toDateString(), 'pillars_completed' => 1]],
        ]);
});

it('has an empty month rather than an error while nothing is synced', function () {
    // No days on the grid, but the month still happened — the card's advice
    // comes from `connected` and `synced`, not from an empty day count.
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['total_days' => 16, 'days' => [], 'connected' => false, 'synced' => false]);
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
        ->assertJsonCount(1, 'days')
        ->assertJson(['total_days' => 16]);
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
