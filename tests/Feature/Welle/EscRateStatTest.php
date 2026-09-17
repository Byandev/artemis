<?php

use App\Enums\Permission;
use App\Models\User;
use App\Models\WelleDailyRecord;
use App\Models\Workspace;
use Illuminate\Support\Carbon;

/*
|--------------------------------------------------------------------------
| My ESC stat cards
|--------------------------------------------------------------------------
|
| Every card is counted over the days Welle has a record of — the fetch writes
| a row for every elapsed day and drops the ones still to come — so "7 of 16"
| is seven days out of sixteen lived, not seven out of a whole month.
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
    ['user' => $this->owner, 'workspace' => $this->workspace] = makeWorkspaceWithOwner();

    $this->workspace->update(['welle_module_enabled' => true]);

    $this->url = route('api.workspaces.welle.stats.esc-rate', ['workspace' => $this->workspace->slug]);
    $this->movementUrl = pillarStatUrl($this->workspace, 'movement');
});

it('counts esc days over the days the month has a record of', function () {
    // Sixteen recorded days, seven of them ESC — 43.75%, which the card rounds.
    foreach (range(1, 16) as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, $day <= 7);
    }

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson([
            'value' => 43.75,
            'esc_days' => 7,
            'total_days' => 16,
            'month' => Carbon::today()->format('Y-m'),
            'connected' => false,
        ]);
});

it('reads only the signed-in user rows, in this workspace, in this month', function () {
    $other = makeWorkspaceMember($this->workspace);
    ['workspace' => $otherWorkspace] = makeWorkspaceWithOwner();

    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false);

    // Someone else's day, the same day in another workspace, and a day of the
    // month before — none of them belong in this card's figures.
    welleDay($this->workspace->id, $other->id, 3, true);
    welleDay($otherWorkspace->id, $this->owner->id, 3, true);

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => Carbon::today()->startOfMonth()->subDay()->toDateString(),
        'pillars_completed' => 3,
        'is_esc' => true,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['value' => 50, 'esc_days' => 1, 'total_days' => 2]);
});

it('reads the month asked for', function () {
    $lastMonth = Carbon::today()->startOfMonth()->subMonth();

    WelleDailyRecord::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->owner->id,
        'date' => $lastMonth->toDateString(),
        'pillars_completed' => 3,
        'is_esc' => true,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month='.$lastMonth->format('Y-m'))
        ->assertOk()
        ->assertJson(['value' => 100, 'esc_days' => 1, 'total_days' => 1]);
});

it('falls back to this month rather than erroring on an unreadable month', function () {
    welleDay($this->workspace->id, $this->owner->id, 1, true);

    $this->actingAs($this->owner)
        ->getJson($this->url.'?month=not-a-month')
        ->assertOk()
        ->assertJson(['month' => Carbon::today()->format('Y-m'), 'total_days' => 1]);
});

it('has no rate rather than a rate of none while nothing is synced', function () {
    $this->actingAs($this->owner)
        ->getJson($this->url)
        ->assertOk()
        ->assertJson(['value' => null, 'esc_days' => 0, 'total_days' => 0]);
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
        ->assertJson(['value' => 100, 'esc_days' => 1, 'total_days' => 1]);
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
| The same month, counted on one pillar instead of all three — so a day that
| was not an ESC day still counts here if movement was ticked on it.
*/

it('counts the days movement was ticked, and their share of the month', function () {
    // Sixteen recorded days: seven full ESC days plus six more where only
    // movement was ticked — thirteen days with movement, 81.25% of the month.
    foreach (range(1, 16) as $day) {
        welleDay($this->workspace->id, $this->owner->id, $day, $day <= 7, ['movement' => $day <= 13]);
    }

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson([
            'value' => 13,
            'total_days' => 16,
            'rate' => 81.25,
            'month' => Carbon::today()->format('Y-m'),
        ]);
});

it('counts a movement day that was not an esc day', function () {
    welleDay($this->workspace->id, $this->owner->id, 1, false, ['movement' => true]);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => false]);

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 2, 'rate' => 50]);
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
        'date' => Carbon::today()->startOfMonth()->subDay()->toDateString(),
        'movement' => true,
        'pillars_completed' => 1,
    ]);

    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 1, 'rate' => 100]);
});

it('reads the month asked for, for a pillar', function () {
    $lastMonth = Carbon::today()->startOfMonth()->subMonth();

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
        ->assertJson(['value' => 1, 'total_days' => 1, 'month' => $lastMonth->format('Y-m')]);
});

it('has no count rather than a count of none while nothing is synced', function () {
    $this->actingAs($this->owner)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => null, 'total_days' => 0, 'rate' => null]);
});

it('answers a member holding the grant, for a pillar', function () {
    $member = makeMemberWithPermissions($this->workspace, [Permission::ViewMyEsc->value], 'Welle');

    welleDay($this->workspace->id, $member->id, 1, true);

    $this->actingAs($member)
        ->getJson($this->movementUrl)
        ->assertOk()
        ->assertJson(['value' => 1, 'total_days' => 1]);
});

it('counts meditation and learning on their own terms', function () {
    // Four recorded days, one ESC. The other three each tick a single pillar,
    // so no two of the three cards can be reading the same column.
    welleDay($this->workspace->id, $this->owner->id, 1, true);
    welleDay($this->workspace->id, $this->owner->id, 2, false, ['movement' => true, 'meditation' => false, 'learning' => false]);
    welleDay($this->workspace->id, $this->owner->id, 3, false, ['movement' => false, 'meditation' => true, 'learning' => false]);
    welleDay($this->workspace->id, $this->owner->id, 4, false, ['movement' => false, 'meditation' => false, 'learning' => true]);

    // Each pillar: its own day plus the ESC day that ticked all three.
    foreach (WelleDailyRecord::PILLARS as $pillar) {
        $this->actingAs($this->owner)
            ->getJson(pillarStatUrl($this->workspace, $pillar))
            ->assertOk()
            ->assertJson([
                'value' => 2,
                'total_days' => 4,
                'rate' => 50,
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
