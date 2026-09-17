<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\WelleDailyRecord;
use App\Models\Workspace;

/*
|--------------------------------------------------------------------------
| My ESC stat cards
|--------------------------------------------------------------------------
|
| Every card is counted over the days of the month that have elapsed — the 1st
| through today, or the whole of a month already past — so "7 of 16" is seven
| days out of sixteen lived, not seven out of a whole month. The clock is
| pinned, or the denominator would move with the calendar.
|
| A day nothing was done on has no row at all, and still belongs in that
| denominator: it is a day that happened and went unused, and dropping it would
| turn a month of two good days into 100%.
|
| The access rules are the page's, re-applied: an endpoint under /api can be
| reached without going through the page that shows it.
*/

/** One pillar card's endpoint — a route of its own per pillar. */
function pillarStatUrl(Workspace $workspace, string $pillar): string
{
    return route("api.workspaces.welle.stats.{$pillar}", ['workspace' => $workspace->slug]);
}

beforeEach(function () {
    // The 16th: sixteen days lived this month, a full thirty-one last month.
    $this->today = freezeWelleToday();

    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['welle_module_enabled' => true]);

    $this->url = route('api.workspaces.welle.stats.esc-rate', ['workspace' => $this->workspace->slug]);
    $this->movementUrl = pillarStatUrl($this->workspace, 'movement');
});

it('counts esc days over the days of the month that have elapsed', function () {
    // Seven ESC days out of sixteen lived — 43.75%, which the card rounds.
    foreach (range(1, 7) as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, true);
    }

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'value' => 43.75,
            'esc_days' => 7,
            'total_days' => 16,
            'month' => $this->today->format('Y-m'),
            'connected' => false,
        ]);
});

it('counts the days nothing was done on, which have no row of their own', function () {
    // Two ESC days and fourteen days that went by unused. Counted over the rows
    // alone this is a perfect month; counted over the days lived it is 12.5%.
    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, true);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['value' => 12.5, 'esc_days' => 2, 'total_days' => 16]);
});

it('counts a whole month of days for a month already over', function () {
    // August has thirty-one days and all of them are behind us, so the month is
    // counted out of the month rather than out of today's date.
    $lastMonth = $this->today->startOfMonth()->subMonth();

    welleDay($this->workspace->id, $this->owner->id, 1, true);

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['esc_days' => 0, 'total_days' => 31]);
});

it('has no rate for a month that has not started', function () {
    $nextMonth = $this->today->startOfMonth()->addMonth();

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$nextMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['value' => null, 'esc_days' => 0, 'total_days' => 0]);
});

it('reads only the signed-in user rows, in this workspace, in this month', function () {
    $other = makeWorkspaceMember($this->workspace);
    ['workspace' => $otherWorkspace] = makeWorkspaceWithOwner();

    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => true]);

    // Someone else's day, the same day in another workspace, and a day of the
    // month before — none of them belong in this card's figures.
    welleDay($this->workspace->id, $other->id, 3, true);
    welleDay($otherWorkspace->id, $this->owner->id, 3, true);

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $this->today->startOfMonth()->subDay()->toDateString(),
        'movement' => true,
        'meditation' => true,
        'learning' => true,
        'pillars_completed' => 3,
        'is_esc' => true,
    ]);

    // One ESC day of the owner's own, over the sixteen days lived.
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['value' => 6.25, 'esc_days' => 1, 'total_days' => 16]);
});

it('reads the month asked for', function () {
    $lastMonth = $this->today->startOfMonth()->subMonth();

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $lastMonth->toDateString(),
        'movement' => true,
        'meditation' => true,
        'learning' => true,
        'pillars_completed' => 3,
        'is_esc' => true,
    ]);

    // One ESC day out of August's thirty-one.
    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['value' => 3.23, 'esc_days' => 1, 'total_days' => 31]);
});

it('falls back to this month rather than erroring on an unreadable month', function () {
    welleDay($this->workspace->id, $this->owner->id, 1, true);

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month=not-a-month')
        ->assertOk()
        ->assertJson(['month' => $this->today->format('Y-m'), 'total_days' => 16]);
});

it('reads a month of nothing as none of its days rather than as no answer', function () {
    connectWelleAccount($this->owner)->forceFill(['last_synced_at' => now()])->save();

    // Connected, fetched, and nothing done: 0% is the answer, not an absence.
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['value' => 0, 'esc_days' => 0, 'total_days' => 16, 'synced' => true]);
});

it('says whether a welle account is connected, and whether it has been fetched', function () {
    $integration = connectWelleAccount($this->owner);

    // The two together are what tell an unfetched card apart from a real zero.
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['connected' => true, 'synced' => false]);

    $integration->forceFill(['last_synced_at' => now()])->save();

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['connected' => true, 'synced' => true]);
});

it('answers a member holding the grant', function () {
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    welleDay($this->workspace->id, $member->id, 1, true);

    $this->actingAs($member)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['value' => 6.25, 'esc_days' => 1, 'total_days' => 16]);
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

/*
|--------------------------------------------------------------------------
| Days with Movement
|--------------------------------------------------------------------------
|
| The same month of elapsed days, counted on one pillar instead of all three —
| so a day that was not an ESC day still counts here if movement was ticked on
| it.
*/

it('counts the days movement was ticked, and their share of the month', function () {
    // Seven full ESC days plus six more where only movement was ticked —
    // thirteen days with movement out of the sixteen lived, 81.25%.
    foreach (range(1, 13) as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, $day <= 7, ['movement' => true]);
    }

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson([
            'value' => 13,
            'total_days' => 16,
            'rate' => 81.25,
            'month' => $this->today->format('Y-m'),
        ]);
});

it('counts a movement day that was not an esc day', function () {
    // One day with movement alone, one with the other two — so the card cannot
    // be counting days that had something done on them.
    welleDay($this->workspace->id, $this->owner->id, 1, false, ['movement' => true]);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['meditation' => true, 'learning' => true]);

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 16, 'rate' => 6.25]);
});

it('reads only the signed-in user rows, in this workspace, in this month, for a pillar', function () {
    $other = makeWorkspaceMember($this->workspace);
    ['workspace' => $otherWorkspace] = makeWorkspaceWithOwner();

    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $other->id, 2, true);
    welleDay($otherWorkspace->id, $this->owner->id, 2, true);

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $this->today->startOfMonth()->subDay()->toDateString(),
        'movement' => true,
        'pillars_completed' => 1,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 16, 'rate' => 6.25]);
});

it('reads the month asked for, for a pillar', function () {
    $lastMonth = $this->today->startOfMonth()->subMonth();

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $lastMonth->toDateString(),
        'movement' => true,
        'pillars_completed' => 1,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 31, 'month' => $lastMonth->format('Y-m')]);
});

it('has no count for a pillar in a month that has not started', function () {
    $nextMonth = $this->today->startOfMonth()->addMonth();

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl.'?month='.$nextMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['value' => null, 'total_days' => 0, 'rate' => null]);
});

it('reads a pillar nobody ticked as none of the month rather than as no answer', function () {
    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 0, 'total_days' => 16, 'rate' => 0]);
});

it('answers a member holding the grant, for a pillar', function () {
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    welleDay($this->workspace->id, $member->id, 1, true);

    $this->actingAs($member)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 16]);
});

it('counts meditation and learning on their own terms', function () {
    // Four days with something on them, one of them ESC. The other three each
    // tick a single pillar, so no two cards can be reading the same column.
    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => true, 'meditation' => false, 'learning' => false]);
    welleDay($this->workspace->id, $this->owner->id, 3, false, ['movement' => false, 'meditation' => true, 'learning' => false]);
    welleDay($this->workspace->id, $this->owner->id, 4, false, ['movement' => false, 'meditation' => false, 'learning' => true]);

    // Each pillar: its own day plus the ESC day that ticked all three, over the
    // sixteen days lived.
    foreach (WelleDailyRecord::PILLARS as $pillar) {
        $this->actingAs($this->owner)
            ->getJson(pillarStatUrl($this->workspace, $pillar))
            ->assertOk()
            ->assertJson([
                'value' => 2,
                'total_days' => 16,
                'rate' => 12.5,
                'pillar' => $pillar,
            ]);
    }
});

it('turns a guest away from every pillar', function () {
    // Before anything signs in: actingAs sticks for the rest of the test, so a
    // guest assertion after one would be asserting about that user instead.
    foreach (WelleDailyRecord::PILLARS as $pillar) {
        $this->getJson(pillarStatUrl($this->workspace, $pillar))->assertUnauthorized();
    }
});

it('gates every pillar, not just the first', function () {
    $stranger = User::factory()->create();
    $member = makeWorkspaceMember($this->workspace);

    foreach (WelleDailyRecord::PILLARS as $pillar) {
        $url = pillarStatUrl($this->workspace, $pillar);

        $this->actingAs($stranger)->getJson($url)->assertForbidden();
        $this->actingAs($member)->getJson($url)->assertForbidden();
    }
});

it('is gone from every pillar while the welle module is switched off', function () {
    $this->workspace->update(['welle_module_enabled' => false]);

    foreach (WelleDailyRecord::PILLARS as $pillar) {
        $this->actingAs($this->owner)
            ->getJson(pillarStatUrl($this->workspace, $pillar))
            ->assertNotFound();
    }
});
